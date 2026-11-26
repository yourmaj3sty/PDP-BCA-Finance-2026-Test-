<?php

declare(strict_types=1);

namespace App\Enums;

enum MaritalStatus: string
{
    case BelumMenikah = 'Belum Menikah';
    case Menikah = 'Menikah';
    case Cerai = 'Cerai';
}

