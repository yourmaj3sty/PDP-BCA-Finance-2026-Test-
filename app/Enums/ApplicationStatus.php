<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationStatus: string
{
    case DraftDealer = 'DRAFT_DEALER';
    case Submitted = 'SUBMITTED';
    case Rejected = 'REJECTED';
    case Approved = 'APPROVED';
    case Signed = 'SIGNED';
    case Disbursed = 'DISBURSED';

    public function label(): string
    {
        return match ($this) {
            self::DraftDealer => 'Draft Dealer',
            self::Submitted => 'Menunggu Review',
            self::Rejected => 'Ditolak',
            self::Approved => 'Disetujui',
            self::Signed => 'Kontrak Ditandatangani',
            self::Disbursed => 'Dana Dicairkan',
        };
    }

    /**
     * Data masih boleh diubah selama belum dikirim ke Atasan, atau sedang dikembalikan untuk revisi.
     */
    public function isEditable(): bool
    {
        return $this === self::DraftDealer || $this === self::Rejected;
    }

    public function canPrintDocuments(): bool
    {
        return in_array($this, [self::Approved, self::Signed, self::Disbursed], true);
    }
}

