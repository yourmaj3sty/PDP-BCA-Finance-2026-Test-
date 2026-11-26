<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CreditApplication;
use App\Support\PublicStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data untuk halaman tanda tangan konsumen (tanpa login). NIK disamarkan, dan
 * dokumen, log, serta token tidak ikut dikirim.
 *
 * @mixin CreditApplication
 */
final class PublicContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'application_number' => $this->application_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer_name' => $this->customer_name,
            'customer_nik_masked' => substr($this->customer_nik, 0, 6).'******'.substr($this->customer_nik, -4),
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
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signature_url' => $this->signature_path !== null ? PublicStorage::url($this->signature_path) : null,
        ];
    }
}
