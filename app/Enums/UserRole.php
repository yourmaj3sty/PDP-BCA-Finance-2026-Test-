<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SalesDealer = 'sales_dealer';
    case Marketing = 'marketing';
    case AtasanMarketing = 'atasan_marketing';
    case AdminBackoffice = 'admin_backoffice';

    public function label(): string
    {
        return match ($this) {
            self::SalesDealer => 'Sales Dealer',
            self::Marketing => 'Marketing',
            self::AtasanMarketing => 'Atasan Marketing',
            self::AdminBackoffice => 'Admin Backoffice',
        };
    }
}

