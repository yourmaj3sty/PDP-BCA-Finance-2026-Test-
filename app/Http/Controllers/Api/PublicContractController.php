<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SignApplicationRequest;
use App\Http\Resources\PublicContractResource;
use App\Models\CreditApplication;
use App\Services\CreditApplicationService;
use Illuminate\Http\JsonResponse;

final class PublicContractController extends Controller
{
    public function __construct(
        private readonly CreditApplicationService $service,
    ) {}

    public function show(string $token): JsonResponse
    {
        $application = CreditApplication::where('contract_sign_token', $token)->firstOrFail();

        return response()->json([
            'data' => new PublicContractResource($application),
        ]);
    }

    public function sign(SignApplicationRequest $request, string $token): JsonResponse
    {
        $application = CreditApplication::where('contract_sign_token', $token)->firstOrFail();

        $signed = $this->service->sign(
            actor: null,
            application: $application,
            signatureImage: $request->file('signature_image'),
            signedContractFile: $request->file('kontrak_ttd'),
            notes: $request->validated('notes'),
        );

        return response()->json([
            'message' => 'Terima kasih. Tanda tangan digital Anda telah tercatat.',
            'data' => new PublicContractResource($signed),
        ]);
    }
}

