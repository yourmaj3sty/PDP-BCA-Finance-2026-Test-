<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveApplicationRequest;
use App\Http\Requests\DisburseApplicationRequest;
use App\Http\Requests\RejectApplicationRequest;
use App\Http\Requests\SignApplicationRequest;
use App\Http\Requests\StoreCreditApplicationRequest;
use App\Http\Requests\UpdateCreditApplicationRequest;
use App\Http\Resources\CreditApplicationResource;
use App\Models\CreditApplication;
use App\Services\CreditApplicationService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

final class CreditApplicationController extends Controller
{
    private const array COMPANY_HEADER = [
        'name' => 'PT. JKL Multifinance Indonesia',
        'division' => 'Divisi Pembiayaan Kendaraan Bermotor',
        'address' => 'Jl. Jend. Sudirman Kav. 45, Jakarta Selatan 12930',
        'phone' => '(021) 5091-4400',
    ];

    public function __construct(
        private readonly CreditApplicationService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CreditApplication::class);

        $user = $request->user();
        $baseQuery = CreditApplication::query();

        // Sales Dealer hanya melihat pengajuan yang ia buat sendiri.
        if ($user->hasRole(UserRole::SalesDealer)) {
            $baseQuery->where('created_by', $user->id);
        }

        // Ringkasan dihitung sebelum filter, supaya angka di kartu dashboard tidak ikut berubah.
        $countsByStatus = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $count = fn (ApplicationStatus $status): int => (int) ($countsByStatus[$status->value] ?? 0);

        $applications = $baseQuery
            ->with(['creator', 'approver'])
            ->withCount('documents')
            ->filterStatus($request->string('status')->toString())
            ->search($request->string('search')->toString())
            ->latest('updated_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => CreditApplicationResource::collection($applications),
            'summary' => [
                'draft_dealer' => $count(ApplicationStatus::DraftDealer),
                'waiting_review' => $count(ApplicationStatus::Submitted),
                'rejected' => $count(ApplicationStatus::Rejected),
                'approved' => $count(ApplicationStatus::Approved),
                'ready_disburse' => $count(ApplicationStatus::Signed),
                'completed' => $count(ApplicationStatus::Disbursed),
                'total' => (int) $countsByStatus->sum(),
            ],
        ]);
    }

    public function store(StoreCreditApplicationRequest $request): JsonResponse
    {
        $application = $this->service->createDraft(
            actor: $request->user(),
            data: $request->validated(),
            files: $this->formDocuments($request),
        );

        return response()->json([
            'message' => 'Pengajuan awal berhasil disimpan sebagai Draft Dealer.',
            'data' => new CreditApplicationResource($application),
        ], 201);
    }

    public function show(CreditApplication $application): JsonResponse
    {
        Gate::authorize('view', $application);

        $application->load(['creator', 'approver', 'documents.uploader', 'logs.user']);

        return response()->json([
            'data' => new CreditApplicationResource($application),
        ]);
    }

    public function update(UpdateCreditApplicationRequest $request, CreditApplication $application): JsonResponse
    {
        $updated = $this->service->updateApplication(
            actor: $request->user(),
            application: $application,
            data: $request->validated(),
            files: $this->formDocuments($request),
        );

        return response()->json([
            'message' => 'Data pengajuan berhasil diperbarui.',
            'data' => new CreditApplicationResource($updated),
        ]);
    }

    public function submit(Request $request, CreditApplication $application): JsonResponse
    {
        Gate::authorize('submit', $application);

        $submitted = $this->service->submit(
            actor: $request->user(),
            application: $application,
            notes: $this->validatedNotes($request),
        );

        return response()->json([
            'message' => 'Pengajuan berhasil dikirim ke Atasan Marketing untuk ditinjau.',
            'data' => new CreditApplicationResource($submitted),
        ]);
    }

    public function approve(ApproveApplicationRequest $request, CreditApplication $application): JsonResponse
    {
        $approved = $this->service->approve(
            actor: $request->user(),
            application: $application,
            notes: $request->validated('notes'),
        );

        return response()->json([
            'message' => 'Pengajuan disetujui. Dokumen Kontrak dan PO siap diterbitkan.',
            'data' => new CreditApplicationResource($approved),
        ]);
    }

    public function reject(RejectApplicationRequest $request, CreditApplication $application): JsonResponse
    {
        $rejected = $this->service->reject(
            actor: $request->user(),
            application: $application,
            notes: (string) $request->validated('notes'),
        );

        return response()->json([
            'message' => 'Pengajuan ditolak dan dikembalikan ke Marketing untuk revisi.',
            'data' => new CreditApplicationResource($rejected),
        ]);
    }

    public function contract(CreditApplication $application): JsonResponse
    {
        Gate::authorize('viewDocuments', $application);

        // Tanggal kontrak mengikuti tanggal persetujuan. updated_at tidak dipakai karena
        // ikut berubah setiap kali PO dikirim, kontrak ditandatangani, atau dana dicairkan.
        $issuedAt = $this->approvedAt($application) ?? $application->updated_at;

        return $this->printableDocument($application, 'contract', 'KTR-', $issuedAt);
    }

    public function po(CreditApplication $application): JsonResponse
    {
        Gate::authorize('viewDocuments', $application);

        $issuedAt = $application->po_sent_at ?? $this->approvedAt($application) ?? $application->updated_at;

        return $this->printableDocument($application, 'po', 'PO-', $issuedAt);
    }

    public function sendPo(Request $request, CreditApplication $application): JsonResponse
    {
        Gate::authorize('sendPo', $application);

        $updated = $this->service->markPoSent(
            actor: $request->user(),
            application: $application,
            notes: $this->validatedNotes($request),
        );

        return response()->json([
            'message' => 'Status pengiriman Purchase Order (PO) ke dealer berhasil dicatat.',
            'data' => new CreditApplicationResource($updated),
        ]);
    }

    public function sign(SignApplicationRequest $request, CreditApplication $application): JsonResponse
    {
        $signed = $this->service->sign(
            actor: $request->user(),
            application: $application,
            signatureImage: $request->file('signature_image'),
            signedContractFile: $request->file('kontrak_ttd'),
            notes: $request->validated('notes'),
        );

        return response()->json([
            'message' => 'Tanda tangan kontrak berhasil disimpan. Pengajuan siap untuk pencairan dana.',
            'data' => new CreditApplicationResource($signed),
        ]);
    }

    public function disburse(DisburseApplicationRequest $request, CreditApplication $application): JsonResponse
    {
        $disbursed = $this->service->disburse(
            actor: $request->user(),
            application: $application,
            disbursementRef: (string) $request->validated('disbursement_ref'),
            notes: $request->validated('notes'),
        );

        return response()->json([
            'message' => 'Pencairan dana berhasil dicatat dan status pengajuan selesai.',
            'data' => new CreditApplicationResource($disbursed),
        ]);
    }

    /**
     * @return array<string, UploadedFile|null>
     */
    private function formDocuments(Request $request): array
    {
        $files = [];
        foreach (DocumentType::applicationForm() as $type) {
            $files[$type->value] = $request->file($type->value);
        }

        return $files;
    }

    private function validatedNotes(Request $request): ?string
    {
        return $request->validate(['notes' => ['nullable', 'string', 'max:1000']])['notes'] ?? null;
    }

    private function approvedAt(CreditApplication $application): ?CarbonInterface
    {
        return $application->logs()->where('action', 'APPROVE')->first()?->created_at;
    }

    private function printableDocument(CreditApplication $application, string $kind, string $numberPrefix, ?CarbonInterface $issuedAt): JsonResponse
    {
        $application->load(['creator', 'approver', 'documents']);

        return response()->json([
            'data' => [
                'document_kind' => $kind,
                'document_number' => $numberPrefix.$application->application_number,
                'issued_at' => ($issuedAt ?? now())->toIso8601String(),
                'company' => self::COMPANY_HEADER,
                'application' => new CreditApplicationResource($application),
            ],
        ]);
    }
}
