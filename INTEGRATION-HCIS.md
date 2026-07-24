# Integrasi Core ↔ HCIS

Core adalah Employee Portal. HCIS tetap menjadi sumber data utama untuk karyawan, organisasi, aturan waktu kerja, saldo cuti, dan payroll. Core tidak membuat salinan master data karyawan.

Akun Core adalah akun employee/self-service. Akun ini melekat ke record `employees` di HCIS dan dapat login memakai NRP atau email karyawan, tetapi bukan akun admin/PIC HCIS dari tabel user back office. Karyawan dapat memiliki akun Core tanpa memiliki akses login ke HCIS.

## Kontrak API versi 1

Base URL: `HCIS_API_URL` (contoh `http://localhost:8000/api/core/v1`).

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/auth/login` | Login akun Core karyawan dan membuat sesi Core |
| GET | `/auth/session` | Memvalidasi sesi Core aktif |
| GET | `/me/bootstrap` | Profil, perusahaan, hak akses, ringkasan dashboard |
| GET | `/me/attendance` | Riwayat dan status absensi karyawan |
| POST | `/me/attendance/check-in` | Check-in dengan waktu, lokasi, dan perangkat |
| POST | `/me/attendance/check-out` | Check-out dengan waktu dan lokasi |
| GET/POST | `/me/leave-requests` | Riwayat dan pengajuan cuti |
| GET/POST | `/me/overtime-requests` | Riwayat dan pengajuan lembur |
| GET | `/me/payslips` | Daftar slip gaji milik pengguna |
| GET | `/me/payslips/{id}/pdf` | Unduh slip gaji dengan URL sementara |
| GET/POST | `/me/claims` | Reimbursement pribadi |
| GET | `/me/assets` | Aset yang sedang dipegang |
| GET | `/approvals` | Pengajuan bawahan sesuai struktur HCIS |
| POST | `/approvals/{id}/decision` | Setujui/tolak pengajuan |

## Aturan integrasi

1. `employee_id`, struktur organisasi, perusahaan, dan relasi atasan selalu berasal dari HCIS.
2. Setiap mutasi dari Core memakai `Idempotency-Key` agar permintaan ulang tidak membuat transaksi ganda.
3. Semua respons membawa `updated_at` dan `version` untuk mendeteksi data lama.
4. HCIS mengirim webhook `employee.updated`, `schedule.updated`, `leave.decided`, `payroll.published`, dan `approval.assigned` ke Core untuk invalidasi cache/notifikasi.
5. Core hanya menyimpan sesi, preferensi UI, token perangkat, dan cache singkat; bukan master data HR.
6. Slip gaji dan dokumen privat diberikan melalui URL sementara yang kedaluwarsa.
7. Seluruh request memakai HTTPS, token layanan antar-server, identitas pengguna, dan audit correlation ID.

## Model akses modul

Menu Core tidak ditentukan di front-end. HCIS menghitung akses efektif dari modul aktif perusahaan dan seluruh role Core milik karyawan. Respons `/me/bootstrap` hanya mengirim modul yang lolos kedua pemeriksaan tersebut beserta ability-nya, misalnya `view`, `create`, `consume`, `check_in`, atau `check_out`.

Solusi lain seperti e-Procurement dan ERP nantinya menggunakan pola kontrak yang sama, tetapi tidak menjadi bagian dari integrasi tahap ini.
