<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CreditApplication;
use App\Support\PublicStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditApplication
 */
final class CreditApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'application_number' => $this->application_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer_name' => $this->customer_name,
            'customer_nik' => $this->customer_nik,
            'customer_dob' => $this->customer_dob?->format('Y-m-d'),
            'marital_status' => $this->marital_status->value,
            'spouse_name' => $this->spouse_name,
            'spouse_nik' => $this->spouse_nik,
            'dealer_name' => $this->dealer_name,
            'vehicle_brand' => $this->vehicle_brand,
            'vehicle_model' => $this->vehicle_model,
            'vehicle_type' => $this->vehicle_type,
            'vehicle_color' => $this->vehicle_color,
            'vehicle_price' => $this->vehicle_price,
            'insurance_type' => $this->insurance_type?->value,
            'insurance_premium' => $this->insurance_premium,
            'down_payment' => $this->down_payment,
            'tenor_months' => $this->tenor_months,
            'interest_rate' => $this->interest_rate,
            'monthly_installment' => $this->monthly_installment,
            'loan_breakdown' => $this->loanBreakdown(),
            'contract_sign_token' => $this->when(
                $user !== null && $this->status->canPrintDocuments(),
                $this->contract_sign_token,
            ),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signature_url' => $this->signature_path !== null ? PublicStorage::url($this->signature_path) : null,
            'po_sent_at' => $this->po_sent_at?->toIso8601String(),
            'disbursement_ref' => $this->disbursement_ref,
            'disbursed_at' => $this->disbursed_at?->toIso8601String(),
            'rejection_note' => $this->rejection_note,
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'role_label' => $this->creator->role->label(),
            ] : null),
            'approver' => $this->whenLoaded('approver', fn (): ?array => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
                'role_label' => $this->approver->role->label(),
            ] : null),
            'documents' => ApplicationDocumentResource::collection($this->whenLoaded('documents')),
            'logs' => ApplicationLogResource::collection($this->whenLoaded('logs')),
            'permissions' => $user !== null ? [
                'can_update' => $user->can('update', $this->resource),
                'can_submit' => $user->can('submit', $this->resource),
                'can_approve' => $user->can('approve', $this->resource),
                'can_reject' => $user->can('reject', $this->resource),
                'can_view_documents' => $user->can('viewDocuments', $this->resource),
                'can_send_po' => $user->can('sendPo', $this->resource),
                'can_sign' => $user->can('sign', $this->resource),
                'can_disburse' => $user->can('disburse', $this->resource),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
