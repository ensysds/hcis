# HCIS Phase 1 Back-Office Scope

Dokumen ini merangkum status fase 1 setelah perluasan fitur. Fokus fase 1 adalah proses internal tim Human Capital untuk group company. ESS karyawan, recruitment portal, dan career portal eksternal sengaja dipisahkan ke fase 2.

## Modul yang sudah tersedia

- Group company access: pembatasan akses user per perusahaan, plus super admin untuk seluruh group.
- Master organization: company, department hierarchy, position, job grade, location, dan employee database.
- Employee lifecycle: kontrak, transfer, promosi, demosi, secondment, perubahan status, termination, retirement, approval, dan histori perubahan master.
- HC case center: proses onboarding, offboarding, disciplinary, grievance, document, claim, loan, industrial relation, dan proses umum dengan checklist sampai close.
- Time administration: shift, roster, attendance correction, leave policy, lampiran cuti privat, dan overtime policy.
- Payroll control: period payroll, salary component, payroll adjustment, overtime earning, payroll lock/finalize, slip PDF, statutory config, dan profil pajak/BPJS.
- Asset management: register aset, serah terima ke karyawan, dan pengembalian.
- Talent back-office: performance cycle, review generation, learning program, participant tracking, dan succession plan.
- Policy/workflow: work calendar, holiday, approval flow per company, role/permission matrix, approval inbox, dan report.
- Reporting: headcount per company, kontrak akan habis, open HC case, employee export XLSX/PDF.

## Prinsip batas fase

- Fase 1: dipakai oleh tim Human Capital/PIC HC untuk mengelola proses karyawan end-to-end.
- Fase 2: pengalaman karyawan dan pihak eksternal, seperti ESS penuh, mobile/self-service, recruitment/career portal, applicant experience, dan candidate pipeline eksternal.

## Verifikasi terakhir

- Laravel: 12.64.0.
- `php artisan test`: 18 test, 108 assertion, semua lulus.
- `npm run build`: berhasil.
- `composer audit`: tidak ada security advisory.
- `php artisan migrate --force`: migrasi enterprise/time/payroll berhasil diterapkan.
