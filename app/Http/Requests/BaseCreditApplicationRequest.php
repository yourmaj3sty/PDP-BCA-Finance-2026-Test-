<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\InsuranceType;
use App\Enums\MaritalStatus;
use App\Services\LoanCalculatorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BaseCreditApplicationRequest extends FormRequest
{
    /**
     * Aturan validasi bersama antara input awal (Sales Dealer) dan kelengkapan/revisi (Marketing).
     *
     * @return array<string, mixed>
     */
    protected function sharedFieldRules(bool $requireLoanFields): array
    {
        $loanRequirement = $requireLoanFields ? 'required' : 'nullable';

        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_nik' => ['required', 'digits:16'],
            'customer_dob' => ['required', 'date', 'before:today'],
            'marital_status' => ['required', Rule::enum(MaritalStatus::class)],
            'spouse_name' => ['required_if:marital_status,Menikah', 'nullable', 'string', 'max:150'],
            'spouse_nik' => ['required_if:marital_status,Menikah', 'nullable', 'digits:16'],

            'dealer_name' => ['required', 'string', 'max:150'],
            'vehicle_brand' => ['required', 'string', 'max:80'],
            'vehicle_model' => ['required', 'string', 'max:100'],
            'vehicle_type' => ['required', 'string', 'max:100'],
            'vehicle_color' => ['required', 'string', 'max:60'],
            'vehicle_price' => ['required', 'integer', 'min:1000000'],

            'insurance_type' => [$loanRequirement, Rule::enum(InsuranceType::class)],
            'down_payment' => [
                $loanRequirement,
                'integer',
                'min:0',
                'lt:vehicle_price',
                'required_with:insurance_type,tenor_months',
            ],
            'tenor_months' => [
                $loanRequirement,
                'integer',
                Rule::in(LoanCalculatorService::ALLOWED_TENORS),
                'required_with:insurance_type,down_payment',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function documentRules(bool $requireInitialDocs): array
    {
        $initialRequirement = $requireInitialDocs ? 'required' : 'nullable';

        return [
            'ktp' => [$initialRequirement, 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'spk' => [$initialRequirement, 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'bukti_bayar' => [$initialRequirement, 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'kk' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Nama lengkap konsumen wajib diisi.',
            'customer_nik.required' => 'NIK konsumen wajib diisi.',
            'customer_nik.digits' => 'NIK konsumen harus terdiri dari tepat 16 digit angka.',
            'customer_dob.required' => 'Tanggal lahir konsumen wajib diisi.',
            'customer_dob.before' => 'Tanggal lahir harus sebelum hari ini.',
            'marital_status.required' => 'Status perkawinan wajib dipilih.',
            'spouse_name.required_if' => 'Nama pasangan wajib diisi jika status perkawinan Menikah.',
            'spouse_nik.required_if' => 'NIK pasangan wajib diisi jika status perkawinan Menikah.',
            'spouse_nik.digits' => 'NIK pasangan harus terdiri dari tepat 16 digit angka.',
            'dealer_name.required' => 'Nama dealer wajib diisi.',
            'vehicle_brand.required' => 'Merk kendaraan wajib diisi.',
            'vehicle_model.required' => 'Model kendaraan wajib diisi.',
            'vehicle_type.required' => 'Tipe kendaraan wajib diisi.',
            'vehicle_color.required' => 'Warna kendaraan wajib diisi.',
            'vehicle_price.required' => 'Harga kendaraan (OTR) wajib diisi.',
            'vehicle_price.min' => 'Harga kendaraan minimal Rp 1.000.000.',
            'insurance_type.required' => 'Jenis asuransi wajib dipilih.',
            'down_payment.required' => 'Down payment (DP) wajib diisi.',
            'down_payment.lt' => 'Down payment (DP) harus lebih kecil dari harga kendaraan (OTR).',
            'tenor_months.required' => 'Lama kredit (tenor) wajib dipilih.',
            'tenor_months.in' => 'Tenor kredit harus 12, 24, 36, 48, atau 60 bulan.',
            'ktp.required' => 'Dokumen KTP konsumen wajib diunggah.',
            'spk.required' => 'Dokumen SPK wajib diunggah.',
            'bukti_bayar.required' => 'Dokumen Bukti Bayar Tanda Jadi wajib diunggah.',
            '*.mimes' => 'Format file harus JPG, PNG, atau PDF.',
            '*.max' => 'Ukuran file maksimal 2 MB.',
        ];
    }
}

