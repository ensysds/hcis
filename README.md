# HCIS One

HCIS/HRIS enterprise berbasis Laravel 12, Blade, Bootstrap 5, MySQL, dan Spatie Permission. HCIS menjadi back-office dan sumber data Human Capital untuk PIC HC, sedangkan Ensys Core menjadi front-end terpadu bagi karyawan. Akses Core dihitung berdasarkan perusahaan, modul aktif, role karyawan, permission, dan scope.

## Cakupan fase 1

- Group company access: user bisa dibatasi per perusahaan, sementara super admin dapat melihat seluruh entitas.
- Organization dan employee master: company, department hierarchy, position, grade, lokasi, employee database, kontrak, dan riwayat tindakan karyawan.
- Employee lifecycle: kontrak, transfer, promosi, demosi, secondment, perubahan status, termination, retirement, serta approval internal.
- Time administration: shift, roster, attendance correction, leave policy, cuti dengan lampiran privat, overtime policy, dan perhitungan overtime ke payroll.
- Payroll control: payroll period, salary component, adjustment, overtime earning, statutory config, profil pajak/BPJS, payroll finalization, slip PDF.
- HC operations: case/process center, checklist onboarding/offboarding/disciplinary/general, asset register, serah terima, dan pengembalian aset.
- Talent back-office: performance cycle, auto-create review, learning program dan peserta, career/succession mapping.
- Policy dan workflow: kalender kerja/libur, approval flow per company, role/permission matrix, approval inbox, report XLSX/PDF.
- Core integration: katalog modul, aktivasi per perusahaan, role Core yang dapat digabung, assignment role per karyawan, bootstrap API, dan transaksi attendance dari Core.

## Menjalankan aplikasi

Persyaratan: PHP 8.2+, Composer, MySQL 8/MariaDB, serta ekstensi PHP standar Laravel.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate:fresh --seed
php artisan serve
```

Buat database MySQL bernama `hcis`, lalu sesuaikan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` pada `.env` sebelum migrasi. Untuk pengembangan cepat, proyek juga dapat memakai SQLite dengan `DB_CONNECTION=sqlite` dan file `database/database.sqlite`.

## Akun demo

Semua akun menggunakan kata sandi `password`.

| Peran | Email |
|---|---|
| Super Admin | superadmin@demo.com |
| Admin HR | hr@demo.com |
| Manager | manager@demo.com |

## Alur verifikasi

1. Admin HR/PIC HC login lalu mengelola Company Management, Organization Management, dan Employee Database.
2. Admin HR/PIC HC mencatat data cuti karyawan dari menu Administrasi Cuti.
3. Manager membuka Approval Inbox dan menyetujui tahap pertama.
4. Admin HR menyetujui tahap kedua; saldo cuti otomatis berkurang.
5. Admin HR membuat dan menjalankan periode payroll.
6. Super Admin mengakses seluruh modul, laporan XLSX/PDF, audit, serta pengaturan.

Jalankan pemeriksaan otomatis dengan `php artisan test`. Suite menguji autentikasi, RBAC, approval cuti, saldo, payroll, slip PDF, lifecycle, company scope, HC case, time administration, payroll adjustment, dan lampiran cuti privat.

## Role dan user management

Super Admin dapat membuka menu `Role Management` untuk membuat role dinamis. Permission memakai pola `modul.aksi`, misalnya `payroll.view`, `payroll.update`, `claim.create`, atau `role.delete`. Setiap role bisa diberi akses lintas modul melalui matriks View, Create, Update, Delete, Approve, dan Export.

Seeder juga membuat role default per modul dengan nama `module_employee`, `module_payroll`, `module_claim`, dan seterusnya. Role default ini bisa langsung dipakai, lalu ditambah permission modul lain sesuai kebutuhan.

Di menu `User Management`, satu user bisa mendapat beberapa role sekaligus dan akses satu atau beberapa perusahaan. Jika role saling beririsan, permission akan digabung otomatis oleh Spatie Permission; tidak ada overwrite antar-role.

## Status verifikasi

Versi saat ini berjalan di Laravel 12.64.0. Pemeriksaan terakhir:

- `php artisan test`: 18 test, 108 assertion, semua lulus.
- `npm run build`: berhasil.
- `composer audit`: tidak ada security advisory.
- `php artisan migrate --force`: migrasi enterprise/time/payroll berhasil diterapkan.
