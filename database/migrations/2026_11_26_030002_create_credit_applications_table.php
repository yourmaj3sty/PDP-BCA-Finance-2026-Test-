<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('application_number', 40)->unique();
            $table->string('status', 32)->default(ApplicationStatus::DraftDealer->value)->index();

            // Data Konsumen & Pasangan
            $table->string('customer_name', 150);
            $table->string('customer_nik', 16)->index();
            $table->date('customer_dob');
            $table->string('marital_status', 32);
            $table->string('spouse_name', 150)->nullable();
            $table->string('spouse_nik', 16)->nullable();

            // Data Kendaraan
            $table->string('dealer_name', 150);
            $table->string('vehicle_brand', 80);
            $table->string('vehicle_model', 100);
            $table->string('vehicle_type', 100);
            $table->string('vehicle_color', 60);
            $table->unsignedBigInteger('vehicle_price');

            // Data Pinjaman & Kalkulasi (dapat dilengkapi pada tahap Marketing)
            $table->string('insurance_type', 32)->nullable();
            $table->unsignedBigInteger('insurance_premium')->default(0);
            $table->unsignedBigInteger('down_payment')->nullable();
            $table->unsignedSmallInteger('tenor_months')->nullable();
            $table->decimal('interest_rate', 5, 2)->default(6.00);
            $table->unsignedBigInteger('monthly_installment')->nullable();

            // Kontrak, Tanda Tangan Digital, PO & Pencairan
            $table->string('contract_sign_token', 64)->nullable()->unique();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamp('po_sent_at')->nullable();
            $table->string('disbursement_ref', 100)->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->text('rejection_note')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_applications');
    }
};

