<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\InsuranceType;
use App\Services\LoanCalculatorService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LoanCalculatorServiceTest extends TestCase
{
    private LoanCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LoanCalculatorService;
    }

    public function test_calculates_loan_breakdown_accurately(): void
    {
        $result = $this->calculator->calculate(
            vehiclePrice: 250_000_000,
            downPayment: 50_000_000,
            insuranceType: InsuranceType::AllRisk,
            tenorMonths: 36,
        );

        // Pokok kendaraan: 200.000.000
        // Premi All Risk 3 tahun: 250.000.000 * 2.5% * 3 = 18.750.000
        // Total Pokok: 218.750.000
        // Total Bunga 6% flat * 3 tahun: 218.750.000 * 18% = 39.375.000
        // Total Pinjaman: 258.125.000 -> / 36 = 7.170.139
        $this->assertSame(200_000_000, $result['principal_vehicle']);
        $this->assertSame(18_750_000, $result['insurance_premium']);
        $this->assertSame(218_750_000, $result['total_principal']);
        $this->assertSame(39_375_000, $result['total_interest']);
        $this->assertSame(258_125_000, $result['total_loan']);
        $this->assertSame(7_170_139, $result['monthly_installment']);
    }

    public function test_throws_exception_when_down_payment_exceeds_or_equals_price(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate(
            vehiclePrice: 200_000_000,
            downPayment: 200_000_000,
            insuranceType: InsuranceType::Tlo,
            tenorMonths: 24,
        );
    }

    public function test_throws_exception_for_unsupported_tenor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate(
            vehiclePrice: 200_000_000,
            downPayment: 40_000_000,
            insuranceType: InsuranceType::Kombinasi,
            tenorMonths: 18,
        );
    }
}
