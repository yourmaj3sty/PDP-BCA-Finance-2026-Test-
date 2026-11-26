<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InsuranceType;
use InvalidArgumentException;

final readonly class LoanCalculatorService
{
    public const float ANNUAL_FLAT_INTEREST_RATE = 6.0;

    /** @var list<int> */
    public const array ALLOWED_TENORS = [12, 24, 36, 48, 60];

    /**
     * Menghitung rincian pembiayaan kendaraan dengan metode bunga flat tahunan standar.
     *
     * @return array{
     *     principal_vehicle: int,
     *     insurance_rate_percent: float,
     *     insurance_premium: int,
     *     total_principal: int,
     *     annual_interest_rate: float,
     *     total_interest: int,
     *     total_loan: int,
     *     monthly_installment: int
     * }
     */
    public function calculate(
        int $vehiclePrice,
        int $downPayment,
        InsuranceType $insuranceType,
        int $tenorMonths,
    ): array {
        if ($vehiclePrice <= 0) {
            throw new InvalidArgumentException('Harga kendaraan harus lebih besar dari nol.');
        }

        if ($downPayment < 0 || $downPayment >= $vehiclePrice) {
            throw new InvalidArgumentException('Down payment harus lebih kecil dari harga kendaraan.');
        }

        if (! in_array($tenorMonths, self::ALLOWED_TENORS, true)) {
            throw new InvalidArgumentException('Tenor kredit tidak valid.');
        }

        $years = $tenorMonths / 12;
        $principalVehicle = $vehiclePrice - $downPayment;
        $insurancePremium = (int) round($vehiclePrice * $insuranceType->annualRate() * $years);
        $totalPrincipal = $principalVehicle + $insurancePremium;
        $totalInterest = (int) round($totalPrincipal * (self::ANNUAL_FLAT_INTEREST_RATE / 100) * $years);
        $totalLoan = $totalPrincipal + $totalInterest;
        $monthlyInstallment = (int) round($totalLoan / $tenorMonths);

        return [
            'principal_vehicle' => $principalVehicle,
            'insurance_rate_percent' => $insuranceType->annualRatePercent(),
            'insurance_premium' => $insurancePremium,
            'total_principal' => $totalPrincipal,
            'annual_interest_rate' => self::ANNUAL_FLAT_INTEREST_RATE,
            'total_interest' => $totalInterest,
            'total_loan' => $totalLoan,
            'monthly_installment' => $monthlyInstallment,
        ];
    }
}

