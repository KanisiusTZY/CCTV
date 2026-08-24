# SOFTWARE REQUIREMENTS SPECIFICATION (SRS)

## SISTEM PEMANTAUAN PRESENSI PEGAWAI DAN WORKSTATION BERBASIS COMPUTER VISION (YOLOv8 + ARCFACE) DENGAN ASISTEN AI GEMINI DAN WHATSAPP GATEWAY

**Version 1.0 Approved**

**Disusun Oleh:**  
**Kanisius Rangga (10601018)**  
*Program Studi D4 Teknologi Rekayasa Perangkat Lunak*  
*Jurusan Teknologi Informasi dan Komputer*  
*Politeknik Negeri Subang*  
*2026*

---

## DAFTAR ISI

1. **Pendahuluan**
   - 1.1 Tujuan Dokumen
   - 1.2 Ruang Lingkup Perangkat Lunak
   - 1.3 Definisi, Akronim, dan Singkatan
   - 1.4 Referensi
   - 1.5 Gambaran Umum Sistem
2. **Deskripsi Umum**
   - 2.1 Perspektif Perangkat Lunak
   - 2.2 Manfaat Perangkat Lunak
   - 2.3 Fungsi Utama Perangkat Lunak
   - 2.4 Penggolongan Karakteristik Pengguna
   - 2.5 Kriteria Keberhasilan
   - 2.6 Batasan Sistem
   - 2.7 Asumsi dan Ketergantungan
   - 2.8 Dokumentasi Pengguna
3. **Spesifikasi Kebutuhan**
   - 3.1 Kebutuhan Fungsional (User Story & Acceptance Criteria)
   - 3.2 Kebutuhan Non-Fungsional
   - 3.3 Kebutuhan Antarmuka (User Interface, Fungsional, dan System Interface)
4. **Use Case Diagram dan Skenario**
   - 4.1 Diagram Use Case
   - 4.2 Deskripsi Aktor dan Fungsi
   - 4.3 Skenario Detail Setiap Use Case
5. **Kriteria Penerimaan dan Metode Pengujian**
   - 5.1 Kriteria Penerimaan
   - 5.2 Metode Pengujian (Unit, Integration, System, Performance, Security Testing)
6. **Hardware Interface**
   - 6.1 Spesifikasi Perangkat Keras Minimum dan Rekomendasi
   - 6.2 Interaksi Perangkat Lunak dengan Perangkat Keras
   - 6.3 Protokol dan Antarmuka Komunikasi Perangkat Keras
7. **Software Interface**
   - 7.1 Antarmuka API dan Komunikasi Internal (Python AI Engine ke Laravel 11)
   - 7.2 Format Data Pertukaran (JSON Schema Telemetry)
   - 7.3 Integrasi Sistem Eksternal (Gemini AI API & Baileys WhatsApp Gateway)
8. **Communication Interface**
   - 8.1 Protokol Jaringan dan Keamanan
   - 8.2 Penanganan Kesalahan dan Pemulihan Koneksi
9. **Lampiran**
   - 9.1 Glosarium Istilah Teknis
   - 9.2 Arsitektur Sistem dan Diagram Pipeline AI
10. **Persetujuan dan Revisi**
    - 10.1 Persetujuan Pemangku Kepentingan
    - 10.2 Rencana Prosedur Revisi

---

## 1. PENDAHULUAN

### 1.1 Tujuan Dokumen
Dokumen *Software Requirements Specification* (SRS) ini disusun sebagai landasan spesifikasi teknis dan acuan rekayasa perangkat lunak dalam perancangan, pengembangan, pengujian, dan penerapan **Sistem Pemantauan Presensi Pegawai dan Workstation Berbasis AI CCTV**. Sistem ini menggabungkan teknologi *Computer Vision* terkini (YOLOv8, ByteTrack, InsightFace SCRFD + ArcFace), arsitektur web modern (Laravel 11, Tailwind CSS), *Generative AI* (Google Gemini Flash), serta *Self-Hosted WhatsApp Gateway Engine* (Baileys) untuk memantau kehadiran pegawai secara otomatis, transparan, objektif, dan *real-time*.

### 1.2 Ruang Lingkup Perangkat Lunak
Sistem pemantauan ini dirancang untuk mengatasi kelemahan sistem presensi konvensional (fingerprint/RFID) yang rentan terhadap fenomena *ghost presence* (pegawai melakukan tap masuk lalu meninggalkan workstation tanpa bekerja). Ruang lingkup fungsional sistem mencakup:
1. **Pendeteksian Objek & Pelacakan Real-Time:** Mendeteksi postur tubuh bagian atas (*upper body*) manusia dan kursi kerja (*chair/workstation*) menggunakan model YOLOv8 yang diintegrasikan dengan algoritma pelacak *ByteTrack*.
2. **Pengenalan Wajah Otomatis (*Face Recognition*):** Mengidentifikasi identitas pegawai menggunakan *SCRFD Face Detector* dan ekstraksi fitur vektor *ArcFace 512-D Embeddings* dengan sistem *throttling* cerdas untuk menjaga FPS tinggi pada pemrosesan CPU.
3. **Analisis Spasial Zona & Logika Presensi:** Menghitung durasi aktif bekerja (*occupied duration*) dan durasi meninggalkan meja (*away duration*) secara independen per meja berdasarkan ambang batas IoU (*Intersection over Union*).
4. **Peringatan Otomatis via WhatsApp (Zero-Watermark):** Mengirim pesan peringatan WhatsApp otomatis ke nomor pegawai yang terdeteksi *Away* melebihi batas toleransi menit yang telah dikonfigurasi melalui *Local Baileys Gateway*.
5. **AI Assistant Kantor Interaktif (Gemini AI):** Memungkinkan staf/HRD bertanya langsung mengenai status kehadiran dan ketersediaan rekan kerja melalui chat WhatsApp dengan jawaban faktual berdasarkan telemetri CCTV live.
6. **Dashboard Manajemen Interaktif:** Menyediakan live stream video MJPEG latensi rendah, editor visual penentuan koordinat zona meja (*interactive canvas drag-and-drop*), manajemen data master pegawai, serta ekspor laporan presensi analitis.

### 1.3 Definisi, Akronim, dan Singkatan
- **SRS:** *Software Requirements Specification*, dokumen spesifikasi kebutuhan perangkat lunak.
- **YOLOv8:** *You Only Look Once version 8*, arsitektur *deep learning* mutakhir untuk deteksi objek secara *real-time*.
- **ByteTrack:** Algoritma *multi-object tracking* berbasis Kalman Filter dan asosiasi asosiatif bounding box.
- **IoU:** *Intersection over Union*, metrik evaluasi tumpang-tindih area antara bounding box orang dan zona meja.
- **SCRFD:** *Sample and Computation Redistribution for Efficient Face Detection*, model pendeteksi wajah berkecepatan tinggi.
- **ArcFace:** Model *deep convolutional neural network* untuk ekstraksi representasi wajah ke dalam vektor *cosine similarity* 512 dimensi.
- **MJPEG:** *Motion JPEG*, format streaming video berbasis rangkaian frame JPEG terkompresi melalui protokol HTTP Multipart.
- **Baileys:** Library *Node.js* berbasis WebSocket untuk menghubungkan WhatsApp Multi-Device API secara langsung tanpa layanan pihak ketiga.
- **Gemini API:** Layanan model bahasa besar (*Large Language Model*) dari Google DeepMind untuk inferensi penalaran natural.
- **REST API:** *Representational State Transfer Application Programming Interface*.

### 1.4 Referensi
- *IEEE Std 830-1998: IEEE Recommended Practice for Software Requirements Specifications.*
- *Ultralytics YOLOv8 Architecture & Deep Learning Documentation (2024).*
- *DeepInsight InsightFace: 2D and 3D Face Analysis Project (ArcFace/SCRFD).*
- *Laravel 11.x Framework Architecture and Security Standards.*

### 1.5 Gambaran Umum Sistem
Sistem ini terdiri dari 3 subsistem utama yang saling berkomunikasi:
1. **AI Vision Engine (Python 3.14 + OpenCV + PyTorch):** Mengambil frame dari RTSP/File CCTV, melakukan inferensi YOLOv8 + ByteTrack + ArcFace, mengelola *state machine* presensi zona meja, dan menyajikan stream MJPEG pada port `5000`.
2. **Web Backend & Dashboard (Laravel 11 + PHP 8 + MySQL):** Mengelola autentikasi admin, konfigurasi zona meja, master data pegawai, logging presensi, dan scheduler cron monitoring pada port `8000`.
3. **WhatsApp Gateway & Generative Assistant (Node.js Baileys + Gemini Flash):** Berjalan pada port `3000`, menangani pesan masuk/keluar WhatsApp, meneruskan query ke Laravel Webhook, dan membalas otomatis dengan kecerdasan buatan Gemini tanpa watermark.

---

## 2. DESKRIPSI UMUM

### 2.1 Perspektif Perangkat Lunak
Sistem ini memposisikan kamera CCTV yang semula bersifat pasif sebagai instrumen audit kehadiran aktif berbasis kecerdasan buatan. Sistem beroperasi secara non-invasif tanpa mengharuskan pegawai melakukan aksi manual berulang, sekaligus memberikan kepastian bagi pihak manajemen mengenai tingkat utilisasi meja kerja dan kedisiplinan jam kerja.

### 2.2 Manfaat Perangkat Lunak
- **Objektivitas Mutlak:** Menghilangkan manipulasi presensi manual maupun fenomena *ghost presence*.
- **Peringatan Preventif:** Mengingatkan pegawai secara santun dan otomatis via WhatsApp jika meninggalkan meja kerja melebihi batas waktu yang disepakati.
- **Transparansi Tim:** Memfasilitasi pimpinan atau rekan kerja untuk memeriksa keberadaan anggota tim melalui WhatsApp AI bot tanpa perlu mencari manual ke ruangan.
- **Efisiensi Operasional:** 100% mandiri (*self-hosted*), tanpa biaya sewa gateway pihak ketiga dan tanpa ketergantungan koneksi berbayar bulanan.

### 2.3 Fungsi Utama Perangkat Lunak
1. Streaming CCTV *real-time* dengan *framerate* tinggi (>= 20 FPS).
2. Deteksi otomatis status *BEKERJA* atau *TIDAK DI TEMPAT* per workstation.
3. Otentikasi pengenalan wajah biometrik pegawai yang duduk di workstation.
4. Pengiriman notifikasi darurat/peringatan WhatsApp berbasis toleransi waktu.
5. Layanan chatbot tanya-jawab status presensi berbasis Gemini AI.
6. Editor kanvas pembuatan dan penyesuaian posisi meja kerja secara visual.

### 2.4 Penggolongan Karakteristik Pengguna

| Kategori Pengguna | Tugas & Tanggung Jawab | Hak Akses Sistem | Keterampilan yang Diperlukan |
| :--- | :--- | :--- | :--- |
| **Super Admin / Manajer HRD** | Mengonfigurasi zona meja, mengelola data pegawai, mengatur batas toleransi *away*, memantau dashboard, dan mengekspor laporan. | Akses penuh (*Full Access*) ke Web Dashboard Admin, database, dan konfigurasi AI. | Pengoperasian browser web, pemahaman regulasi jam kerja kantor. |
| **Pegawai / Karyawan** | Bekerja di workstation masing-masing, menerima notifikasi WhatsApp, dan berinteraksi dengan AI Assistant. | Tidak memiliki hak login admin; menerima pesan WA dan dapat mengirim pertanyaan ke bot WA. | Penggunaan aplikasi pesan instan WhatsApp pada smartphone. |

### 2.5 Kriteria Keberhasilan
- Sistem mampu mendeteksi kehadiran orang pada meja kerja dengan akurasi >= 95%.
- Kecepatan pemrosesan frame video mencapai minimal 18–24 FPS pada CPU standar.
- Pengenalan wajah ArcFace berhasil mengidentifikasi pegawai terdaftar dengan akurasi >= 90% pada pencahayaan normal.
- Notifikasi WhatsApp terkirim ke nomor pegawai dalam waktu < 10 detik sejak ambang batas waktu *away* terlampaui.
- AI Assistant Gemini memberikan respon akurat yang sesuai dengan data telemetri CCTV dalam waktu < 4 detik.

### 2.6 Batasan Sistem
- Sudut pandang kamera CCTV harus mencakup area meja kerja dan kepala/bahu pegawai dengan jelas tanpa terhalang pilar permanen.
- Pengenalan wajah optimal membutuhkan resolusi wajah minimal 50x50 piksel pada frame video.
- Nomor WhatsApp pegawai harus dalam format aktif yang terdaftar di jaringan seluler.

### 2.7 Asumsi dan Ketergantungan
- Kamera CCTV terhubung pada jaringan lokal yang sama atau dapat diakses via protokol RTSP/file video.
- Server komputer memiliki sistem operasi Windows 10/11 atau Linux 64-bit dengan lingkungan PHP 8.2+, Node.js 18+, dan Python 3.10+.
- Koneksi internet aktif tersedia untuk memanggil Google Gemini API.

---

## 3. SPESIFIKASI KEBUTUHAN

### 3.1 Kebutuhan Fungsional

| ID | User Story | Acceptance Criteria |
| :--- | :--- | :--- |
| **F-01** | Sebagai Admin, saya ingin melihat video stream CCTV secara live dengan bounding box deteksi status meja. | 1. Sistem menyajikan video MJPEG dengan latensi rendah (< 300ms).<br>2. Setiap meja diberi garis kotak (Hijau = Bekerja, Merah = Tidak di Tempat). |
| **F-02** | Sebagai Admin, saya dapat membuat, menggeser, dan menghapus zona meja langsung pada snapshot CCTV. | 1. Admin dapat *click-and-drag* mouse di kanvas web untuk membuat kotak meja baru.<br>2. Posisi koordinat langsung tersimpan ke backend MySQL dan file konfigurasi AI Engine. |
| **F-03** | Sebagai Admin, saya dapat mendaftarkan data pegawai beserta nomor WhatsApp dan foto wajah selfie. | 1. Form input mencakup Nama, Jabatan, Nomor WA, Meja Kerja, dan Batas Toleransi Away (Menit).<br>2. Upload foto otomatis diekstrak vektor wajahnya ke database biometrik InsightFace. |
| **F-04** | Sebagai Sistem AI, sistem harus dapat melacak durasi bekerja dan durasi meninggalkan meja secara akurat. | 1. Durasi bekerja bertambah saat ada orang terdeteksi di dalam zona meja.<br>2. Durasi *away* mulai dihitung saat meja ditinggalkan kosong. |
| **F-05** | Sebagai Sistem, sistem harus mengirim pesan peringatan WhatsApp saat pegawai melewati batas waktu toleransi. | 1. Sistem memeriksa status meja setiap 15 detik.<br>2. Jika durasi *away* >= batas toleransi pegawai, notifikasi WhatsApp langsung dikirim ke nomor yang bersangkutan.<br>3. Terdapat fitur jeda anti-spam (*cooldown*) 3 menit. |
| **F-06** | Sebagai Pegawai/Admin, saya dapat bertanya tentang kondisi kehadiran kantor kepada nomor WhatsApp bot. | 1. Pesan WhatsApp masuk ditangkap oleh gateway Baileys secara lokal.<br>2. Gemini AI merespon dengan data aktual CCTV tanpa emoji dan gaya bahasa profesional. |
| **F-07** | Sebagai Admin, saya dapat menyalakan seluruh subsistem monitoring hanya dengan 1 perintah terminal. | 1. Perintah `php artisan monitor:start` menjalankan Laravel serve, Python stream server, dan Baileys WhatsApp Gateway secara serentak. |

### 3.2 Kebutuhan Non-Fungsional

| ID | Parameter | Spesifikasi Kebutuhan |
| :--- | :--- | :--- |
| **NF-01** | **Performance & FPS** | Pipeline Computer Vision dioptimasi dengan *face check throttling* sehingga mampu menghasilkan framerate stabil 20–25 FPS pada CPU. |
| **NF-02** | **Zero-Watermark Integrity** | Pengiriman pesan WhatsApp menggunakan engine *Baileys* lokal sehingga 100% bebas dari watermark atau footer pihak ketiga. |
| **NF-03** | **Low Latency Streaming** | Streaming MJPEG menggunakan mekanisme *latest frame sequencing* untuk mencegah penumpukan buffer video di browser. |
| **NF-04** | **Security & Privacy** | Password akun admin dienkripsi menggunakan algoritma *Bcrypt*, serta API Key Gemini disimpan aman di dalam environment variable `.env`. |
| **NF-05** | **Reliability & Auto-Recovery** | WhatsApp Gateway Baileys dilengkapi *auto-reconnect handler* dan *EPIPE broken pipe safety* agar tidak crash saat stdout background tertutup. |
| **NF-06** | **Scalability** | Struktur database dirancang modular dengan relasi foreign key antara tabel `employees`, `workstation_zones`, dan `presence_notification_logs`. |

---

## 4. USE CASE DIAGRAM DAN SKENARIO

### 4.1 Deskripsi Aktor

| No | Aktor | Deskripsi Peran |
| :--- | :--- | :--- |
| 1 | **Super Admin / HRD** | Pengelola utama aplikasi web yang bertugas mengatur zona meja, data pegawai, dan mengevaluasi laporan presensi. |
| 2 | **Pegawai / Karyawan** | Subjek yang dipantau kehadirannya di meja kerja, penerima notifikasi WA, dan pengguna chatbot presensi. |
| 3 | **AI Vision & Gateway Engine** | Aktor sistem cerdas yang mendeteksi objek, mengenali wajah, menghitung durasi, dan membalas chat secara otomatis. |

### 4.2 Skenario Detail Use Case

#### Skenario 1: Peringatan Otomatis Melewati Batas Waktu Meja (UC-AWAY-ALERT)
- **ID:** UC-01
- **Nama Use Case:** Pengiriman Notifikasi Peringatan Melewati Batas Away
- **Aktor Utama:** AI Vision Engine, WhatsApp Gateway
- **Aktor Sekunder:** Pegawai Terkait
- **Kondisi Awal:** Pegawai terdaftar di database dengan nomor WhatsApp aktif dan batas toleransi meja (misal: 15 menit).
- **Trigger:** Pegawai terdeteksi meninggalkan workstation (*Status: TIDAK DI TEMPAT*) selama durasi >= batas toleransi.
- **Alur Normal:**
  1. Background Scheduler Laravel (`presence:check-away`) mengambil data telemetri dari `http://localhost:5000/api/status`.
  2. Sistem membandingkan `away_duration_seconds` pada zona meja dengan `max_away_minutes` pegawai.
  3. Sistem memverifikasi bahwa pegawai belum menerima notifikasi dalam masa *cooldown* 3 menit terakhir.
  4. Sistem mengirimkan *payload* pesan peringatan ke WhatsApp Local Gateway (`http://localhost:3000/send`).
  5. WhatsApp Gateway mengirim pesan WhatsApp resmi ke nomor pegawai tanpa watermark.
  6. Sistem mencatat log pengiriman ke tabel `presence_notification_logs`.
- **Alur Alternatif:**
  - Jika nomor WhatsApp pegawai kosong atau tidak valid, sistem membatalkan pengiriman dan mencatat status `FAILED` di log.
  - Jika pegawai kembali ke meja sebelum batas menit tercapai, penghitung durasi *away* di-reset menjadi 0 dan notifikasi tidak dikirim.
- **Kondisi Akhir:** Pegawai menerima pesan WhatsApp peringatan dan log tercatat di sistem.

#### Skenario 2: Tanya Jawab Status Presensi via WhatsApp AI Bot (UC-GEMINI-CHAT)
- **ID:** UC-02
- **Nama Use Case:** Interaksi Asisten AI Presensi via WhatsApp
- **Aktor Utama:** Pegawai / Admin
- **Aktor Sekunder:** Local WhatsApp Gateway, Gemini AI Service, Laravel Webhook
- **Kondisi Awal:** WhatsApp Gateway (`server.js`) terhubung ke WhatsApp dan Laravel aktif.
- **Trigger:** Pengguna mengirim pesan teks ke nomor WhatsApp bot (contoh: *"Siapa saja yang ada di kantor?"*).
- **Alur Normal:**
  1. WhatsApp Gateway menangkap event `messages.upsert` dan meneruskan isi pesan beserta nomor pengirim ke Webhook Laravel (`POST /api/whatsapp/webhook`).
  2. Controller memanggil `GeminiService::askAssistant()`.
  3. `GeminiService` mengambil status *live* dari Python CCTV (`/api/status`) dan daftar pegawai dari MySQL.
  4. Sistem menyusun *System Prompt* profesional dengan aturan ketat **0% Emoji** dan menyertakan data faktual CCTV.
  5. Google Gemini Flash memproses inferensi dan menghasilkan jawaban ringkas dan akurat dalam waktu < 3 detik.
  6. Laravel mengembalikan teks jawaban ke WhatsApp Gateway, lalu dikirimkan langsung ke nomor penanya.
- **Kondisi Akhir:** Penanya menerima balasan WhatsApp informatif dan akurat sesuai fakta visual CCTV.

---

## 5. KRITERIA PENERIMAAN DAN PENGUJIAN

### 5.1 Kriteria Penerimaan Sistem
1. **Autentikasi & Keamanan:** Akses dashboard dibatasi hanya untuk akun admin yang valid dengan proteksi CSRF token di seluruh formulir.
2. **Ketepatan Deteksi Meja:** Perubahan status dari *BEKERJA* menjadi *TIDAK DI TEMPAT* terjadi secara konsisten ketika nilai IoU postur tubuh terhadap zona meja < ambang batas selama buffer waktu tertentu.
3. **Stabilitas Layanan Gateway:** WhatsApp Gateway mampu mengirim dan menerima pesan secara terus menerus tanpa terputus (*EPIPE-safe*).
4. **Respon Gemini AI Bebas Halusinasi:** AI hanya menjawab status berdasarkan data telemetri aktual CCTV yang disuntikkan ke dalam *system context*.

### 5.2 Metode Pengujian

| Jenis Pengujian | Tujuan Pengujian | Pendekatan | Deskripsi Skenario Pengujian |
| :--- | :--- | :--- | :--- |
| **Unit Testing** | Menguji kebenaran logika fungsi internal. | *White Box Testing* | Menguji format nomor telepon `formatPhoneNumber()`, parsing bounding box IoU di Python, dan kalkulasi menit *away*. |
| **Integration Testing** | Memverifikasi komunikasi antar subsistem. | *White Box & Black Box* | Menguji aliran data: Deteksi Python -> Endpoint `/api/status` -> Command Laravel -> HTTP POST Gateway Baileys -> Pengiriman WhatsApp. |
| **System Testing** | Memastikan keseluruhan alur aplikasi berjalan sesuai SRS. | *Black Box Testing* | Pengujian end-to-end: Admin menggambar meja baru di kanvas -> menetapkan pegawai Bili -> menyimulasikan Bili meninggalkan meja -> memverifikasi WA masuk di HP Bili. |
| **Performance Testing** | Mengukur kestabilan beban dan framerate streaming. | *Stress & Benchmark* | Mengukur konsumsi CPU/RAM dan kestabilan FPS saat 6 meja dipantau bersamaan selama 24 jam nonstop. |
| **Security Testing** | Menjamin keamanan data dan kredensial sistem. | *Vulnerability Test* | Pengujian sanitasi input SQL Injection, XSS, serta verifikasi bahwa token API tidak terekspos ke antarmuka publik. |

---

## 6. HARDWARE INTERFACE

### 6.1 Spesifikasi Perangkat Keras

| Komponen | Spesifikasi Minimum | Spesifikasi Rekomendasi |
| :--- | :--- | :--- |
| **Prosesor (CPU)** | Intel Core i5 Gen 8 / AMD Ryzen 5 (4 Cores, 2.5 GHz) | Intel Core i7 Gen 11+ / AMD Ryzen 7 (8 Cores, 3.5 GHz+) |
| **GPU Acceleration** | Intel UHD Graphics (CPU Mode) | NVIDIA GeForce GTX 1650 / RTX 3060 (CUDA Support) |
| **Memori (RAM)** | 8 GB DDR4 | 16 GB DDR4/DDR5 |
| **Penyimpanan** | 500 MB ruang kosong untuk aplikasi & database | 10 GB SSD NVMe (untuk penyimpanan histori log & rekaman) |
| **Kamera CCTV** | IP Camera 720p @ 15 FPS (RTSP Stream) | IP Camera 1080p Full HD @ 25–30 FPS (H.264/H.265 RTSP) |
| **Jaringan** | Wi-Fi / LAN 100 Mbps | Gigabit Ethernet (1000 Mbps) stabil |

---

## 7. SOFTWARE INTERFACE

### 7.1 Skema Pertukaran Data Telemetri Internal (Python -> Laravel)
Python Stream Engine mengekspos endpoint status terstruktur pada `GET http://127.0.0.1:5000/api/status` dengan format JSON:

```json
{
  "fps": 22.4,
  "status": "online",
  "total_bekerja": 5,
  "total_away": 1,
  "zones": {
    "chair_1": {
      "zone_id": "chair_1",
      "status": "BEKERJA",
      "occupied_duration": 2450.5,
      "away_duration_seconds": 0.0,
      "verified_employee_name": "Gea",
      "chair_bbox": [320, 158, 408, 279]
    },
    "chair_3": {
      "zone_id": "chair_3",
      "status": "TIDAK_DI_TEMPAT",
      "occupied_duration": 1200.0,
      "away_duration_seconds": 960.0,
      "verified_employee_name": "Bili",
      "chair_bbox": [443, 175, 525, 305]
    }
  }
}
```

### 7.2 Integrasi Google Gemini Generative AI
- **Endpoint:** `https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={GEMINI_API_KEY}`
- **Konfigurasi Parameter:** `temperature: 0.1`, `maxOutputTokens: 1500`.
- **System Instruction:** Menyuntikkan fakta telemetri CCTV terkini dan menegakkan aturan respon bebas emoji (*Zero-Emoji Policy*).

---

## 8. COMMUNICATION INTERFACE

1. **HTTP/HTTPS (Port 8000 & 5000):** Digunakan untuk komunikasi REST API antara Laravel dan Python Engine, serta transmisi video MJPEG.
2. **WebSocket TLS (Port 443):** Digunakan oleh Baileys untuk menjaga koneksi soket dua arah (*bidirectional socket*) dengan WhatsApp Multi-Device Server.

---

## 9. LAMPIRAN

### 9.1 Glosarium Istilah
- **Bounding Box:** Koordinat area persegi panjang `[x1, y1, x2, y2]` yang melingkupi objek terdeteksi.
- **ArcFace Cosine Similarity:** Perhitungan derajat kemiripan antara dua vektor biometrik wajah dalam ruang dimensi tinggi (rentang -1 hingga 1, ambang kecocokan >= 0.45).
- **Throttling Face Check:** Mekanisme pembatasan frekuensi inferensi wajah (misal: hanya diperiksa tiap 1.5–8.0 detik per kursi) untuk menghemat beban CPU secara drastis.

---

## 10. PERSETUJUAN DAN REVISI

### 10.1 Persetujuan Pemangku Kepentingan

*Subang, 24 Agustus 2026*

Dokumen *Software Requirements Specification* (SRS) ini telah disusun, ditinjau secara menyeluruh, dan disetujui sebagai pedoman resmi pengembangan **Sistem Pemantauan Presensi Pegawai dan Workstation Berbasis AI CCTV**:

| Peran Jabatan | Nama Lengkap | Tanda Tangan / Persetujuan |
| :--- | :--- | :--- |
| **Lead Software Engineer & Researcher** | **Kanisius Rangga (10601018)** | *Disetujui* |
| **System Architect & Co-Author** | **Ridho Hasbi Ashiddiq (10601031)** | *Disetujui* |
| **Quality Assurance & Co-Author** | **Fathan M.J (10601015)** | *Disetujui* |

---
*© 2026 Apex Development - Program Studi D4 Rekayasa Perangkat Lunak, Politeknik Negeri Subang.*
