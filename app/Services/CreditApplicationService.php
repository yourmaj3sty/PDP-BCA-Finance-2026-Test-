<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Enums\InsuranceType;
use App\Enums\MaritalStatus;
use App\Models\ApplicationDocument;
use App\Models\ApplicationLog;
use App\Models\CreditApplication;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreditApplicationService
{
    /** Relasi yang dibutuhkan halaman detail setelah setiap aksi. */
    private const array DETAIL_RELATIONS = ['creator', 'approver', 'documents.uploader', 'logs.user'];

    public function __construct(
        private readonly LoanCalculatorService $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files
     */
    public function createDraft(User $actor, array $data, array $files = []): CreditApplication
    {
        return DB::transaction(function () use ($actor, $data, $files): CreditApplication {
            $application = CreditApplication::create([
                ...$this->prepareAttributes($data),
                'application_number' => $this->generateApplicationNumber(),
                'status' => ApplicationStatus::DraftDealer,
                'created_by' => $actor->id,
            ]);

            $this->storeFormDocuments($actor, $application, $files);

            $this->recordLog($application, $actor, 'CREATE_DRAFT', null, ApplicationStatus::DraftDealer,
                'Pengajuan awal dibuat oleh Sales Dealer.');

            return $application->load(self::DETAIL_RELATIONS);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files
     */
    public function updateApplication(User $actor, CreditApplication $application, array $data, array $files = []): CreditApplication
    {
        return DB::transaction(function () use ($actor, $application, $data, $files): CreditApplication {
            $locked = $this->lock($application);
            $this->ensure($locked->status->isEditable(),
                'Pengajuan hanya dapat diubah saat berstatus Draft Dealer atau Ditolak.');

            $locked->update($this->prepareAttributes($data, $locked));
            $this->storeFormDocuments($actor, $locked, $files);

            $this->recordLog($locked, $actor, 'UPDATE_DATA', $locked->status, $locked->status,
                'Data pengajuan atau dokumen diperbarui.');

            return $locked->load(self::DETAIL_RELATIONS);
        });
    }

    public function submit(User $actor, CreditApplication $application, ?string $notes = null): CreditApplication
    {
        return DB::transaction(function () use ($actor, $application, $notes): CreditApplication {
            $locked = $this->lock($application, with: ['documents']);
            $this->ensure($locked->status->isEditable(),
                'Pengajuan hanya dapat dikirim dari status Draft Dealer atau Ditolak.');
            $this->ensureReadyForSubmission($locked);

            $fromStatus = $locked->status;
            $locked->update([
                'status' => ApplicationStatus::Submitted,
                'rejection_note' => null,
            ]);

            $this->recordLog(
                $locked,
                $actor,
                $fromStatus === ApplicationStatus::Rejected ? 'RESUBMIT' : 'SUBMIT',
                $fromStatus,
                ApplicationStatus::Submitted,
                $notes ?: 'Pengajuan dilengkapi dan dikirim ke Atasan Marketing.',
            );

            return $locked->load(self::DETAIL_RELATIONS);
        });
    }

    public function approve(User $actor, CreditApplication $application, ?string $notes = null): CreditApplication
    {
        return DB::transaction(function () use ($actor, $application, $notes): CreditApplication {
            $locked = $this->lock($application);
            $this->ensure($locked->status === ApplicationStatus::Submitted,
                'Hanya pengajuan berstatus Menunggu Review yang dapat disetujui.');

            $locked->update([
                'status' => ApplicationStatus::Approved,
                'approved_by' => $actor->id,
                'rejection_note' => null,
                'contract_sign_token' => $locked->contract_sign_token ?: Str::random(48),
            ]);

            $this->recordLog($locked, $actor, 'APPROVE', ApplicationStatus::Submitted, ApplicationStatus::Approved,
                $notes ?: 'Pengajuan disetujui. Dokumen Kontrak dan PO siap dicetak.');

            return $locked->load(self::DETAIL_RELATIONS);
        });
    }

    public function reject(User $actor, CreditApplication $application, string $notes): CreditApplication
    {
        return DB::transaction(function () use ($actor, $application, $notes): CreditApplication {
            $locked = $this->lock($application);
            $this->ensure($locked->status === ApplicationStatus::Submitted,
                'Hanya pengajuan berstatus Menunggu Review yang dapat ditolak.');

            $notes = trim($notes);
            $locked->update([
                'status' => ApplicationStatus::Rejected,
                'rejection_note' => $notes,
                'approved_by' => null,
            ]);

            $this->recordLog($locked, $actor, 'REJECT', ApplicationStatus::Submitted, ApplicationStatus::Rejected, $notes);

            return $locked->load(self::DETAIL_RELATIONS);
        });
    }

    public function markPoSent(User $actor, CreditApplication $application, ?string $notes = null): CreditApplication
    {
        return DB::transaction(function () use ($actor, $application, $notes): CreditApplication {
            $locked = $this->lock($application);
            $this->ensure($locked->status->canPrintDocuments(),
                'PO hanya dapat dikirim setelah pengajuan disetujui.');

            $locked->update(['po_sent_at' => $locked->po_sent_at ?? now()]);

            $this->recordLog($locked, $actor, 'SEND_PO', $locked->status, $locked->status,
                $notes ?: "Purchase Order dikirim ke {$locked->dealer_name}.");

            return $locked->load(self::DETAIL_RELATIONS);
        });
    }

    /**
     * $actor null berarti konsumen yang menandatangani lewat tautan publik.
     */
    public function sign(
        ?User $actor,
        CreditApplication $application,
        ?UploadedFile $signatureImage,
        ?UploadedFile $signedContractFile,
        ?string $notes = null,
    ): CreditApplication {
        return DB::transaction(function () use ($actor, $application, $signatureImage, $signedContractFile, $notes): CreditApplication {
            $locked = $this->lock($application);
            $this->ensure($locked->status === ApplicationStatus::Approved,
                'Tanda tangan kontrak hanya dapat diproses pada pengajuan berstatus Disetujui.');

            if ($signatureImage === null && $signedContractFile === null) {
                throw ValidationException::withMessages([
                    'signature' => ['Harap gambar tanda tangan pada kanvas atau unggah file bukti kontrak tertandatangani.'],
                ]);
            }

            $signaturePath = $signatureImage?->store("signatures/{$locked->id}", 'public') ?: $locked->signature_path;

            if ($signedContractFile !== null) {
                // Konsumen tidak punya akun, jadi dokumennya dicatat atas nama pembuat pengajuan.
                $docPath = $this->replaceDocument($locked, $actor?->id ?? $locked->created_by, DocumentType::KontrakTtd, $signedContractFile);

                // Kalau yang diunggah berupa foto, foto itu sekaligus dipakai sebagai gambar tanda tangan.
                $isImage = in_array(strtolower($signedContractFile->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true);
                if ($signaturePath === null && $isImage) {
                    $signaturePath = $docPath;
                }
            }

            $locked->update([
                'status' => ApplicationStatus::Signed,
                'signed_at' => now(),
                'signature_path' => $signaturePath,
                'po_sent_at' => $locked->po_sent_at ?? now(),
            ]);

            $defaultNote = $actor !== null
                ? 'Kontrak ditandatangani dan diverifikasi ke dalam sistem.'
                : 'Kontrak ditandatangani secara digital oleh konsumen melalui tautan kontrak.';

            $this->recordLog($locked, $actor, 'SIGN_CONTRACT', ApplicationStatus::Approved, ApplicationStatus::Signed,
                $notes ?: $defaultNote);

            return $locked->load(self::DETAIL_RELATIONS);
        });
    }

    public function disburse(User $actor, CreditApplication $application, string $disbursementRef, ?string $notes = null): CreditApplication
    {
        return DB::transaction(function () use ($actor, $application, $disbursementRef, $notes): CreditApplication {
            $locked = $this->lock($application);
            $this->ensure($locked->status === ApplicationStatus::Signed,
                'Pencairan dana hanya dapat dilakukan setelah kontrak ditandatangani (status SIGNED).');

            $ref = trim($disbursementRef);
            $locked->update([
                'status' => ApplicationStatus::Disbursed,
                'disbursement_ref' => $ref,
                'disbursed_at' => now(),
            ]);

            $this->recordLog($locked, $actor, 'DISBURSE', ApplicationStatus::Signed, ApplicationStatus::Disbursed,
                $notes ? "{$notes} (Ref: {$ref})" : "Dana dicairkan ke dealer dengan nomor referensi {$ref}.");

            return $locked->load(self::DETAIL_RELATIONS);
        });
    }

    /**
     * Gabungkan input baru dengan data lama, lalu hitung ulang premi dan angsuran di server.
     * Field yang tidak dikirim tetap memakai nilai sebelumnya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareAttributes(array $data, ?CreditApplication $existing = null): array
    {
        $maritalStatus = isset($data['marital_status'])
            ? MaritalStatus::from((string) $data['marital_status'])
            : $existing?->marital_status;
        $isMarried = $maritalStatus === MaritalStatus::Menikah;

        $vehiclePrice = (int) ($data['vehicle_price'] ?? $existing?->vehicle_price ?? 0);
        $downPayment = $this->optionalInt($data, 'down_payment', $existing?->down_payment);
        $tenorMonths = $this->optionalInt($data, 'tenor_months', $existing?->tenor_months);

        $insuranceRaw = array_key_exists('insurance_type', $data) ? $data['insurance_type'] : $existing?->insurance_type?->value;
        $insuranceType = filled($insuranceRaw) ? InsuranceType::from((string) $insuranceRaw) : null;

        // Validasi request hanya membandingkan DP yang ikut dikirim. DP lama bisa jadi
        // lebih besar dari harga baru, dan kalkulator akan melempar exception (HTTP 500).
        if ($downPayment !== null && $downPayment >= $vehiclePrice) {
            throw ValidationException::withMessages([
                'down_payment' => ['Down payment (DP) harus lebih kecil dari harga kendaraan (OTR).'],
            ]);
        }

        $calc = null;
        if ($vehiclePrice > 0 && $downPayment !== null && $insuranceType !== null && $tenorMonths !== null) {
            $calc = $this->calculator->calculate($vehiclePrice, $downPayment, $insuranceType, $tenorMonths);
        }

        return [
            'customer_name' => $data['customer_name'] ?? $existing?->customer_name,
            'customer_nik' => $data['customer_nik'] ?? $existing?->customer_nik,
            'customer_dob' => $data['customer_dob'] ?? $existing?->customer_dob?->format('Y-m-d'),
            'marital_status' => $maritalStatus,
            'spouse_name' => $isMarried ? ($data['spouse_name'] ?? $existing?->spouse_name) : null,
            'spouse_nik' => $isMarried ? ($data['spouse_nik'] ?? $existing?->spouse_nik) : null,
            'dealer_name' => $data['dealer_name'] ?? $existing?->dealer_name,
            'vehicle_brand' => $data['vehicle_brand'] ?? $existing?->vehicle_brand,
            'vehicle_model' => $data['vehicle_model'] ?? $existing?->vehicle_model,
            'vehicle_type' => $data['vehicle_type'] ?? $existing?->vehicle_type,
            'vehicle_color' => $data['vehicle_color'] ?? $existing?->vehicle_color,
            'vehicle_price' => $vehiclePrice,
            'insurance_type' => $insuranceType,
            'insurance_premium' => $calc['insurance_premium'] ?? 0,
            'down_payment' => $downPayment,
            'tenor_months' => $tenorMonths,
            'interest_rate' => LoanCalculatorService::ANNUAL_FLAT_INTEREST_RATE,
            'monthly_installment' => $calc['monthly_installment'] ?? null,
        ];
    }

    /**
     * Key yang dikirim kosong berarti dihapus (null); key yang tidak dikirim memakai nilai lama.
     *
     * @param  array<string, mixed>  $data
     */
    private function optionalInt(array $data, string $key, ?int $current): ?int
    {
        if (! array_key_exists($key, $data)) {
            return $current;
        }

        return filled($data[$key]) ? (int) $data[$key] : null;
    }

    /**
     * @param  array<string, UploadedFile|null>  $files
     */
    private function storeFormDocuments(User $actor, CreditApplication $application, array $files): void
    {
        foreach (DocumentType::applicationForm() as $type) {
            $file = $files[$type->value] ?? null;
            if ($file instanceof UploadedFile) {
                $this->replaceDocument($application, $actor->id, $type, $file);
            }
        }
    }

    /**
     * Satu pengajuan hanya menyimpan satu file per jenis dokumen; file lama diganti.
     */
    private function replaceDocument(CreditApplication $application, int $uploaderId, DocumentType $type, UploadedFile $file): string
    {
        $storedPath = $file->store("documents/{$application->id}", 'public');

        $document = ApplicationDocument::firstOrNew([
            'credit_application_id' => $application->id,
            'document_type' => $type,
        ]);

        $oldPath = $document->file_path;

        $document->fill([
            'file_path' => $storedPath,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 240, ''),
            'uploaded_by' => $uploaderId,
        ])->save();

        // Hapus file lama setelah commit, supaya file tidak hilang kalau transaksi gagal.
        if (filled($oldPath)) {
            DB::afterCommit(static fn () => Storage::disk('public')->delete($oldPath));
        }

        return $storedPath;
    }

    private function ensureReadyForSubmission(CreditApplication $application): void
    {
        $errors = [];

        if ($application->marital_status === MaritalStatus::Menikah) {
            if (blank($application->spouse_name)) {
                $errors['spouse_name'] = ['Nama pasangan wajib diisi jika status perkawinan Menikah.'];
            }
            if (! preg_match('/^\d{16}$/', (string) $application->spouse_nik)) {
                $errors['spouse_nik'] = ['NIK pasangan wajib 16 digit numerik jika status perkawinan Menikah.'];
            }
        }

        if ($application->insurance_type === null) {
            $errors['insurance_type'] = ['Jenis asuransi wajib dilengkapi sebelum dikirim.'];
        }
        if ($application->down_payment === null || $application->down_payment >= $application->vehicle_price) {
            $errors['down_payment'] = ['Down payment wajib diisi dan lebih kecil dari harga kendaraan.'];
        }
        if (! in_array($application->tenor_months, LoanCalculatorService::ALLOWED_TENORS, true)) {
            $errors['tenor_months'] = ['Lama kredit (tenor) wajib dipilih sebelum dikirim.'];
        }
        if (($application->monthly_installment ?? 0) <= 0) {
            $errors['monthly_installment'] = ['Simulasi angsuran belum terhitung.'];
        }

        $uploadedTypes = $application->documents->pluck('document_type');

        foreach (DocumentType::applicationForm() as $type) {
            if (! $uploadedTypes->contains($type)) {
                $errors[$type->value] = ["Dokumen {$type->label()} wajib diunggah sebelum dikirim."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Format: JKL-YYYYMM-0001, urut per bulan.
     */
    private function generateApplicationNumber(): string
    {
        $prefix = 'JKL-'.now()->format('Ym').'-';

        $latest = CreditApplication::where('application_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('application_number');

        $lastSequence = is_string($latest) ? (int) substr($latest, strlen($prefix)) : 0;

        return $prefix.str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Ambil ulang baris dengan lock, supaya dua aksi bersamaan tidak memproses status yang sama.
     *
     * @param  list<string>  $with
     */
    private function lock(CreditApplication $application, array $with = []): CreditApplication
    {
        return CreditApplication::with($with)
            ->whereKey($application->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['status' => [$message]]);
        }
    }

    private function recordLog(
        CreditApplication $application,
        ?User $actor,
        string $action,
        ?ApplicationStatus $fromStatus,
        ApplicationStatus $toStatus,
        ?string $notes,
    ): void {
        ApplicationLog::create([
            'credit_application_id' => $application->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus->value,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
