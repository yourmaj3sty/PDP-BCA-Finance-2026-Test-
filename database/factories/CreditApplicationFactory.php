<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\InsuranceType;
use App\Enums\MaritalStatus;
use App\Models\CreditApplication;
use App\Models\User;
use App\Services\LoanCalculatorService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditApplication>
 */
class CreditApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vehiclePrice = 250_000_000;
        $downPayment = 50_000_000;
        $insuranceType = InsuranceType::AllRisk;
        $tenorMonths = 36;

        $calc = app(LoanCalculatorService::class)->calculate(
            vehiclePrice: $vehiclePrice,
            downPayment: $downPayment,
            insuranceType: $insuranceType,
            tenorMonths: $tenorMonths,
        );

        return [
            'application_number' => 'JKL-'.now()->format('Ym').'-'.fake()->unique()->numerify('####'),
            'status' => ApplicationStatus::DraftDealer,
            'customer_name' => fake()->name(),
            'customer_nik' => fake()->numerify('3174############'),
            'customer_dob' => '1992-05-14',
            'marital_status' => MaritalStatus::BelumMenikah,
            'spouse_name' => null,
            'spouse_nik' => null,
            'dealer_name' => 'Dealer Nusantara Motor Jakarta',
            'vehicle_brand' => 'Honda',
            'vehicle_model' => 'BR-V',
            'vehicle_type' => 'Prestige CVT',
            'vehicle_color' => 'Putih Mutiara',
            'vehicle_price' => $vehiclePrice,
            'insurance_type' => $insuranceType,
            'insurance_premium' => $calc['insurance_premium'],
            'down_payment' => $downPayment,
            'tenor_months' => $tenorMonths,
            'interest_rate' => LoanCalculatorService::ANNUAL_FLAT_INTEREST_RATE,
            'monthly_installment' => $calc['monthly_installment'],
            'created_by' => User::factory(),
        ];
    }
}

