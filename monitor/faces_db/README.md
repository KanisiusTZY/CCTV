# Dataset Foto Wajah Karyawan (Optional Face Verification)

Folder ini digunakan untuk menyimpan foto sampel wajah karyawan untuk fitur **Verifikasi Identitas Opsional**.

## Cara Menambahkan Foto Karyawan:
1. Simpan foto wajah karyawan di folder ini (`faces_db/`).
2. Beri nama file sesuai nama asli karyawan (gunakan tanda garis bawah `_` untuk spasi).

Contoh:
- `Budi_Santoso.jpg`
- `Siti_Rahma.png`
- `Agus_Pratama.jpeg`

## Catatan Penting:
- **Spatial Person Detector (Inti Utama)**: Sistem tetap 100% mendeteksi status **BEKERJA** (Boks Hijau) di meja kerja meskipun wajah pegawai sedang **membelakangi kamera, menunduk, atau tertutup masker**.
- **Face Verification (Fitur Opsional)**: Jika pegawai sesekali menoleh ke kamera dan foto wajahnya ada di folder ini, nama pegawai (misal: `Budi Santoso 94%`) akan secara otomatis terverifikasi dan muncul di atas boks meja!
