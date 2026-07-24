# PLANNING DETAIL — HCIS/HRIS (Human Capital Information System)

> Dokumen ini adalah **spesifikasi build lengkap** untuk dieksekusi oleh AI agent.
> Bangun aplikasi web HRIS enterprise dengan **Laravel + Blade + Bootstrap 5 + MySQL**,
> bergaya **SAP Fiori-like**: clean, bersih, compact, font kecil.
> Kerjakan **semua modul secara fungsional** (bukan placeholder), mengikuti urutan build di bagian akhir.

---

## 1. CONTEXT (Latar Belakang & Tujuan)

- **Masalah**: Perusahaan (grup usaha, multi-company/multi-site) membutuhkan sistem HRIS terpusat untuk mengelola seluruh *employee lifecycle* dari rekrutmen sampai offboarding.
- **Tujuan**: Menyediakan aplikasi web HRIS lengkap dengan modul inti sampai lanjutan, memiliki **role-based access** (User/Karyawan & Admin/HR), login **email + password**, dan UI enterprise ala SAP.
- **Hasil yang diharapkan**: Aplikasi Laravel jalan (migrasi + seeder + UI + logika bisnis) yang bisa login, menampilkan dashboard sesuai role, dan menjalankan proses tiap modul (CRUD + workflow approval).
- **Audiens dokumen**: AI coding agent. Semua keputusan teknis sudah dikunci di bawah — jangan bertanya balik, langsung eksekusi dengan default yang tertulis.

---

## 2. TECH STACK & KONVENSI (WAJIB)

| Aspek | Keputusan |
|---|---|
| Framework | Laravel 12 (PHP 8.2+) |
| Frontend | Blade + Bootstrap 5.3 (CDN atau Vite), Bootstrap Icons |
| Interaktivitas ringan | Vanilla JS + sedikit Alpine.js (opsional) untuk dropdown/modal |
| Database | MySQL 8 / MariaDB |
| Auth | Laravel built-in (session), custom login **email + password** |
| Otorisasi | Spatie Laravel Permission (roles & permissions) |
| Tabel data | Server-side pagination Laravel; DataTables opsional utk search/sort |
| Export | Laravel Excel (maatwebsite/excel) untuk XLSX/CSV; DomPDF untuk PDF/slip |
| Chart dashboard | Chart.js |
| Struktur kode | Controller tipis, Service class untuk logika bisnis, Form Request untuk validasi, Blade components untuk UI reusable |

### Konvensi penamaan
- Tabel: `snake_case` jamak (`employees`, `leave_requests`).
- Route name: `modul.aksi` (`employees.index`, `leave.approve`).
- Setiap modul punya folder Controller sendiri: `app/Http/Controllers/{Module}/`.
- Setiap modul punya folder view: `resources/views/{module}/`.
- Kolom audit standar tiap tabel transaksi: `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at` (soft delete).

---

## 3. DESAIN UI — "SAP STYLE" (WAJIB DIIKUTI)

Buat 1 layout master + design token, dipakai konsisten di semua halaman.

### Prinsip visual
- **Compact & rapat**: padding kecil, baris tabel tipis (tinggi baris ± 32px).
- **Font kecil**: base `13px`, tabel `12px`, heading `14–16px`. Font: `Inter`, `Segoe UI`, sans-serif.
- **Warna netral & bersih** (palet SAP-like):
  - Background app: `#f5f6f7`
  - Panel/kartu: `#ffffff`
  - Border: `#e0e0e0`
  - Teks utama: `#32363a`
  - Aksen/primary: `#0a6ed1` (SAP blue), hover `#085caf`
  - Sukses `#107e3e`, warning `#e9730c`, error `#bb0000`
- **Header bar** atas tipis (± 44px): logo kiri, nama app, search global, notifikasi, profil user.
- **Sidebar** kiri collapsible: daftar modul (icon + label kecil), grup per kategori.
- **Konten**: breadcrumb tipis → judul halaman → toolbar aksi (kanan) → tabel/form.
- **Tabel**: garis tipis, header abu muda, zebra opsional, aksi baris berupa icon kecil (view/edit/delete). Toolbar: search, filter, tombol "+ New", export.
- **Form**: label kecil di atas field, grid 2–3 kolom, field rapat, tombol Save/Cancel kanan bawah.
- **Status** pakai *badge* kecil (Draft/Submitted/Approved/Rejected) dengan warna semantik.

### Komponen Blade yang harus dibuat (reusable)
`x-app-layout`, `x-page-header` (breadcrumb+judul+toolbar), `x-data-table`, `x-form-input`, `x-form-select`, `x-status-badge`, `x-modal`, `x-card`, `x-stat-tile` (KPI dashboard), `x-approval-timeline`.

---

## 4. AUTENTIKASI (Login email + password)

### Requirement
- Halaman **login** menampilkan 2 field: **Email** dan **NRP** (Nomor Registrasi Pegawai) + password.
- Aturan: user login dengan **kombinasi email + password**. NRP tetap menjadi data unik di tabel `users`/`employees`, tetapi tidak dipakai sebagai syarat login.
- Validasi: email harus terdaftar, password benar, akun `is_active = true`.
- Setelah login → redirect sesuai role:
  - Role **admin/HR** → `/admin/dashboard`
  - Role **user/karyawan** → `/dashboard` (ESS)
- Fitur tambahan: "Remember me", logout, lupa password (reset via email), rate-limit percobaan login (throttle 5x), audit log login.

### Implementasi
- Tabel `users`: `id, employee_id, nrp (unique), email (unique), password, is_active, last_login_at, ...`.
- Custom `LoginRequest` memvalidasi email+password dan memastikan akun aktif.
- Guard session default. Gunakan Spatie roles untuk penentuan redirect & menu.
- Halaman: `resources/views/auth/login.blade.php` — desain SAP-clean, panel login di tengah, logo perusahaan, background netral.

---

## 5. RBAC — ROLE & PEMETAAN AKSES MODUL

### Role dasar (seed di awal)
1. **super_admin** — akses penuh semua modul + setting sistem.
2. **admin_hr** — akses seluruh modul HR (kelola data semua karyawan), tanpa setting sistem kritikal.
3. **manager** — akses tim (approval, lihat data bawahan) + ESS pribadi.
4. **employee (user)** — akses ESS pribadi saja.

Gunakan **permission granular** per modul: `view`, `create`, `update`, `delete`, `approve`, `export` (mis. `employee.view`, `leave.approve`). Role adalah kumpulan permission.

### PEMETAAN AKSES: USER (Karyawan/ESS) vs ADMIN (HR)

| Modul | Akses USER (Employee/ESS) | Akses ADMIN (HR/Super Admin) |
|---|---|---|
| **Dashboard** | Dashboard pribadi: info absen hari ini, sisa cuti, pengumuman, tugas approval (jika manager) | Dashboard HR: headcount, kehadiran hari ini, cuti pending, turnover, grafik |
| **Employee Database** | Lihat & ajukan perubahan data **pribadi** sendiri (butuh approval HR) | CRUD data **semua** karyawan, approve perubahan data, kelola dokumen |
| **Organization** | Lihat struktur organisasi (read-only) | CRUD struktur org, jabatan, departemen, cost center |
| **Recruitment** | (Opsional) referral kandidat | CRUD lowongan, kelola pelamar, jadwal interview, offering |
| **Onboarding** | Lihat & isi checklist onboarding sendiri | Kelola template & progress onboarding semua karyawan baru |
| **Attendance** | Check-in/out (mobile/GPS), lihat riwayat absen sendiri | Lihat & koreksi absensi semua, kelola mesin, rekap |
| **Shift & Schedule** | Lihat jadwal shift sendiri | Buat & assign pola shift/roster |
| **Leave (Cuti)** | Ajukan cuti, lihat saldo & riwayat sendiri | Approve/kelola cuti semua, atur kuota & jenis cuti |
| **Overtime (Lembur)** | Ajukan lembur, lihat riwayat sendiri | Approve & rekap lembur |
| **Payroll** | Lihat & unduh **slip gaji** sendiri | Jalankan payroll run, komponen gaji, generate slip semua |
| **Tax (PPh21)** | Lihat bukti potong pajak sendiri | Kelola perhitungan & pelaporan pajak |
| **BPJS** | Lihat status kepesertaan sendiri | Kelola kepesertaan & iuran BPJS |
| **Loan/Kasbon** | Ajukan pinjaman, lihat cicilan sendiri | Approve & kelola pinjaman, potongan |
| **Reimbursement/Claim** | Ajukan klaim, lihat status sendiri | Approve & bayar klaim |
| **Performance (KPI)** | Isi self-appraisal, lihat hasil sendiri | Kelola siklus penilaian, kalibrasi, review semua |
| **Competency** | Lihat kompetensi & gap sendiri | Kelola kamus kompetensi & assessment |
| **Career & Succession** | Lihat jalur karier sendiri | Kelola talent pool & suksesi |
| **Learning (LMS)** | Ikut training, lihat sertifikat sendiri | Kelola katalog training, peserta, evaluasi |
| **Offboarding/Exit** | Ajukan resign, isi exit interview, clearance sendiri | Kelola proses exit & clearance semua |
| **Disciplinary/SP** | Lihat SP sendiri (jika ada) | Kelola pelanggaran & surat peringatan |
| **Document/Letter** | Ajukan & unduh surat (mis. surat keterangan kerja) | Generate & kelola surat/dokumen semua |
| **Asset** | Lihat aset yang dipegang sendiri | Kelola aset & serah terima |
| **Reports & Analytics** | Laporan terbatas pribadi | Semua laporan & analitik HR |
| **Admin & Settings** | ❌ Tidak ada | User, role, permission, master data, audit log, konfigurasi sistem |

> **Manager** = akses employee + kemampuan **approve** dan **lihat data bawahan** pada modul Attendance, Leave, Overtime, Reimbursement, Performance.

---

## 6. STRUKTUR PROYEK & FONDASI (dibangun paling awal)

1. `composer create-project laravel/laravel hcis`
2. Install: `spatie/laravel-permission`, `maatwebsite/excel`, `barryvdh/laravel-dompdf`.
3. Konfigurasi `.env` MySQL (`hcis` db).
4. Layout master + design token (bagian 3) + komponen Blade reusable.
5. Auth email+password (bagian 4) + RBAC seeder (bagian 5).
6. Master data umum: `companies`, `departments`, `positions`, `job_grades`, `cost_centers`, `locations/sites`.
7. Menu sidebar dinamis berbasis permission (item menu muncul sesuai hak akses).
8. Notifikasi in-app + **Approval/Workflow Engine** generik (dipakai lintas modul).
9. Audit log (paket `spatie/laravel-activitylog` atau tabel `activity_logs`).

### Approval/Workflow Engine (komponen inti lintas modul)
Tabel generik: `approval_flows` (definisi tahap per jenis dokumen), `approval_requests` (dokumen yang diajukan: type, ref_id, status), `approval_steps` (tahap, approver, status, catatan, waktu). Semua modul yang butuh approval (cuti, lembur, klaim, pinjaman, perubahan data) memakai engine ini. Status standar: `Draft → Submitted → In Review → Approved / Rejected → (Cancelled)`.

---

## 7. RINCIAN MODUL (fitur, proses, tabel, layar)

> Format tiap modul: **Fitur** · **Proses/alur** · **Tabel utama** · **Layar/route**.

### 7.1 Core — Employee Database (Personnel)
- **Fitur**: data pribadi (NIK, NRP, nama, TTL, gender, agama, status kawin, alamat, kontak), data kepegawaian (tgl masuk, status kontrak/tetap, jabatan, dept, atasan, grade, lokasi), keluarga, pendidikan, pengalaman, dokumen (KTP, NPWP, ijazah — upload file), foto, riwayat mutasi/promosi.
- **Proses**: HR create/edit; karyawan ajukan perubahan data pribadi → approval HR → tersimpan. Riwayat perubahan tercatat.
- **Tabel**: `employees`, `employee_families`, `employee_educations`, `employee_experiences`, `employee_documents`, `employee_movements` (mutasi/promosi/demosi).
- **Layar**: list karyawan (search/filter dept, status), detail karyawan (tab-based), form edit, ESS "Data Saya".

### 7.2 Core — Organization Management
- **Fitur**: struktur organisasi (tree), unit/departemen, jabatan, job grade, cost center, hierarki company/site. Org chart visual.
- **Proses**: admin kelola CRUD; perubahan struktur memengaruhi relasi atasan-bawahan.
- **Tabel**: `companies`, `departments` (parent_id utk hirarki), `positions`, `job_grades`, `cost_centers`, `locations`.
- **Layar**: org chart, list & form tiap master.

### 7.3 Recruitment & ATS
- **Fitur**: manpower request (MPP), lowongan (internal/eksternal), pelamar (biodata, CV upload), tahap seleksi (screening → interview → test → offering), status kandidat, konversi kandidat jadi karyawan (feed ke Onboarding).
- **Proses**: request MPP → approval → posting lowongan → pelamar masuk → pipeline seleksi (drag/status) → offering → hired → onboarding.
- **Tabel**: `manpower_requests`, `job_vacancies`, `candidates`, `applications`, `interview_schedules`, `job_offers`.
- **Layar**: kanban/pipeline pelamar, form lowongan, jadwal interview.

### 7.4 Onboarding
- **Fitur**: template checklist onboarding per posisi/dept, penugasan aset, dokumen yang harus dilengkapi, progress tracking, PIC.
- **Proses**: karyawan baru → checklist auto-generate dari template → item diceklis oleh karyawan/HR/IT → selesai.
- **Tabel**: `onboarding_templates`, `onboarding_tasks`, `employee_onboardings`, `employee_onboarding_items`.
- **Layar**: progress board, checklist karyawan (ESS).

### 7.5 Attendance (Absensi)
- **Fitur**: check-in/out via web & mobile (GPS + selfie opsional), integrasi mesin fingerprint/face (import log), status hadir/terlambat/pulang cepat/alpha, dikaitkan dengan shift, koreksi absensi (dengan approval).
- **Proses**: karyawan check-in/out → sistem hitung terlambat berdasar shift → rekap harian/bulanan. HR koreksi bila perlu.
- **Tabel**: `attendances` (per hari: in, out, late_minutes, status), `attendance_logs` (raw mesin), `attendance_corrections`.
- **Layar**: kalender absensi pribadi, monitor kehadiran hari ini (admin), rekap & export.

### 7.6 Shift & Scheduling
- **Fitur**: definisi shift (jam masuk/pulang, toleransi), pola/roster, assign karyawan ke shift per tanggal, shift lapangan/site.
- **Proses**: admin buat shift → susun roster mingguan/bulanan → assign. Dipakai modul Attendance & Overtime.
- **Tabel**: `shifts`, `shift_schedules` (employee_id, date, shift_id).
- **Layar**: kalender roster, assign massal.

### 7.7 Leave Management (Cuti)
- **Fitur**: jenis cuti (tahunan, sakit, melahirkan, izin, cuti besar), kuota/saldo per karyawan, pengajuan (tanggal, alasan, lampiran), approval bertingkat, kalkulasi saldo otomatis, kalender cuti tim.
- **Proses**: karyawan ajukan → engine approval (atasan → HR) → saldo terpotong → tercatat. Saldo di-reset/prorate tahunan.
- **Tabel**: `leave_types`, `leave_balances`, `leave_requests`, (pakai `approval_*`).
- **Layar**: form ajukan cuti, saldo & riwayat (ESS), approval inbox (manager), kalender cuti (admin).

### 7.8 Overtime (Lembur)
- **Fitur**: pengajuan lembur (tanggal, jam mulai/selesai, alasan), perhitungan jam & rate lembur (mengikuti aturan Depnaker), approval, feed ke payroll.
- **Proses**: ajukan → approval atasan/HR → jam lembur diakui → masuk komponen payroll.
- **Tabel**: `overtime_requests` (+ `approval_*`).
- **Layar**: form lembur, rekap, approval inbox.

### 7.9 Payroll
- **Fitur**: komponen gaji (earnings: gaji pokok, tunjangan; deductions: potongan, BPJS, pajak, pinjaman), struktur gaji per karyawan/grade, **payroll run** per periode, integrasi absensi/lembur/cuti tanpa bayar, generate **slip gaji** (PDF), THR & bonus, riwayat payroll.
- **Proses**: HR buat periode → sistem tarik data absen/lembur → hitung earnings-deductions-netto → review → finalize → slip terbit → ESS bisa unduh.
- **Tabel**: `salary_components`, `employee_salaries`, `payroll_periods`, `payroll_runs`, `payslips`, `payslip_details`.
- **Layar**: setup komponen, payroll run wizard, review tabel, slip (ESS).

### 7.10 Tax (PPh 21)
- **Fitur**: perhitungan PPh21 (PTKP, tarif progresif, TER terbaru), bukti potong (1721-A1), integrasi payroll.
- **Proses**: dihitung saat payroll run; laporan pajak per periode/tahunan.
- **Tabel**: `tax_configs`, `employee_tax_profiles` (status PTKP), `tax_records`.
- **Layar**: konfigurasi pajak, laporan, bukti potong (ESS).

### 7.11 BPJS
- **Fitur**: kepesertaan BPJS Ketenagakerjaan (JHT, JP, JKK, JKM) & Kesehatan, perhitungan iuran (porsi perusahaan & karyawan), integrasi payroll.
- **Tabel**: `bpjs_configs`, `employee_bpjs`.
- **Layar**: konfigurasi rate, data kepesertaan, laporan iuran.

### 7.12 Loan / Cash Advance (Pinjaman/Kasbon)
- **Fitur**: pengajuan pinjaman, plafon, tenor, jadwal cicilan, potongan otomatis di payroll, sisa pinjaman.
- **Proses**: ajukan → approval → cairkan → cicilan dipotong tiap payroll sampai lunas.
- **Tabel**: `loans`, `loan_installments`.
- **Layar**: form ajukan, jadwal cicilan (ESS), approval & monitoring (admin).

### 7.13 Reimbursement / Claim
- **Fitur**: pengajuan klaim (medis, transport, dinas) + lampiran nota, kategori & plafon, approval, pembayaran (via payroll atau terpisah).
- **Tabel**: `claim_types`, `claims`, `claim_details` (+ `approval_*`).
- **Layar**: form klaim, status (ESS), approval & rekap (admin).

### 7.14 Performance Management (KPI/OKR)
- **Fitur**: siklus penilaian (periode), KPI/goal setting, bobot, self-appraisal, penilaian atasan, kalibrasi, skor akhir, feedback 360 (opsional).
- **Proses**: HR buka siklus → goal setting → self-appraisal → review atasan → kalibrasi → finalisasi skor.
- **Tabel**: `appraisal_cycles`, `kpis`, `appraisals`, `appraisal_items`, `appraisal_scores`.
- **Layar**: form goal & appraisal (ESS), review (manager), monitoring siklus (admin).

### 7.15 Competency Management
- **Fitur**: kamus kompetensi, level per jabatan, assessment, gap analysis.
- **Tabel**: `competencies`, `position_competencies`, `employee_competencies`.
- **Layar**: kamus, assessment, gap report.

### 7.16 Career & Succession Planning
- **Fitur**: jenjang karier, talent pool, rencana suksesi posisi kunci, readiness kandidat.
- **Tabel**: `career_paths`, `talent_pools`, `succession_plans`.
- **Layar**: career path viewer, matrix suksesi (admin).

### 7.17 Learning & Development (LMS)
- **Fitur**: katalog training, pendaftaran, jadwal, materi (upload/link), kehadiran, evaluasi, sertifikat, TNA (training need analysis).
- **Tabel**: `trainings`, `training_sessions`, `training_participants`, `certificates`.
- **Layar**: katalog & daftar (ESS), kelola training & peserta (admin).

### 7.18 Offboarding / Exit
- **Fitur**: pengajuan resign, persetujuan, exit checklist/clearance (IT, finance, aset), exit interview, perhitungan pesangon (opsional), penonaktifan akun.
- **Proses**: karyawan resign → approval → clearance per unit → exit interview → akun dinonaktifkan.
- **Tabel**: `resignations`, `exit_clearances`, `exit_interviews`.
- **Layar**: form resign (ESS), clearance board (admin).

### 7.19 Disciplinary / SP
- **Fitur**: catatan pelanggaran, jenis & level SP (SP1/2/3), surat peringatan, masa berlaku.
- **Tabel**: `disciplinary_cases`, `warning_letters`.
- **Layar**: input kasus, daftar SP, surat (generate PDF).

### 7.20 Document & Letter Management
- **Fitur**: template surat (surat keterangan kerja, kontrak, SK), generate PDF dengan data karyawan, arsip dokumen, pengajuan surat (ESS).
- **Tabel**: `letter_templates`, `letters`, `documents`.
- **Layar**: kelola template, generate & arsip.

### 7.21 Asset Management
- **Fitur**: master aset, serah terima ke karyawan, pengembalian, kondisi, dikaitkan onboarding/offboarding.
- **Tabel**: `assets`, `asset_assignments`.
- **Layar**: daftar aset, form serah terima, aset saya (ESS).

### 7.22 Reports & Analytics / Dashboard
- **Fitur**: dashboard HR (headcount, demografi, kehadiran, cuti, turnover, cost), laporan per modul, export XLSX/PDF, filter periode/dept.
- **Tabel**: (agregasi dari tabel modul; view/query).
- **Layar**: dashboard admin (Chart.js), pusat laporan.

### 7.23 Admin & Settings
- **Fitur**: kelola user, role & permission, master data (semua master), konfigurasi approval flow, konfigurasi sistem (nama perusahaan, logo, periode fiskal), audit log, backup.
- **Layar**: user management, role-permission matrix, master data, settings, audit log viewer.

---

## 8. HALAMAN UTAMA YANG WAJIB ADA

1. **Login** (email + password) — SAP clean.
2. **Dashboard User (ESS)** — ringkasan pribadi + menu ESS.
3. **Dashboard Admin (HR)** — KPI + grafik + antrean approval.
4. **Layout master** (header + sidebar dinamis + breadcrumb).
5. **Halaman modul** untuk tiap modul di bagian 7 (index/list, detail, form, approval).
6. **Profil & ganti password**.
7. **Halaman 403/404** bergaya konsisten.

---

## 9. URUTAN BUILD (fase eksekusi untuk AI agent)

Meski semua modul fungsional, kerjakan bertahap agar stabil & bisa diverifikasi:

**Fase 0 — Fondasi**: setup Laravel, DB, layout SAP, komponen Blade, auth email+password, RBAC (Spatie), seeder role & user demo, sidebar dinamis, approval engine, audit log, notifikasi.

**Fase 1 — Core**: Employee Database, Organization, master data, Dashboard User & Admin.

**Fase 2 — Time**: Attendance, Shift, Leave, Overtime (pakai approval engine).

**Fase 3 — Payroll**: Salary components, Payroll run, Slip, Tax PPh21, BPJS, Loan, Reimbursement.

**Fase 4 — Talent**: Recruitment, Onboarding, Performance, Competency, Career/Succession, Learning.

**Fase 5 — Admin/Lainnya**: Offboarding, Disciplinary, Document/Letter, Asset, Reports & Analytics, Settings.

Di setiap fase: buat migration + model + seeder demo + controller + service + Blade + route + hak akses, lalu pastikan bisa dijalankan.

---

## 10. DATA DEMO (SEEDER)

Sediakan seeder agar aplikasi langsung bisa dites:
- 1 company, 3 departemen, beberapa posisi/grade.
- 4 user demo: `superadmin@demo.com`, `hr@demo.com`, `manager@demo.com`, `employee@demo.com` — password `password`.
- ± 15 karyawan dummy, beberapa pengajuan cuti/lembur/klaim contoh, 1 payroll period contoh.

---

## 11. VERIFIKASI (cara menguji end-to-end)

1. `php artisan migrate:fresh --seed` sukses tanpa error.
2. `php artisan serve` → buka `/login`.
3. Login **employee** (email+password) → masuk Dashboard ESS; menu hanya modul user; bisa ajukan cuti → status Submitted.
4. Login **manager** → ada inbox approval; approve cuti → status Approved; saldo cuti karyawan berkurang.
5. Login **admin_hr** → Dashboard HR tampil grafik; bisa CRUD karyawan; jalankan payroll run → slip gaji ter-generate → employee bisa unduh slip PDF.
6. Login **super_admin** → bisa kelola user/role/permission & settings.
7. Cek RBAC: employee **tidak bisa** akses route admin (dapat 403).
8. Cek UI: font kecil/compact, layout SAP konsisten, responsif dasar.
9. Export salah satu laporan ke XLSX & PDF berhasil.
10. Audit log mencatat login & aksi penting.

---

## 12. CATATAN & ASUMSI

- Integrasi mesin fingerprint & mobile app = sediakan endpoint/import log; app mobile native di luar scope build ini (web responsif dulu).
- Perhitungan pajak/BPJS: gunakan konfigurasi yang bisa diubah (jangan hardcode tarif), agar mudah menyesuaikan regulasi.
- Semua modul memakai soft delete + audit kolom.
- Utamakan kebenaran alur & RBAC; styling detail boleh disempurnakan setelah fungsional jalan.
