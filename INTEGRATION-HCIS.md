# Integrasi Core ↔ HCIS

Core adalah Employee Portal. HCIS tetap menjadi sumber data utama untuk karyawan, organisasi, aturan waktu kerja, saldo cuti, dan payroll. Core tidak membuat salinan master data karyawan.

## Kontrak API versi 1

Base URL: `HCIS_API_URL` (contoh `http://localhost:8000/api/core/v1`).

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/auth/session` | Menukar identitas SSO menjadi sesi Core |
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
