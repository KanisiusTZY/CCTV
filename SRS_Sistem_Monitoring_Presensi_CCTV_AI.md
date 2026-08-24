# Software Requirements Specification

## SISTEM PEMANTAUAN PRESENSI PEGAWAI DAN WORKSTATION BERBASIS COMPUTER VISION DENGAN INTEGRASI ASISTEN AI DAN WHATSAPP GATEWAY

**Version 1.0 Approved**

**Disusun Oleh:**  
**Kanisius Rangga Putu Wiguna**  
**NIM. 10601018**

**PROGRAM STUDI D4 TEKNOLOGI REKAYASA PERANGKAT LUNAK**  
**JURUSAN TEKNOLOGI INFORMASI DAN KOMPUTER**  
**POLITEKNIK NEGERI SUBANG**  
**2026**

---

## Daftar Isi

1. **Pendahuluan** ......................................................................................................... 1  
   1.1 Tujuan Dokumen ................................................................................................ 1  
   1.2 Ruang Lingkup Perangkat Lunak ............................................................................ 2  
   1.3 Definisi, Akronim, dan Singkatan ........................................................................... 3  
   1.4 Referensi ........................................................................................................... 4  
   1.5 Gambaran Umum ............................................................................................... 4  
2. **Deskripsi Umum** .................................................................................................... 5  
   2.1 Perspektif Perangkat Lunak ................................................................................. 5  
   2.2 Manfaat Perangkat Lunak .................................................................................... 5  
   2.3 Fungsi Perangkat Lunak ...................................................................................... 6  
   2.4 Penggolongan Karakteristik Pengguna .................................................................. 6  
   2.5 Kriteria Keberhasilan ........................................................................................... 7  
   2.6 Batasan ............................................................................................................. 8  
   2.7 Asumsi dan Ketergantungan ................................................................................ 9  
   2.8 Dokumentasi Pengguna ....................................................................................... 9  
3. **Spesifikasi Kebutuhan** .......................................................................................... 10  
   3.1 Kebutuhan Fungsional ........................................................................................ 10  
   3.2 Kebutuhan Non-Fungsional ................................................................................ 11  
   3.3 Kebutuhan Antarmuka ........................................................................................ 13  
4. **Use Case Diagram** ................................................................................................ 15  
   4.1 Diagram Use Case ............................................................................................. 15  
   4.2 Deskripsi Use Case ............................................................................................ 15  
5. **Kriteria Penerimaan dan Pengujian** ....................................................................... 23  
   5.1 Kriteria Penerimaan ........................................................................................... 23  
   5.2 Metode Pengujian .............................................................................................. 24  
6. **Hardware Interface** ............................................................................................... 25  
   6.1 Spesifikasi Perangkat Keras Minimum ................................................................. 25  
   6.2 Interaksi Perangkat Lunak dengan Perangkat Keras .............................................. 26  
   6.3 Protokol dan Antarmuka Komunikasi ................................................................... 26  
7. **Software Interface** ............................................................................................... 26  
8. **Communication Interface** .................................................................................... 27  
9. **Lampiran** .............................................................................................................. 28  
   9.1 Glosarium ......................................................................................................... 28  
   9.2 Dokumentasi Pendukung .................................................................................... 29  
10. **Persetujuan dan Revisi** ....................................................................................... 32  
    10.1 Persetujuan Pemangku Kepentingan ................................................................. 32  
    10.2 Rencana Revisi ................................................................................................ 33  

---

## 1. Pendahuluan

### 1.1 Tujuan Dokumen
Di era transformasi digital dan otomatisasi perkantoran modern, manajemen presensi dan kedisiplinan kerja pegawai merupakan faktor fundamental dalam mengukur produktivitas suatu organisasi. Sistem pencatatan kehadiran konvensional seperti kartu identitas RFID, mesin sidik jari (fingerprint), maupun aplikasi absensi berbasis mobile GPS memiliki keterbatasan mendasar. Sistem-sistem tersebut hanya mencatat waktu kedatangan dan kepulangan di awal dan akhir jam kerja, namun tidak mampu memverifikasi apakah pegawai benar-benar berada di workstation (meja kerja) untuk menjalankan tugasnya. Kondisi ini kerap memicu fenomena ghost presence, yaitu pegawai melakukan absensi masuk tetapi kemudian meninggalkan meja kerja dalam waktu yang tidak wajar tanpa izin atau konfirmasi.

Berdasarkan permasalahan di atas, penulis merancang dan mengembangkan **Sistem Pemantauan Presensi Pegawai dan Workstation Berbasis Computer Vision dengan Integrasi Asisten AI dan WhatsApp Gateway**. Sistem ini memanfaatkan kemajuan teknologi Deep Learning Object Detection (YOLOv8), pelacakan objek (ByteTrack), dan pengenalan biometrik wajah (InsightFace ArcFace) untuk memantau kehadiran pegawai secara otomatis, berkelanjutan, dan objektif.

Dokumen Software Requirements Specification (SRS) ini disusun untuk memberikan acuan spesifikasi teknis, kebutuhan fungsional dan non-fungsional, arsitektur antarmuka, perancangan use case, serta kriteria pengujian perangkat lunak agar sistem dapat diimplementasikan secara terstruktur dan tepat sasaran.

### 1.2 Ruang Lingkup Perangkat Lunak
Sistem yang dirancang memiliki ruang lingkup fungsionalitas utama sebagai berikut:
a. Memproses aliran video kamera CCTV secara real-time untuk mendeteksi postur tubuh manusia pada area workstation.
b. Melakukan verifikasi identitas biometrik wajah pegawai menggunakan model Deep Feature Embeddings.
c. Menghitung durasi kehadiran aktif (occupied duration) dan durasi meninggalkan meja (away duration) secara independen untuk masing-masing meja kerja.
d. Menyediakan antarmuka editor interaktif berbasis kanvas web untuk memudahkan administrator dalam menentukan dan mengonfigurasi batas koordinat meja kerja.
e. Mengirimkan notifikasi peringatan secara otomatis melalui pesan WhatsApp kepada pegawai yang terdeteksi meninggalkan meja kerja melebihi ambang batas toleransi waktu yang telah ditetapkan.
f. Menyediakan layanan asisten cerdas (AI Assistant) terintegrasi yang mampu menjawab pertanyaan pegawai atau pimpinan terkait status kehadiran kantor secara faktual berdasarkan data pemantauan CCTV terkini.
g. Menyajikan rekapitulasi data presensi dan log notifikasi dalam bentuk tabel serta laporan yang dapat dianalisis oleh pihak manajemen/HRD.

### 1.3 Definisi, Akronim, dan Singkatan
a. **SRS (Software Requirements Specification):** Dokumen spesifikasi kebutuhan perangkat lunak yang merinci fungsi, batasan, dan kriteria sistem yang akan dibangun.
b. **CCTV (Closed-Circuit Television):** Sistem kamera pengawas video untuk transmisi sinyal visual ke perangkat pemantau.
c. **Computer Vision:** Bidang kecerdasan buatan yang memungkinkan komputer mengenali dan mengekstraksi informasi dari data visual seperti gambar dan video.
d. **YOLOv8 (You Only Look Once Version 8):** Arsitektur jaringan saraf tiruan mutakhir untuk deteksi objek berkecepatan tinggi.
e. **ByteTrack:** Algoritma pelacakan multi-objek (multi-object tracking) yang memanfaatkan asosiasi kemiripan bounding box dan Kalman Filter.
f. **ArcFace:** Algoritma pengenalan wajah biometrik berbasis Additive Angular Margin Loss yang memetakan fitur wajah ke dalam vektor representasi 512 dimensi.
g. **IoU (Intersection over Union):** Metrik kalkulasi tumpang-tindih spasial antara area deteksi orang dan area koordinat meja kerja.
h. **UI/UX (User Interface / User Experience):** Aspek antarmuka visual dan kenyamanan pengalaman interaksi pengguna terhadap sistem.
i. **REST API (Representational State Transfer API):** Antarmuka komunikasi data terstandarisasi antar subsistem perangkat lunak melalui protokol HTTP.
j. **WhatsApp Gateway:** Perangkat lunak penghubung yang memungkinkan sistem mengirim dan menerima pesan instan WhatsApp secara mandiri.
k. **Generative AI / LLM:** Model kecerdasan buatan pemrosesan bahasa alami untuk menghasilkan respon dialog yang kontekstual dan adaptif.

### 1.4 Referensi
- IEEE Std 830-1998: IEEE Recommended Practice for Software Requirements Specifications.
- Ultralytics YOLOv8 Documentation & Architecture Standards.
- DeepInsight InsightFace: State-of-the-art 2D and 3D Face Analysis.
- Laravel Framework Architecture and Security Standards.

### 1.5 Gambaran Umum
Sistem ini dirancang untuk beroperasi secara modular dan terdistribusi, menghubungkan sub-sistem analisis visi komputer (Computer Vision Engine), sub-sistem aplikasi web manajemen (Web Management & Dashboard), dan sub-sistem perpesanan pintar (WhatsApp Gateway & AI Assistant). Kamera CCTV yang terpasang pada ruangan kantor akan mentransmisikan video ke modul visi komputer untuk diproses secara berkelanjutan. Hasil analisis spasial dan biometrik akan disinkronisasikan ke dalam basis data sehingga pimpinan dan staf dapat memantau kondisi workstation secara transparan melalui dashboard web maupun percakapan interaktif WhatsApp.

---

## 2. Deskripsi Umum

### 2.1 Perspektif Perangkat Lunak
Perangkat lunak ini bertindak sebagai platform otomasi pengawasan presensi cerdas (Smart Presence Surveillance) yang memadukan teknologi pengolahan citra digital dengan aplikasi enterprise. Berbeda dengan sistem CCTV konvensional yang bersifat pasif dan hanya merekam video, sistem ini secara aktif mengidentifikasi objek, mengukur durasi aktivitas di meja kerja, dan mengambil tindakan otomatis berupa peringatan dini jika terjadi pelanggaran batas waktu kehadiran.

### 2.2 Manfaat Perangkat Lunak
Adapun manfaat dari perangkat lunak ini adalah:
a. **Meningkatkan Disiplin Kerja:** Mencegah ketidakhadiran tanpa keterangan pada jam kerja melalui pengawasan workstation yang kontinu.
b. **Otomatisasi Peringatan:** Mengurangi beban kerja HRD/manajemen dalam menegur pegawai secara manual dengan memanfaatkan notifikasi otomatis WhatsApp.
c. **Akurasi Data Presensi:** Menghasilkan data rekapitulasi jam kerja berbasis bukti visual biometrik yang objektif dan valid.
d. **Kemudahan Akses Informasi:** Memfasilitasi pimpinan atau rekan kerja untuk memeriksa status ketersediaan anggota tim secara cepat melalui asisten virtual AI.
e. **Efisiensi Manajemen Meja:** Membantu pengelolaan tata letak workstation kantor secara fleksibel melalui editor kanvas digital.

### 2.3 Fungsi Perangkat Lunak
Adapun fungsi dari perangkat lunak ini mencakup:
a. Menampilkan streaming video pemantauan CCTV secara real-time pada dashboard.
b. Mendeteksi keberadaan tubuh manusia pada area meja kerja yang telah dipetakan.
c. Mencocokkan wajah pegawai dengan data biometrik foto referensi yang terdaftar.
d. Mengakumulasi durasi aktif bekerja (Bekerja) dan durasi meninggalkan meja (Tidak di Tempat).
e. Menyediakan form pendaftaran pegawai beserta upload foto wajah biometrik dan pengaturan toleransi waktu.
f. Menyediakan fitur drag-and-drop kanvas untuk pembuatan dan konfigurasi koordinat meja kerja.
g. Mengirimkan notifikasi WhatsApp otomatis saat durasi meninggalkan meja melampaui batas toleransi.
h. Menyediakan bot asisten pintar untuk melayani pertanyaan presensi melalui percakapan teks natural.

### 2.4 Penggolongan Karakteristik Pengguna

**Tabel 2. 1 Karakteristik Pengguna**

| Kategori Pengguna | Tugas | Hak Akses ke Aplikasi | Kemampuan yang Harus Dimiliki |
| :--- | :--- | :--- | :--- |
| **Administrator / HRD** | Mengelola zona meja, mengelola data pegawai, mengatur batas waktu toleransi, memantau dashboard, dan mengevaluasi laporan presensi. | Akses penuh (Full Access) ke seluruh menu dashboard, editor zona, manajemen master data, dan log sistem. | Memahami pengoperasian komputer dan browser web, memahami struktur workstation kantor. |
| **Pegawai / Karyawan** | Menjalankan aktivitas kerja di workstation, menerima notifikasi kehadiran, dan melakukan pengecekan status rekan kerja via WhatsApp. | Menerima pesan notifikasi WhatsApp dan berinteraksi dengan AI Assistant melalui percakapan pesan instan. | Mampu menggunakan aplikasi perpesanan WhatsApp pada smartphone. |

### 2.5 Kriteria Keberhasilan
Keberhasilan perangkat lunak ini diukur berdasarkan parameter berikut:
a. **Keandalan Deteksi:** Sistem mampu mengenali kehadiran orang di area meja kerja dengan tingkat akurasi minimal 95%.
b. **Akurasi Biometrik Wajah:** Pengenalan identitas pegawai mencapai tingkat kecocokan minimal 90% pada kondisi pencahayaan ruang kerja yang memadai.
c. **Kelancaran Streaming Video:** Sistem mampu menampilkan aliran video secara mulus dengan latensi minimal (< 500 ms).
d. **Kecepatan Notifikasi:** Notifikasi WhatsApp terkirim dalam waktu kurang dari 15 detik setelah pegawai melanggar batas toleransi waktu.
e. **Ketepatan Respon AI:** Asisten AI memberikan jawaban yang selaras dengan data CCTV aktual tanpa menghasilkan informasi palsu (halusinasi).

### 2.6 Batasan
Batasan operasional perangkat lunak meliputi:
a. Kamera CCTV harus diposisikan pada sudut pandang (angle) yang mampu mencakup area kepala, bahu, dan meja kerja pegawai secara jelas.
b. Kualitas pengenalan wajah biometrik dipengaruhi oleh intensitas pencahayaan ruangan dan resolusi citra wajah pada frame video.
c. Nomor telepon pegawai yang didaftarkan harus merupakan nomor aktif yang terhubung dengan akun WhatsApp.
d. Layanan pemrosesan bahasa alami AI Assistant memerlukan koneksi jaringan internet untuk berkomunikasi dengan model AI cloud.

### 2.7 Asumsi dan Ketergantungan
a. Komputer server pemrosesan video memiliki daya komputasi yang memadai untuk menjalankan algoritma Deep Learning.
b. Kamera pengawas CCTV beroperasi secara stabil dan terhubung dalam jaringan lokal server.
c. Administrator menggunakan browser web modern yang mendukung teknologi HTML5 Canvas.

### 2.8 Dokumentasi Pengguna
Untuk memudahkan pengoperasian sistem, disediakan dokumen User Manual yang memuat panduan langkah demi langkah mengenai cara pembuatan zona meja pada kanvas, tata cara registrasi pegawai dan upload foto wajah biometrik, konfigurasi batas waktu toleransi, serta integrasi layanan WhatsApp.

---
