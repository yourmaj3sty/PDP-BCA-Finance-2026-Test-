<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\ApplicationLog;
use App\Models\CreditApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CreditApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    private User $marketing;

    private User $atasan;

    private User $backoffice;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->sales = User::factory()->role(UserRole::SalesDealer)->create([
            'email' => 'sales@jkl.co.id',
            'username' => 'sales_dealer',
            'password' => Hash::make('password123'),
        ]);

        $this->marketing = User::factory()->role(UserRole::Marketing)->create([
            'email' => 'marketing@jkl.co.id',
            'username' => 'marketing',
            'password' => Hash::make('password123'),
        ]);

        $this->atasan = User::factory()->role(UserRole::AtasanMarketing)->create([
            'email' => 'atasan@jkl.co.id',
            'username' => 'atasan_marketing',
            'password' => Hash::make('password123'),
        ]);

        $this->backoffice = User::factory()->role(UserRole::AdminBackoffice)->create([
            'email' => 'backoffice@jkl.co.id',
            'username' => 'admin_backoffice',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_user_can_login_with_email_or_username_and_fetch_profile(): void
    {
        $response = $this->postJson('/api/login', [
            'login' => 'sales_dealer',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.role', 'sales_dealer');

        $token = $response->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'sales@jkl.co.id');
    }

    public function test_invalid_login_returns_401(): void
    {
        $this->postJson('/api/login', [
            'login' => 'sales@jkl.co.id',
            'password' => 'salah-password',
        ])->assertStatus(401);
    }

    public function test_end_to_end_credit_lifecycle_with_rejection_revision_and_disbursement(): void
    {
        // 1. Sales Dealer membuat pengajuan awal (DRAFT_DEALER)
        Sanctum::actingAs($this->sales);

        $createResponse = $this->post('/api/applications', [
            'customer_name' => 'Ahmad Fauzi',
            'customer_nik' => '3174011005900001',
            'customer_dob' => '1990-05-10',
            'marital_status' => 'Menikah',
            'spouse_name' => 'Dewi Lestari',
            'spouse_nik' => '3174015008920002',
            'dealer_name' => 'Dealer Nusantara Motor',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Avanza',
            'vehicle_type' => '1.5 G CVT',
            'vehicle_color' => 'Putih',
            'vehicle_price' => 270_000_000,
            'ktp' => UploadedFile::fake()->create('ktp.pdf', 120, 'application/pdf'),
            'spk' => UploadedFile::fake()->create('spk.pdf', 150, 'application/pdf'),
            'bukti_bayar' => UploadedFile::fake()->create('bukti.jpg', 200, 'image/jpeg'),
        ], ['Accept' => 'application/json']);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.status', ApplicationStatus::DraftDealer->value);

        $appId = $createResponse->json('data.id');

        // 2. Marketing mencoba submit sebelum melengkapi data pinjaman & KK -> harus ditolak (422)
        Sanctum::actingAs($this->marketing);

        $this->postJson("/api/applications/{$appId}/submit")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['insurance_type', 'down_payment', 'tenor_months', 'kk']);

        // 3. Marketing melengkapi data pinjaman dan mengunggah Kartu Keluarga
        $updateResponse = $this->post("/api/applications/{$appId}", [
            '_method' => 'PUT',
            'customer_name' => 'Ahmad Fauzi',
            'customer_nik' => '3174011005900001',
            'customer_dob' => '1990-05-10',
            'marital_status' => 'Menikah',
            'spouse_name' => 'Dewi Lestari',
            'spouse_nik' => '3174015008920002',
            'dealer_name' => 'Dealer Nusantara Motor',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Avanza',
            'vehicle_type' => '1.5 G CVT',
            'vehicle_color' => 'Putih',
            'vehicle_price' => 270_000_000,
            'insurance_type' => 'All Risk',
            'down_payment' => 70_000_000,
            'tenor_months' => 36,
            'kk' => UploadedFile::fake()->create('kk.pdf', 180, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $updateResponse->assertOk();
        $this->assertGreaterThan(0, (int) $updateResponse->json('data.monthly_installment'));

        // 4. Marketing mengirim pengajuan ke Atasan Marketing (SUBMITTED)
        $this->postJson("/api/applications/{$appId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', ApplicationStatus::Submitted->value);

        // 5. Atasan Marketing menolak tanpa catatan -> 422, dengan catatan -> REJECTED
        Sanctum::actingAs($this->atasan);

        $this->postJson("/api/applications/{$appId}/reject", ['notes' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);

        $this->postJson("/api/applications/{$appId}/reject", [
            'notes' => 'Mohon sesuaikan uang muka minimal 80 juta sesuai ketentuan tenor.',
        ])->assertOk()
            ->assertJsonPath('data.status', ApplicationStatus::Rejected->value);

        // 6. Marketing merevisi DP dan mengirim ulang (RESUBMIT -> SUBMITTED)
        Sanctum::actingAs($this->marketing);

        $this->putJson("/api/applications/{$appId}", [
            'customer_name' => 'Ahmad Fauzi',
            'customer_nik' => '3174011005900001',
            'customer_dob' => '1990-05-10',
            'marital_status' => 'Menikah',
            'spouse_name' => 'Dewi Lestari',
            'spouse_nik' => '3174015008920002',
            'dealer_name' => 'Dealer Nusantara Motor',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Avanza',
            'vehicle_type' => '1.5 G CVT',
            'vehicle_color' => 'Putih',
            'vehicle_price' => 270_000_000,
            'insurance_type' => 'All Risk',
            'down_payment' => 80_000_000,
            'tenor_months' => 36,
        ])->assertOk();

        $this->postJson("/api/applications/{$appId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', ApplicationStatus::Submitted->value);

        // 7. Atasan Marketing menyetujui pengajuan (APPROVED)
        Sanctum::actingAs($this->atasan);

        $approveResponse = $this->postJson("/api/applications/{$appId}/approve", [
            'notes' => 'Disetujui setelah penyesuaian DP.',
        ]);

        $approveResponse->assertOk()
            ->assertJsonPath('data.status', ApplicationStatus::Approved->value);

        $signToken = $approveResponse->json('data.contract_sign_token');
        $this->assertNotEmpty($signToken);

        // 8. Admin Backoffice dapat mengambil data Kontrak dan PO otomatis
        Sanctum::actingAs($this->backoffice);

        $this->getJson("/api/applications/{$appId}/contract")
            ->assertOk()
            ->assertJsonPath('data.document_kind', 'contract');

        $this->getJson("/api/applications/{$appId}/po")
            ->assertOk()
            ->assertJsonPath('data.document_kind', 'po');

        // Pencairan ditolak jika belum ditandatangani (status masih APPROVED)
        $this->postJson("/api/applications/{$appId}/disburse", [
            'disbursement_ref' => 'TRF-001',
        ])->assertStatus(403);

        // 9. Konsumen mengakses halaman tanda tangan berbasis token unik tanpa login -> SIGNED
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/public/contracts/{$signToken}")
            ->assertOk()
            ->assertJsonPath('data.customer_name', 'Ahmad Fauzi');

        $pngBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        );

        $signResponse = $this->post("/api/public/contracts/{$signToken}/sign", [
            'agreed' => '1',
            'signature_image' => UploadedFile::fake()->createWithContent('ttd.png', $pngBytes),
        ], ['Accept' => 'application/json']);

        $signResponse->assertOk()
            ->assertJsonPath('data.status', ApplicationStatus::Signed->value);

        // 10. Admin Backoffice mengeksekusi pencairan dana -> DISBURSED
        Sanctum::actingAs($this->backoffice);

        $this->postJson("/api/applications/{$appId}/disburse", [
            'disbursement_ref' => 'TRF-JKL-20261126-099',
            'notes' => 'Transfer pelunasan selesai.',
        ])->assertOk()
            ->assertJsonPath('data.status', ApplicationStatus::Disbursed->value)
            ->assertJsonPath('data.disbursement_ref', 'TRF-JKL-20261126-099');

        // Pastikan seluruh jejak audit tercatat dengan lengkap
        $detail = $this->getJson("/api/applications/{$appId}")->assertOk();
        $this->assertGreaterThanOrEqual(7, count($detail->json('data.logs')));
    }

    public function test_validates_spouse_fields_and_down_payment_and_role_restrictions(): void
    {
        Sanctum::actingAs($this->sales);

        // Menikah tanpa data pasangan & DP >= OTR harus gagal validasi
        $this->post('/api/applications', [
            'customer_name' => 'Bambang',
            'customer_nik' => '12345', // bukan 16 digit
            'customer_dob' => '1991-01-01',
            'marital_status' => 'Menikah',
            'dealer_name' => 'Dealer A',
            'vehicle_brand' => 'Honda',
            'vehicle_model' => 'Brio',
            'vehicle_type' => 'RS',
            'vehicle_color' => 'Merah',
            'vehicle_price' => 200_000_000,
            'insurance_type' => 'TLO',
            'down_payment' => 210_000_000,
            'tenor_months' => 24,
            'ktp' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
            'spk' => UploadedFile::fake()->create('spk.pdf', 100, 'application/pdf'),
            'bukti_bayar' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_nik', 'spouse_name', 'spouse_nik', 'down_payment']);

        // Marketing tidak boleh membuat pengajuan baru (khusus Sales Dealer)
        Sanctum::actingAs($this->marketing);
        $this->postJson('/api/applications', [])->assertStatus(403);

        // Sales Dealer lain tidak bisa mengakses pengajuan milik Sales lain (IDOR protection)
        $otherSales = User::factory()->role(UserRole::SalesDealer)->create();
        $app = CreditApplication::factory()->create(['created_by' => $this->sales->id]);

        Sanctum::actingAs($otherSales);
        $this->getJson("/api/applications/{$app->id}")->assertStatus(403);
    }

    public function test_dashboard_filters_and_search_work_correctly(): void
    {
        CreditApplication::factory()->create([
            'application_number' => 'JKL-202611-7777',
            'customer_name' => 'Surya Pranata',
            'status' => ApplicationStatus::Submitted,
            'created_by' => $this->sales->id,
        ]);

        CreditApplication::factory()->create([
            'application_number' => 'JKL-202611-8888',
            'customer_name' => 'Maya Kartika',
            'status' => ApplicationStatus::Disbursed,
            'created_by' => $this->sales->id,
        ]);

        Sanctum::actingAs($this->atasan);

        $this->getJson('/api/applications?status=SUBMITTED&search=Surya')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.application_number', 'JKL-202611-7777')
            ->assertJsonPath('summary.waiting_review', 1)
            ->assertJsonPath('summary.completed', 1);
    }

    public function test_upload_rejects_oversized_or_disallowed_file_types(): void
    {
        Sanctum::actingAs($this->sales);

        $this->post('/api/applications', [
            'customer_name' => 'Test Validasi File',
            'customer_nik' => '3174011005900099',
            'customer_dob' => '1990-05-10',
            'marital_status' => 'Belum Menikah',
            'dealer_name' => 'Dealer Nusantara Motor',
            'vehicle_brand' => 'Honda',
            'vehicle_model' => 'Brio',
            'vehicle_type' => 'Satya E',
            'vehicle_color' => 'Kuning',
            'vehicle_price' => 190_000_000,
            'ktp' => UploadedFile::fake()->create('ktp.exe', 100, 'application/x-msdownload'),
            'spk' => UploadedFile::fake()->create('spk.pdf', 2500, 'application/pdf'),
            'bukti_bayar' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ktp', 'spk']);
    }

    public function test_marketing_can_mark_po_sent_and_upload_signed_contract_pdf(): void
    {
        $app = CreditApplication::factory()->create([
            'status' => ApplicationStatus::Approved,
            'contract_sign_token' => 'token-uji-po-dan-kontrak-ttd-123456789012345678',
            'created_by' => $this->sales->id,
            'approved_by' => $this->atasan->id,
        ]);

        Sanctum::actingAs($this->marketing);

        $this->postJson("/api/applications/{$app->id}/send-po")
            ->assertOk();

        $this->assertNotNull($app->fresh()->po_sent_at);

        $this->post("/api/applications/{$app->id}/sign", [
            'agreed' => '1',
            'kontrak_ttd' => UploadedFile::fake()->create('kontrak-ttd.pdf', 300, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', ApplicationStatus::Signed->value);
    }

    public function test_lowering_price_below_stored_down_payment_returns_validation_error(): void
    {
        // Draft dari factory punya DP Rp 50 juta. Sales menurunkan harga tanpa mengirim ulang DP.
        $app = CreditApplication::factory()->create(['created_by' => $this->sales->id]);

        Sanctum::actingAs($this->sales);

        $this->putJson("/api/applications/{$app->id}", [
            'customer_name' => $app->customer_name,
            'customer_nik' => $app->customer_nik,
            'customer_dob' => '1992-05-14',
            'marital_status' => 'Belum Menikah',
            'dealer_name' => $app->dealer_name,
            'vehicle_brand' => $app->vehicle_brand,
            'vehicle_model' => $app->vehicle_model,
            'vehicle_type' => $app->vehicle_type,
            'vehicle_color' => $app->vehicle_color,
            'vehicle_price' => 40_000_000,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['down_payment']);
    }

    public function test_contract_date_stays_at_approval_date_after_later_updates(): void
    {
        $app = CreditApplication::factory()->create([
            'status' => ApplicationStatus::Approved,
            'created_by' => $this->sales->id,
            'approved_by' => $this->atasan->id,
        ]);

        ApplicationLog::create([
            'credit_application_id' => $app->id,
            'user_id' => $this->atasan->id,
            'action' => 'APPROVE',
            'from_status' => ApplicationStatus::Submitted->value,
            'to_status' => ApplicationStatus::Approved->value,
            'created_at' => '2026-01-10 09:00:00',
        ]);

        // Aksi sesudahnya (misalnya kirim PO) ikut mengubah updated_at.
        $this->travelTo('2026-02-20 10:00:00');
        Sanctum::actingAs($this->marketing);
        $this->postJson("/api/applications/{$app->id}/send-po")->assertOk();

        $issuedAt = $this->getJson("/api/applications/{$app->id}/contract")
            ->assertOk()
            ->json('data.issued_at');

        $this->assertStringStartsWith('2026-01-10', $issuedAt);
    }
}
