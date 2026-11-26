<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\InsuranceType;
use App\Enums\MaritalStatus;
use App\Services\LoanCalculatorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CreditApplication extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'application_number',
        'status',
        'customer_name',
        'customer_nik',
        'customer_dob',
        'marital_status',
        'spouse_name',
        'spouse_nik',
        'dealer_name',
        'vehicle_brand',
        'vehicle_model',
        'vehicle_type',
        'vehicle_color',
        'vehicle_price',
        'insurance_type',
        'insurance_premium',
        'down_payment',
        'tenor_months',
        'interest_rate',
        'monthly_installment',
        'contract_sign_token',
        'signed_at',
        'signature_path',
        'po_sent_at',
        'disbursement_ref',
        'disbursed_at',
        'rejection_note',
        'created_by',
        'approved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'customer_dob' => 'date',
            'marital_status' => MaritalStatus::class,
            'vehicle_price' => 'integer',
            'insurance_type' => InsuranceType::class,
            'insurance_premium' => 'integer',
            'down_payment' => 'integer',
            'tenor_months' => 'integer',
            'interest_rate' => 'float',
            'monthly_installment' => 'integer',
            'signed_at' => 'datetime',
            'po_sent_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class)->orderBy('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApplicationLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Rincian simulasi kredit, atau null kalau data pinjaman belum lengkap/tidak valid.
     *
     * @return array<string, int|float>|null
     */
    public function loanBreakdown(): ?array
    {
        if (
            $this->vehicle_price <= 0
            || $this->down_payment === null
            || $this->down_payment >= $this->vehicle_price
            || $this->insurance_type === null
            || ! in_array($this->tenor_months, LoanCalculatorService::ALLOWED_TENORS, true)
        ) {
            return null;
        }

        return app(LoanCalculatorService::class)->calculate(
            vehiclePrice: $this->vehicle_price,
            downPayment: $this->down_payment,
            insuranceType: $this->insurance_type,
            tenorMonths: $this->tenor_months,
        );
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '' || $status === 'ALL') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $trimmed = trim((string) $term);
        if ($trimmed === '') {
            return $query;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $trimmed);
        $like = "%{$escaped}%";

        return $query->where(function (Builder $sub) use ($like): void {
            $sub->where('application_number', 'like', $like)
                ->orWhere('customer_name', 'like', $like)
                ->orWhere('customer_nik', 'like', $like)
                ->orWhere('dealer_name', 'like', $like)
                ->orWhere('vehicle_brand', 'like', $like)
                ->orWhere('vehicle_model', 'like', $like);
        });
    }
}

