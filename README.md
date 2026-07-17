# Core — Employee Self Service

Core adalah portal karyawan yang responsif untuk web dan mobile. Seluruh master data Human Capital tetap dikelola oleh HCIS; Core menjadi pengalaman pengguna untuk absensi, cuti, lembur, approval, payroll, klaim, dokumen, aset, serta layanan karyawan lainnya.

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
