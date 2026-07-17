# Core — Employee Portal

Core adalah unified user front-end Ensys yang responsif untuk web dan mobile. Tahap pertama terhubung ke solusi HCIS untuk absensi, cuti, lembur, approval, payroll, LMS, KMS, IMS, performance, dan layanan karyawan lainnya. Integrasi e-Procurement dan ERP disiapkan untuk tahap berikutnya.

Seluruh master data dan aturan Human Capital tetap dikelola oleh HCIS. Menu Core dibentuk dari modul perusahaan dan role karyawan yang dikirim HCIS, sehingga setiap karyawan dapat memiliki kombinasi layanan berbeda.

## Menjalankan project

Persyaratan: Node.js 22.13 atau lebih baru.

```bash
npm install
npm run dev
```

Salin `.env.example` menjadi `.env.local`, lalu isi alamat API dan token layanan HCIS saat backend integrasi tersedia. Tanpa konfigurasi tersebut, antarmuka menggunakan data demo untuk kebutuhan pengembangan UI.

```bash
npm run build
npm test
```

Kontrak endpoint dan aturan kepemilikan data tersedia di `INTEGRATION-HCIS.md`.
