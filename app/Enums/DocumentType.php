<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentType: string
{
    case Ktp = 'ktp';
    case Spk = 'spk';
    case BuktiBayar = 'bukti_bayar';
    case Kk = 'kk';
    case KontrakTtd = 'kontrak_ttd';

    /**
     * Dokumen yang diunggah lewat form pengajuan. Keempatnya wajib ada sebelum pengajuan dikirim.
     *
     * @return list<self>
     */
    public static function applicationForm(): array
    {
        return [self::Ktp, self::Spk, self::BuktiBayar, self::Kk];
    }

    public function label(): string
    {
        return match ($this) {
            self::Ktp => 'KTP Konsumen',
            self::Spk => 'Surat Pemesanan Kendaraan (SPK)',
            self::BuktiBayar => 'Bukti Bayar Tanda Jadi',
            self::Kk => 'Kartu Keluarga (KK)',
            self::KontrakTtd => 'Bukti Kontrak Ditandatangani',
        };
    }
}

