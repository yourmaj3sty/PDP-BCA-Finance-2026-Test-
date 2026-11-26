<?php

declare(strict_types=1);

namespace App\Enums;

enum InsuranceType: string
{
    case AllRisk = 'All Risk';
    case Tlo = 'TLO';
    case Kombinasi = 'Kombinasi';

    /**
     * Premi tahunan sebagai rasio terhadap harga kendaraan (OTR).
     */
    public function annualRate(): float
    {
        return match ($this) {
            self::AllRisk => 0.025,
            self::Kombinasi => 0.016,
            self::Tlo => 0.008,
        };
    }

    public function annualRatePercent(): float
    {
        return $this->annualRate() * 100;
    }
}

