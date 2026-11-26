<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Enums\InsuranceType;
use App\Enums\MaritalStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\ApplicationLog;
use App\Models\CreditApplication;
use App\Models\User;
use App\Services\LoanCalculatorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password123');

        $sales = User::updateOrCreate(
            ['email' => 'sales@jkl.co.id'],
            [
                'name' => 'Rizal Pratama',
                'username' => 'sales_dealer',
                'role' => UserRole::SalesDealer,
                'password' => $password,
            ],
        );

        $marketing = User::updateOrCreate(
            ['email' => 'marketing@jkl.co.id'],
            [
                'name' => 'Siti Rahmawati',
                'username' => 'marketing',
                'role' => UserRole::Marketing,
                'password' => $password,
            ],
        );

        $atasan = User::updateOrCreate(
            ['email' => 'atasan@jkl.co.id'],
            [
                'name' => 'Hendra Wijaya',
                'username' => 'atasan_marketing',
                'role' => UserRole::AtasanMarketing,
                'password' => $password,
            ],
        );

        $backoffice = User::updateOrCreate(
            ['email' => 'backoffice@jkl.co.id'],
            [
                'name' => 'Dewi Kusuma',
                'username' => 'admin_backoffice',
                'role' => UserRole::AdminBackoffice,
                'password' => $password,
            ],
        );

        if (CreditApplication::query()->exists()) {
            return;
        }

        $calculator = app(LoanCalculatorService::class);
        $sampleSignaturePath = $this->createSampleSignaturePng('samples/ttd-andi-saputra.png');

        $samples = [
            [
                'application_number' => 'JKL-202611-0001',
                'status' => ApplicationStatus::Disbursed,
                'customer_name' => 'Andi Saputra',
                'customer_nik' => '3174091504890003',
                'customer_dob' => '1989-04-15',
                'marital_status' => MaritalStatus::Menikah,
                'spouse_name' => 'Nadia Permata',
                'spouse_nik' => '3174095808910002',
                'dealer_name' => 'Dealer Nusantara Motor Jakarta',
                'vehicle_brand' => 'Toyota',
                'vehicle_model' => 'Avanza',
                'vehicle_type' => '1.5 G CVT',
                'vehicle_color' => 'Hitam Metalik',
                'vehicle_price' => 276_000_000,
                'insurance_type' => InsuranceType::AllRisk,
                'down_payment' => 56_000_000,
                'tenor_months' => 48,
                'created_by' => $sales->id,
                'approved_by' => $atasan->id,
                'contract_sign_token' => Str::random(48),
                'signed_at' => now()->subDays(2),
                'signature_path' => $sampleSignaturePath,
                'po_sent_at' => now()->subDays(2),
                'disbursement_ref' => 'TRF-JKL-20261124-081',
                'disbursed_at' => now()->subDay(),
                'docs' => [DocumentType::Ktp, DocumentType::Spk, DocumentType::BuktiBayar, DocumentType::Kk],
                'logs' => [
                    ['user_id' => $sales->id, 'action' => 'CREATE_DRAFT', 'from' => null, 'to' => ApplicationStatus::DraftDealer, 'notes' => 'Lead awal diinput dari SPK Dealer Nusantara Motor.', 'hours_ago' => 72],
                    ['user_id' => $marketing->id, 'action' => 'SUBMIT', 'from' => ApplicationStatus::DraftDealer, 'to' => ApplicationStatus::Submitted, 'notes' => 'Data pasangan, skema pembiayaan, dan KK telah dilengkapi.', 'hours_ago' => 64],
                    ['user_id' => $atasan->id, 'action' => 'APPROVE', 'from' => ApplicationStatus::Submitted, 'to' => ApplicationStatus::Approved, 'notes' => 'Profil konsumen memenuhi syarat kredit.', 'hours_ago' => 56],
                    ['user_id' => null, 'action' => 'SIGN_CONTRACT', 'from' => ApplicationStatus::Approved, 'to' => ApplicationStatus::Signed, 'notes' => 'Kontrak ditandatangani secara digital oleh konsumen.', 'hours_ago' => 48],
                    ['user_id' => $backoffice->id, 'action' => 'DISBURSE', 'from' => ApplicationStatus::Signed, 'to' => ApplicationStatus::Disbursed, 'notes' => 'Dana dicairkan ke dealer dengan nomor referensi TRF-JKL-20261124-081.', 'hours_ago' => 24],
                ],
            ],
            [
                'application_number' => 'JKL-202611-0002',
                'status' => ApplicationStatus::Signed,
                'customer_name' => 'Rina Marlina',
                'customer_nik' => '3273024709930004',
                'customer_dob' => '1993-09-07',
                'marital_status' => MaritalStatus::BelumMenikah,
                'spouse_name' => null,
                'spouse_nik' => null,
                'dealer_name' => 'Honda Fatmawati Motors',
                'vehicle_brand' => 'Honda',
                'vehicle_model' => 'HR-V',
                'vehicle_type' => 'SE CVT',
                'vehicle_color' => 'Putih Platinum',
                'vehicle_price' => 416_000_000,
                'insurance_type' => InsuranceType::Kombinasi,
                'down_payment' => 100_000_000,
                'tenor_months' => 36,
                'created_by' => $sales->id,
                'approved_by' => $atasan->id,
                'contract_sign_token' => Str::random(48),
                'signed_at' => now()->subHours(5),
                'signature_path' => $sampleSignaturePath,
                'po_sent_at' => now()->subHours(6),
                'docs' => [DocumentType::Ktp, DocumentType::Spk, DocumentType::BuktiBayar, DocumentType::Kk],
                'logs' => [
                    ['user_id' => $sales->id, 'action' => 'CREATE_DRAFT', 'from' => null, 'to' => ApplicationStatus::DraftDealer, 'notes' => 'Pengajuan awal diinput oleh Sales Dealer.', 'hours_ago' => 30],
                    ['user_id' => $marketing->id, 'action' => 'SUBMIT', 'from' => ApplicationStatus::DraftDealer, 'to' => ApplicationStatus::Submitted, 'notes' => 'Kelengkapan dokumen dan skema asuransi Kombinasi diverifikasi.', 'hours_ago' => 22],
                    ['user_id' => $atasan->id, 'action' => 'APPROVE', 'from' => ApplicationStatus::Submitted, 'to' => ApplicationStatus::Approved, 'notes' => 'Disetujui untuk tenor 36 bulan.', 'hours_ago' => 14],
                    ['user_id' => null, 'action' => 'SIGN_CONTRACT', 'from' => ApplicationStatus::Approved, 'to' => ApplicationStatus::Signed, 'notes' => 'Konsumen menyetujui dan menandatangani kontrak digital.', 'hours_ago' => 5],
                ],
            ],
            [
                'application_number' => 'JKL-202611-0003',
                'status' => ApplicationStatus::Approved,
                'customer_name' => 'Budi Santoso',
                'customer_nik' => '3175042101860007',
                'customer_dob' => '1986-01-21',
                'marital_status' => MaritalStatus::Menikah,
                'spouse_name' => 'Sri Wahyuni',
                'spouse_nik' => '3175046305880005',
                'dealer_name' => 'Mitsubishi Pondok Indah',
                'vehicle_brand' => 'Mitsubishi',
                'vehicle_model' => 'Xpander',
                'vehicle_type' => 'Ultimate CVT',
                'vehicle_color' => 'Abu-abu Graphite',
                'vehicle_price' => 324_500_000,
                'insurance_type' => InsuranceType::AllRisk,
                'down_payment' => 74_500_000,
                'tenor_months' => 36,
                'created_by' => $sales->id,
                'approved_by' => $atasan->id,
                'contract_sign_token' => Str::random(48),
                'docs' => [DocumentType::Ktp, DocumentType::Spk, DocumentType::BuktiBayar, DocumentType::Kk],
                'logs' => [
                    ['user_id' => $sales->id, 'action' => 'CREATE_DRAFT', 'from' => null, 'to' => ApplicationStatus::DraftDealer, 'notes' => 'Deal unit Xpander Ultimate dari SPK dealer.', 'hours_ago' => 18],
                    ['user_id' => $marketing->id, 'action' => 'SUBMIT', 'from' => ApplicationStatus::DraftDealer, 'to' => ApplicationStatus::Submitted, 'notes' => 'Dokumen KK diunggah dan dikirim untuk approval.', 'hours_ago' => 12],
                    ['user_id' => $atasan->id, 'action' => 'APPROVE', 'from' => ApplicationStatus::Submitted, 'to' => ApplicationStatus::Approved, 'notes' => 'Disetujui. Silakan terbitkan Kontrak & PO.', 'hours_ago' => 4],
                ],
            ],
            [
                'application_number' => 'JKL-202611-0004',
                'status' => ApplicationStatus::Submitted,
                'customer_name' => 'Fajar Nugroho',
                'customer_nik' => '3674011206950001',
                'customer_dob' => '1995-06-12',
                'marital_status' => MaritalStatus::BelumMenikah,
                'spouse_name' => null,
                'spouse_nik' => null,
                'dealer_name' => 'Yamaha Flagship Shop Cempaka Putih',
                'vehicle_brand' => 'Yamaha',
                'vehicle_model' => 'NMAX Turbo',
                'vehicle_type' => 'Tech MAX',
                'vehicle_color' => 'Magma Black',
                'vehicle_price' => 43_250_000,
                'insurance_type' => InsuranceType::Tlo,
                'down_payment' => 10_000_000,
                'tenor_months' => 24,
                'created_by' => $sales->id,
                'approved_by' => null,
                'docs' => [DocumentType::Ktp, DocumentType::Spk, DocumentType::BuktiBayar, DocumentType::Kk],
                'logs' => [
                    ['user_id' => $sales->id, 'action' => 'CREATE_DRAFT', 'from' => null, 'to' => ApplicationStatus::DraftDealer, 'notes' => 'Data awal pengajuan motor Yamaha NMAX Turbo.', 'hours_ago' => 10],
                    ['user_id' => $marketing->id, 'action' => 'SUBMIT', 'from' => ApplicationStatus::DraftDealer, 'to' => ApplicationStatus::Submitted, 'notes' => 'Pengajuan siap ditinjau oleh Atasan Marketing.', 'hours_ago' => 3],
                ],
            ],
            [
                'application_number' => 'JKL-202611-0005',
                'status' => ApplicationStatus::DraftDealer,
                'customer_name' => 'Dian Kartika',
                'customer_nik' => '3171035402900006',
                'customer_dob' => '1990-02-14',
                'marital_status' => MaritalStatus::Cerai,
                'spouse_name' => null,
                'spouse_nik' => null,
                'dealer_name' => 'Suzuki Sejahtera Buana Trada',
                'vehicle_brand' => 'Suzuki',
                'vehicle_model' => 'XL7 Hybrid',
                'vehicle_type' => 'Beta AT',
                'vehicle_color' => 'Savanna Ivory',
                'vehicle_price' => 294_000_000,
                'insurance_type' => InsuranceType::Kombinasi,
                'down_payment' => 60_000_000,
                'tenor_months' => 48,
                'created_by' => $sales->id,
                'approved_by' => null,
                'docs' => [DocumentType::Ktp, DocumentType::Spk, DocumentType::BuktiBayar],
                'logs' => [
                    ['user_id' => $sales->id, 'action' => 'CREATE_DRAFT', 'from' => null, 'to' => ApplicationStatus::DraftDealer, 'notes' => 'Dokumen awal KTP, SPK, dan Tanda Jadi sudah diunggah. Menunggu kelengkapan KK oleh Marketing.', 'hours_ago' => 1],
                ],
            ],
        ];

        foreach ($samples as $sample) {
            $calc = $calculator->calculate(
                vehiclePrice: $sample['vehicle_price'],
                downPayment: $sample['down_payment'],
                insuranceType: $sample['insurance_type'],
                tenorMonths: $sample['tenor_months'],
            );

            $application = CreditApplication::create([
                'application_number' => $sample['application_number'],
                'status' => $sample['status'],
                'customer_name' => $sample['customer_name'],
                'customer_nik' => $sample['customer_nik'],
                'customer_dob' => $sample['customer_dob'],
                'marital_status' => $sample['marital_status'],
                'spouse_name' => $sample['spouse_name'],
                'spouse_nik' => $sample['spouse_nik'],
                'dealer_name' => $sample['dealer_name'],
                'vehicle_brand' => $sample['vehicle_brand'],
                'vehicle_model' => $sample['vehicle_model'],
                'vehicle_type' => $sample['vehicle_type'],
                'vehicle_color' => $sample['vehicle_color'],
                'vehicle_price' => $sample['vehicle_price'],
                'insurance_type' => $sample['insurance_type'],
                'insurance_premium' => $calc['insurance_premium'],
                'down_payment' => $sample['down_payment'],
                'tenor_months' => $sample['tenor_months'],
                'interest_rate' => LoanCalculatorService::ANNUAL_FLAT_INTEREST_RATE,
                'monthly_installment' => $calc['monthly_installment'],
                'contract_sign_token' => $sample['contract_sign_token'] ?? null,
                'signed_at' => $sample['signed_at'] ?? null,
                'signature_path' => $sample['signature_path'] ?? null,
                'po_sent_at' => $sample['po_sent_at'] ?? null,
                'disbursement_ref' => $sample['disbursement_ref'] ?? null,
                'disbursed_at' => $sample['disbursed_at'] ?? null,
                'created_by' => $sample['created_by'],
                'approved_by' => $sample['approved_by'],
            ]);

            foreach ($sample['docs'] as $docType) {
                $relativePath = "samples/{$application->id}-{$docType->value}.pdf";
                $this->createSamplePdf(
                    path: $relativePath,
                    title: "{$docType->label()} - {$application->application_number}",
                    subtitle: "Pemohon: {$application->customer_name} | Unit: {$application->vehicle_brand} {$application->vehicle_model}",
                );

                ApplicationDocument::create([
                    'credit_application_id' => $application->id,
                    'document_type' => $docType,
                    'file_path' => $relativePath,
                    'original_name' => "{$docType->value}-{$application->application_number}.pdf",
                    'uploaded_by' => $docType === DocumentType::Kk ? $marketing->id : $sales->id,
                ]);
            }

            foreach ($sample['logs'] as $entry) {
                ApplicationLog::create([
                    'credit_application_id' => $application->id,
                    'user_id' => $entry['user_id'],
                    'action' => $entry['action'],
                    'from_status' => $entry['from']?->value,
                    'to_status' => $entry['to']->value,
                    'notes' => $entry['notes'],
                    'created_at' => now()->subHours($entry['hours_ago']),
                ]);
            }
        }
    }

    private function createSamplePdf(string $path, string $title, string $subtitle): void
    {
        $esc = static fn (string $text): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        $stream = "BT\n/F1 14 Tf\n50 750 Td\n(".$esc('PT. JKL MULTIFINANCE INDONESIA - LAMPIRAN DOKUMEN').") Tj\n"
            ."0 -28 Td\n/F1 12 Tf\n(".$esc($title).") Tj\n"
            ."0 -20 Td\n/F1 10 Tf\n(".$esc($subtitle).") Tj\n"
            ."0 -20 Td\n(".$esc('Dokumen arsip terdigitalisasi untuk verifikasi internal pengajuan kredit.').") Tj\nET";

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $idx => $offset) {
            if ($idx === 0) {
                continue;
            }
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        Storage::disk('public')->put($path, $pdf);
    }

    /**
     * Membuat berkas gambar PNG sederhana berisi guratan tanda tangan tanpa ekstensi GD.
     */
    private function createSampleSignaturePng(string $path): string
    {
        $width = 240;
        $height = 80;
        $pixels = array_fill(0, $height, array_fill(0, $width, [255, 255, 255]));

        for ($x = 24; $x < 216; $x++) {
            $y = (int) round(42 + 14 * sin($x / 14) - 6 * cos($x / 7));
            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dx = -1; $dx <= 1; $dx++) {
                    $py = $y + $dy;
                    $px = $x + $dx;
                    if ($py >= 0 && $py < $height && $px >= 0 && $px < $width) {
                        $pixels[$py][$px] = [30, 58, 138];
                    }
                }
            }
        }

        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00";
            for ($x = 0; $x < $width; $x++) {
                $rgb = $pixels[$y][$x];
                $raw .= chr($rgb[0]).chr($rgb[1]).chr($rgb[2]);
            }
        }

        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };

        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $idat = gzcompress($raw, 6);

        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', $ihdr)
            .$chunk('IDAT', $idat !== false ? $idat : '')
            .$chunk('IEND', '');

        Storage::disk('public')->put($path, $png);

        return $path;
    }
}
