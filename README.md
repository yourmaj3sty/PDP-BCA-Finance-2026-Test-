# Sistem Digitalisasi Pengajuan Kredit Kendaraan Bermotor — PT. JKL

Aplikasi web internal PT. JKL untuk memproses pengajuan kredit kendaraan bermotor: Sales Dealer menginput data awal, Marketing melengkapi, Atasan Marketing menyetujui, sistem menerbitkan Dokumen Kontrak dan Purchase Order (PO), konsumen menandatangani kontrak secara digital, lalu Admin Backoffice mencairkan dana.

---

## Daftar Isi

1. [Latar Belakang & Tujuan Sistem](#1-latar-belakang--tujuan-sistem)
2. [Peran Pengguna & Alur Status](#2-peran-pengguna--alur-status)
3. [Arsitektur Teknis & Struktur Database](#3-arsitektur-teknis--struktur-database)
4. [Panduan Instalasi & Menjalankan Proyek](#4-panduan-instalasi--menjalankan-proyek)
5. [Akun Uji Coba & Daftar Endpoint API](#5-akun-uji-coba--daftar-endpoint-api)
6. [Panduan Penyusunan Pitch Deck Presentasi](#6-panduan-penyusunan-pitch-deck-presentasi)
7. [Catatan Sebelum Dipakai di Produksi](#7-catatan-sebelum-dipakai-di-produksi)

---

## 1. Latar Belakang & Tujuan Sistem

### Masalah pada Proses Manual Sebelumnya
- Pertukaran dokumen fisik (fotokopi KTP, SPK, Kartu Keluarga, dan formulir cetak) antar divisi rawan tercecer dan memperlambat proses verifikasi.
- Terjadi pengetikan ulang data yang sama antara Sales Dealer, Marketing, dan Admin Backoffice saat membuat Dokumen Kontrak dan Purchase Order (PO).
- Proses persetujuan (*approval*) dan revisi berjalan tanpa riwayat status terpusat sehingga sulit ditelusuri.
- Penandatanganan kontrak dan pengiriman dokumen hasil *scan* membutuhkan pertemuan tatap muka sebelum pencairan dana dapat dieksekusi.

### Solusi yang Diterapkan
- **Satu basis data**: Data konsumen, pasangan, kendaraan, skema pembiayaan, dan dokumen digital diinput sekali lalu dipakai ulang di setiap tahap.
- **Kalkulasi kredit otomatis**: Pokok pembiayaan, premi asuransi (All Risk, Kombinasi, TLO), bunga flat 6% per tahun, dan angsuran dihitung langsung di form, lalu dihitung ulang di backend sebelum disimpan.
- **Kontrak & PO siap cetak**: Setelah pengajuan disetujui, halaman cetak Dokumen Kontrak dan PO terisi dari database (`window.print()` / Simpan PDF).
- **Tanda tangan tanpa akun konsumen**: Konsumen membuka tautan bertoken, meninjau ringkasan kontrak, lalu menandatangani di kanvas HTML5 atau mengunggah bukti tanda tangan.
- **Audit trail**: Setiap perubahan data dan perpindahan status tercatat dengan nama pengguna, peran, catatan, dan waktu.

---

## 2. Peran Pengguna & Alur Status

### Empat Peran Utama

| Peran (`role`) | Tanggung Jawab dalam Sistem |
|---|---|
| **Sales Dealer** (`sales_dealer`) | Menginput data awal konsumen dan unit kendaraan setelah deal SPK, serta mengunggah dokumen awal (KTP, SPK, Bukti Bayar Tanda Jadi). |
| **Marketing** (`marketing`) | Melengkapi data pasangan (jika Menikah), skema asuransi dan tenor kredit, mengunggah Kartu Keluarga (KK), mengirim pengajuan untuk disetujui, serta memantau status TTD konsumen dan pengiriman PO ke dealer. |
| **Atasan Marketing** (`atasan_marketing`) | Meninjau seluruh rincian konsumen, simulasi pinjaman, dan dokumen lampiran dalam satu layar, lalu melakukan **Approve** atau **Reject** (wajib disertai catatan). |
| **Admin Backoffice** (`admin_backoffice`) | Mencetak Dokumen Kontrak dan PO otomatis, memverifikasi tanda tangan kontrak digital konsumen, menginput nomor referensi transfer, dan mengeksekusi **Pencairan Dana**. |

### Siklus Status Pengajuan (*State Machine*)

```text
[DRAFT_DEALER] ──(Marketing lengkapi & kirim)──> [SUBMITTED]
                                                   │     ▲
                     ┌──(Atasan Reject + catatan)──┘     │
                     ▼                                   │
                 [REJECTED] ──(Marketing revisi & kirim)─┘
                                                         │
                     ┌──(Atasan Approve)─────────────────┘
                     ▼
                 [APPROVED] ──(TTD Digital Konsumen & PO ke Dealer)──> [SIGNED] ──(Admin Cairkan Dana)──> [DISBURSED]
```

---

## 3. Arsitektur Teknis & Struktur Database

### Stack Teknologi
- **Backend**: PHP 8.3+ dengan Laravel 13 (Controller, Form Request, Eloquent, Policy, Sanctum token auth, penyimpanan file di disk `public`).
- **Frontend**: Vue 3 (`<script setup>`), Vue Router, Axios, dan Tailwind CSS 4, di-bundle Vite (`laravel-vite-plugin`) dalam repositori yang sama.
- **Database**: SQLite secara default di `.env.example`; MySQL 8 / MariaDB bisa dipakai dengan mengganti konfigurasi `DB_*`.

Logika bisnis ada di `app/Services/CreditApplicationService.php` (transisi status, upload dokumen, audit log) dan `app/Services/LoanCalculatorService.php` (rumus angsuran). Siapa boleh melakukan apa diatur di `app/Policies/CreditApplicationPolicy.php`. Rumus angsuran juga ada di `resources/js/utils/loanCalculator.js` untuk simulasi di form; kalau tarif asuransi atau bunga berubah, ubah keduanya.

### Empat Tabel Inti
1. **`users`**: Menyimpan akun internal (`id`, `name`, `username`, `email`, `role`, `password`).
2. **`credit_applications`**: Menyimpan data inti pengajuan (`application_number`, `status`), data konsumen dan pasangan (`customer_name`, `customer_nik`, `customer_dob`, `marital_status`, `spouse_name`, `spouse_nik`), data kendaraan (`dealer_name`, `vehicle_brand`, `vehicle_model`, `vehicle_type`, `vehicle_color`, `vehicle_price`), data kredit (`insurance_type`, `insurance_premium`, `down_payment`, `tenor_months`, `interest_rate`, `monthly_installment`), serta data kontrak/pencairan (`contract_sign_token`, `signed_at`, `signature_path`, `po_sent_at`, `disbursement_ref`, `disbursed_at`, `rejection_note`, `created_by`, `approved_by`).
3. **`application_documents`**: Menyimpan metadata dan jalur file dokumen (`ktp`, `spk`, `bukti_bayar`, `kk`, `kontrak_ttd`) di `storage/app/public`.
4. **`application_logs`**: Mencatat riwayat aktivitas dan perpindahan status (`user_id`, `action`, `from_status`, `to_status`, `notes`, `created_at`).

---

## 4. Panduan Instalasi & Menjalankan Proyek

### Prasyarat
- PHP >= 8.3 (dengan ekstensi `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `zip`, `zlib`)
- Composer >= 2.x
- Node.js >= 20.x dan npm
- MySQL / MariaDB (misalnya via XAMPP) atau SQLite untuk mode lokal cepat

### Langkah Instalasi

```bash
# 1. Pasang dependensi PHP dan JavaScript
composer install
npm install

# 2. Salin file konfigurasi environment dan buat application key
cp .env.example .env
php artisan key:generate

# 3. Konfigurasi database pada file .env
#    Untuk MySQL (XAMPP):
#      DB_CONNECTION=mysql
#      DB_HOST=127.0.0.1
#      DB_PORT=3306
#      DB_DATABASE=pdp_kredit
#      DB_USERNAME=root
#      DB_PASSWORD=
#    (Atau biarkan DB_CONNECTION=sqlite untuk langsung berjalan dengan database/database.sqlite)

# 4. Buat symbolic link penyimpanan dokumen publik
php artisan storage:link

# 5. Jalankan migrasi dan isi data akun serta contoh pengajuan
php artisan migrate --seed

# 6. Kompilasi aset frontend Vue + Tailwind
npm run build
```

### Menjalankan Aplikasi secara Lokal

```bash
# Jalankan server Laravel (akses di http://127.0.0.1:8000)
php artisan serve
```

Untuk mode pengembangan frontend dengan *hot-reload*:

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

### Menjalankan Pengujian Otomatis

```bash
php artisan test
```

---

## 5. Akun Uji Coba & Daftar Endpoint API

### Akun Seeder Siap Pakai
Seluruh akun demo menggunakan password: `password123`

| Peran | Email | Username | Nama Staf |
|---|---|---|---|
| Sales Dealer | `sales@jkl.co.id` | `sales_dealer` | Rizal Pratama |
| Marketing | `marketing@jkl.co.id` | `marketing` | Siti Rahmawati |
| Atasan Marketing | `atasan@jkl.co.id` | `atasan_marketing` | Hendra Wijaya |
| Admin Backoffice | `backoffice@jkl.co.id` | `admin_backoffice` | Dewi Kusuma |

### Ringkasan Endpoint REST API (`/api`)

| Method & Endpoint | Fungsi |
|---|---|
| `POST /api/login`, `POST /api/logout`, `GET /api/me` | Autentikasi akun internal dan pengecekan profil peran aktif |
| `GET /api/applications` | Daftar pengajuan kredit, ringkasan angka dashboard, filter `?status=` & `?search=` |
| `POST /api/applications` | Membuat pengajuan awal (`DRAFT_DEALER`) beserta upload dokumen awal |
| `GET /api/applications/{id}` | Detail lengkap konsumen, kendaraan, kalkulasi kredit, dokumen, dan riwayat audit |
| `PUT /api/applications/{id}` | Melengkapi atau merevisi data pengajuan dan dokumen (frontend mengirimnya sebagai `POST` + `_method=PUT` karena berisi file) |
| `POST /api/applications/{id}/submit` | Mengirim pengajuan ke Atasan Marketing (`DRAFT_DEALER` / `REJECTED` -> `SUBMITTED`) |
| `POST /api/applications/{id}/approve` | Menyetujui pengajuan (`SUBMITTED` -> `APPROVED`) |
| `POST /api/applications/{id}/reject` | Menolak pengajuan beserta catatan wajib (`SUBMITTED` -> `REJECTED`) |
| `GET /api/applications/{id}/contract` & `/po` | Data terstruktur Dokumen Kontrak dan Purchase Order siap cetak |
| `POST /api/applications/{id}/send-po` | Mencatat waktu pengiriman PO ke dealer |
| `POST /api/applications/{id}/sign` | Menyimpan tanda tangan digital internal (`APPROVED` -> `SIGNED`) |
| `GET /api/public/contracts/{token}` & `POST .../sign` | Halaman dan konfirmasi tanda tangan digital konsumen berbasis token unik tanpa login |
| `POST /api/applications/{id}/disburse` | Mencatat nomor referensi transfer dan menyelesaikan pencairan (`SIGNED` -> `DISBURSED`) |

---

## 6. Panduan Penyusunan Pitch Deck Presentasi

Kerangka *slide deck* (PowerPoint / Google Slides) untuk mempresentasikan hasil digitalisasi proses pengajuan kredit PT. JKL.

### Struktur Slide yang Direkomendasikan (8 Slide)

#### Slide 1 — Sampul (*Cover*)
- **Judul**: Digitalisasi Proses Penerimaan Pengajuan Kredit Kendaraan Bermotor — PT. JKL
- **Subjudul**: Dari Berkas Fisik ke Portal Web Berbasis Laravel & Vue 3
- **Isi**: Nama penyaji/tim, studi kasus PT. JKL, dan tanggal presentasi.

#### Slide 2 — Masalah Operasional (*Current Pain Points*)
- **Poin Utama (Before)**:
  - Ketergantungan pada berkas fisik (fotokopi KTP, SPK, KK, formulir cetak) yang menghambat koordinasi antar dealer, marketing, dan backoffice.
  - Penginputan data berulang (*double entry*) saat penyusunan Kontrak Pembiayaan dan Purchase Order (PO).
  - Tidak ada jejak status dan alasan penolakan yang terdokumentasi secara terpusat.
  - Konsumen harus hadir tatap muka untuk tanda tangan kontrak fisik serta proses *scan* manual sebelum dana dapat dicairkan.

#### Slide 3 — Solusi & Nilai Utama (*Proposed Solution*)
- **Poin Utama (After)**:
  - Portal web terpusat dengan akses berbasis peran (*role-based access control*) untuk 4 divisi utama.
  - Pengisian formulir satu kali dengan validasi otomatis (NIK 16 digit, data pasangan dinamis untuk status Menikah, validasi DP < Harga OTR, batas ukuran dokumen 2 MB).
  - Simulasi angsuran otomatis dan transparan (pokok unit, premi asuransi, bunga flat 6% per tahun, dan tenor 12–60 bulan).
  - Penerbitan Kontrak & PO siap cetak langsung dari database tanpa pengetikan ulang.
  - Tanda tangan digital konsumen melalui tautan unik tanpa memerlukan akun terpisah.

#### Slide 4 — Alur Kerja & Matriks Peran (*End-to-End Workflow*)
- Tampilkan diagram alur 6 status (`DRAFT_DEALER` -> `SUBMITTED` -> `REJECTED` / `APPROVED` -> `SIGNED` -> `DISBURSED`) berdampingan dengan pembagian tugas 4 peran:
  1. **Sales Dealer**: Input lead awal + KTP, SPK, Bukti Bayar Tanda Jadi.
  2. **Marketing**: Lengkapi data pasangan/kredit + unggah KK + kirim ke Atasan Marketing.
  3. **Atasan Marketing**: Tinjau satu layar + Approve atau Reject dengan catatan wajib.
  4. **Admin Backoffice**: Cetak Kontrak & PO, verifikasi TTD konsumen, input referensi transfer, Cairkan Dana.

#### Slide 5 — Arsitektur Teknis & Desain Database
- **Tumpukan Teknologi**: PHP Laravel (REST API, Policy RBAC, Form Request Validation, Storage) + Vue 3 SPA (Vite, Vue Router, Axios, Tailwind CSS) + MySQL.
- **Diagram 4 Tabel Utama**: `users`, `credit_applications`, `application_documents`, `application_logs` beserta relasi antar tabel.
- **Keamanan & Integritas Data**: Validasi ganda frontend-backend, kalkulasi ulang angsuran di server, proteksi akses lintas peran, serta *audit trail* setiap transisi status.

#### Slide 6 — Demo Alur Sistem & Daftar Tangkapan Layar (*Screenshots*)
Ambil tangkapan layar aplikasi saat menjalankan 1 siklus penuh dengan urutan berikut untuk dimasukkan ke slide demo:
1. **Layar Login (`LoginView`)**: Menampilkan halaman masuk sederhana dengan opsi peran.
2. **Dashboard Ringkas (`DashboardView`)**: Menampilkan 4 kartu ringkasan status (`Menunggu Review`, `Disetujui`, `Siap Cair`, `Selesai`) dan tabel pencarian pengajuan.
3. **Form Pengajuan (`ApplicationFormView`)**: Menampilkan input data konsumen (termasuk tampilan dinamis data pasangan saat memilih `Menikah`), simulasi angsuran otomatis, serta upload dokumen digital.
4. **Halaman Review Atasan Marketing (`ApplicationDetailView`)**: Menampilkan seluruh rincian konsumen, unit kendaraan, tautan lihat dokumen, serta tombol Approve/Reject.
5. **Dokumen Cetak Kontrak & PO (`DocumentPrintView`)**: Menampilkan template Dokumen Kontrak dan PO yang terisi otomatis dari database.
6. **Halaman Tanda Tangan Konsumen (`PublicSignView`)**: Menampilkan ringkasan kontrak dan kanvas tanda tangan HTML5 melalui tautan token unik.
7. **Pencairan Dana & Audit Trail (`ApplicationDetailView` - `DISBURSED`)**: Menampilkan input nomor referensi transfer, status akhir *Dana Dicairkan*, serta tabel riwayat aktivitas lengkap.

#### Slide 7 — Dampak Operasional (*Before vs After Comparison*)

| Aspek Proses | Sebelum (Manual) | Sesudah (Sistem Digital PT. JKL) |
|---|---|---|
| Pertukaran Berkas | Fotokopi fisik KTP, SPK, KK dikirim antar bagian | Unggah digital (JPG/PNG/PDF <= 2 MB) dan pratinjau langsung di browser |
| Input Data Kontrak & PO | Pengetikan ulang manual oleh admin dari formulir kertas | Otomatis terisi dari database dan langsung siap cetak/PDF |
| Approval & Revisi | Persetujuan lisan/kertas tanpa riwayat terstruktur | Keputusan Approve/Reject tercatat beserta alasan dan stempel waktu |
| Tanda Tangan Konsumen | Wajib tatap muka fisik dan *scan* ulang dokumen | Tanda tangan digital via tautan token unik atau unggah bukti TTD |
| Pelacakan Pencairan | Sulit memantau posisi berkas secara *real-time* | Dashboard ringkas & *audit trail* lengkap hingga nomor referensi transfer |

#### Slide 8 — Batasan Ruang Lingkup & Rencana Pengembangan Lanjutan
- **Batasan Tahap Awal (Fase Saat Ini)**: Fokus pada ketepatan alur internal, validasi standar web, dan dokumen siap cetak tanpa ketergantungan eksternal (belum mencakup integrasi API Dukcapil, e-Meterai pihak ketiga, atau *payment gateway* bank langsung).
- **Rencana Fase Berikutnya**:
  - Integrasi verifikasi NIK Dukcapil dan *credit scoring* biro kredit.
  - Integrasi *virtual account* / *disbursement API* perbankan untuk transfer otomatis ke dealer.
  - Notifikasi otomatis status pengajuan dan tautan TTD kepada konsumen melalui WhatsApp/email resmi perusahaan.

---

## 7. Catatan Sebelum Dipakai di Produksi

Hal-hal berikut belum diubah karena menyentuh alur sistem, tapi perlu diputuskan sebelum aplikasi dipakai dengan data konsumen asli:

- **Dokumen KTP, KK, SPK, dan bukti bayar tersimpan di disk `public`.** Siapa pun yang memegang URL `/storage/documents/...` bisa membuka file tanpa login. Nama file acak sehingga sulit ditebak, tapi URL tetap bisa bocor lewat riwayat browser atau tautan yang diteruskan. Solusinya: pindahkan ke disk `local` dan sajikan lewat endpoint yang memeriksa policy `view`.
- **Token login Sanctum tidak punya masa berlaku** (`config/sanctum.php`, `expiration => null`) dan disimpan di `localStorage`. Isi `SANCTUM_EXPIRATION` atau `expiration` sesuai kebijakan sesi perusahaan.
- **Tautan tanda tangan konsumen tetap aktif setelah kontrak ditandatangani.** Halaman hanya bisa dibaca (form tanda tangan hilang setelah status `SIGNED`), tetapi nama konsumen, NIK tersamar, dan nominal kredit masih tampil untuk siapa pun yang memegang tautan.
- **Nomor pengajuan dibuat dari nomor terakhir bulan berjalan.** Dua pengajuan yang disimpan pada saat yang sama bisa mendapat nomor yang sama; kolomnya `unique`, jadi salah satunya gagal dengan error 500. Untuk volume tinggi, gunakan tabel counter dengan lock atau retry.
- **Akun demo dan password `password123` ditampilkan di halaman login.** Hapus daftar akun di `LoginView.vue` dan ganti seeder sebelum deploy.
