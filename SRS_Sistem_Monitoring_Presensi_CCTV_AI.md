# SOFTWARE REQUIREMENTS SPECIFICATION (SRS)

## SISTEM PEMANTAUAN PRESENSI PEGAWAI DAN WORKSTATION BERBASIS COMPUTER VISION (YOLO11 + ARCFACE) DENGAN ASISTEN AI GEMINI DAN WHATSAPP GATEWAY

**Version 1.0 Approved**

**Disusun Oleh:**  
**Kanisius Rangga (10601018)**  

Program Studi D4 Teknologi Rekayasa Perangkat Lunak  
Jurusan Teknologi Informasi dan Komputer  
**Politeknik Negeri Subang**  
**2026**  

---

## DAFTAR ISI

1. **Pendahuluan** .................................................................................................................... 1  
   1.1 Tujuan Dokumen ...................................................................................................... 1  
   1.2 Ruang Lingkup Perangkat Lunak ............................................................................. 1  
   1.3 Definisi, Akronim, dan Singkatan ............................................................................ 2  
   1.4 Referensi ................................................................................................................... 2  
   1.5 Gambaran Umum Sistem ......................................................................................... 3  
2. **Deskripsi Umum** ............................................................................................................. 3  
   2.1 Perspektif Perangkat Lunak ...................................................................................... 3  
   2.2 Manfaat Perangkat Lunak ........................................................................................ 3  
   2.3 Fungsi Utama Perangkat Lunak ............................................................................... 4  
   2.4 Penggolongan Karakteristik Pengguna .................................................................... 4  
   2.5 Kriteria Keberhasilan ............................................................................................... 4  
   2.6 Batasan Sistem ......................................................................................................... 5  
   2.7 Asumsi dan Ketergantungan .................................................................................... 5  
3. **Spesifikasi Kebutuhan** ..................................................................................................... 6  
   3.1 Kebutuhan Fungsional .............................................................................................. 6  
   3.2 Kebutuhan Non-Fungsional ..................................................................................... 7  
4. **Use Case Diagram dan Skenario** .................................................................................... 8  
   4.1 Deskripsi Aktor ........................................................................................................ 8  
   4.2 Skenario Detail Use Case ......................................................................................... 8  
5. **Kriteria Penerimaan dan Pengujian** ............................................................................ 10  
   5.1 Kriteria Penerimaan Sistem .................................................................................... 10  
   5.2 Metode Pengujian ................................................................................................... 10  
6. **Hardware Interface** ...................................................................................................... 11  
   6.1 Spesifikasi Perangkat Keras ................................................................................... 11  
7. **Software Interface** ........................................................................................................ 12  
   7.1 Skema Pertukaran Data Telemetri Internal ............................................................ 12  
   7.2 Integrasi Google Gemini Generative AI ................................................................ 12  
8. **Communication Interface** ............................................................................................ 13  
9. **Lampiran** ........................................................................................................................ 13  
   9.1 Glosarium Istilah .................................................................................................... 13  
10. **Persetujuan dan Revisi** ................................................................................................ 14  
    10.1 Persetujuan Pemangku Kepentingan .................................................................... 14  

---

## 1. PENDAHULUAN

### 1.1 Tujuan Dokumen
Dokumen *Software Requirements Specification* (SRS) ini disusun sebagai landasan spesifikasi teknis dan acuan rekayasa perangkat lunak dalam perancangan, pengembangan, pengujian, dan penerapan **Sistem Pemantauan Presensi Pegawai dan Workstation Berbasis AI CCTV**. Sistem ini menggabungkan teknologi *computer vision* terkini (**YOLO11**, **InsightFace SCRFD + ArcFace**), arsitektur web modern (**Laravel 11, Tailwind CSS**), *generative AI* (**Google Gemini Flash**), serta *self-hosted WhatsApp gateway engine* (**Baileys**) untuk memantau kehadiran pegawai secara otomatis, transparan, objektif, dan *real-time*.

### 1.2 Ruang Lingkup Perangkat Lunak
Sistem pemantauan ini dirancang untuk mengatasi kelemahan sistem presensi konvensional (*fingerprint/RFID*) yang rentan terhadap fenomena *ghost presence*, yaitu kondisi ketika pegawai melakukan *tap* masuk lalu meninggalkan *workstation* tanpa bekerja. Ruang lingkup fungsional sistem mencakup hal-hal berikut:
1. **Pendeteksian Objek Real-Time**: mendeteksi postur tubuh bagian atas (*upper body*) manusia dan area kerja (*workstation/chair*) menggunakan model **YOLO11** dengan evaluasi spasial murni per-frame dan *debounce state machine*.
2. **Pengenalan Wajah Otomatis (Face Recognition)**: mengidentifikasi identitas pegawai menggunakan SCRFD *face detector* dan ekstraksi fitur vektor ArcFace 512-D *embeddings* dengan sistem *asynchronous worker* dan *round-robin throttling* untuk menjaga FPS tinggi pada CPU.
3. **Analisis Spasial Zona dan Logika Presensi**: menghitung durasi aktif bekerja (*occupied duration*) dan durasi meninggalkan meja (*away duration*) secara independen per meja berdasarkan metrik gabungan IoU (*Intersection over Union*), *containment ratio*, dan titik *centroid*.
4. **Peringatan Otomatis via WhatsApp (Zero-Watermark)**: mengirim pesan peringatan WhatsApp otomatis ke nomor pegawai yang terdeteksi *away* melebihi batas toleransi menit yang telah dikonfigurasi melalui *local Baileys gateway*.
5. **AI Assistant Kantor Interaktif (Gemini AI)**: memfasilitasi staf/HRD bertanya langsung mengenai status kehadiran dan ketersediaan rekan kerja melalui chat WhatsApp dengan jawaban faktual berdasarkan telemetri CCTV *live*.
6. **Dashboard Manajemen Interaktif**: menyediakan *live stream* video MJPEG latensi rendah, editor visual penentuan koordinat zona meja (*interactive canvas drag-and-drop*), manajemen data master pegawai, serta ekspor laporan presensi analitis.

### 1.3 Definisi, Akronim, dan Singkatan
a. **SRS**: *Software Requirements Specification*, dokumen spesifikasi kebutuhan perangkat lunak.  
b. **YOLO11**: *You Only Look Once version 11 (Ultralytics)*, arsitektur model *deep learning* mutakhir generasi terbaru untuk deteksi objek secara *real-time* dengan efisiensi tinggi pada komputasi CPU.  
c. **IoU**: *Intersection over Union*, metrik evaluasi tumpang-tindih area antara *bounding box* orang dan zona meja.  
d. **SCRFD**: *Sample and Computation Redistribution for Efficient Face Detection*, model pendeteksi wajah berkecepatan tinggi.  
e. **ArcFace**: model *deep convolutional neural network* untuk ekstraksi representasi wajah ke dalam vektor *cosine similarity* 512 dimensi.  
f. **MJPEG**: *Motion JPEG*, format *streaming* video berbasis rangkaian frame JPEG terkompresi melalui protokol HTTP *multipart*.  
g. **Baileys**: *library* Node.js berbasis WebSocket untuk menghubungkan WhatsApp *Multi-Device API* secara langsung tanpa layanan pihak ketiga.  
h. **Gemini API**: layanan model bahasa besar (*large language model*) dari Google DeepMind untuk inferensi penalaran natural.  
i. **REST API**: *Representational State Transfer Application Programming Interface*.  

### 1.4 Referensi
a. IEEE Std 830-1998: *IEEE Recommended Practice for Software Requirements Specifications*.  
b. Ultralytics YOLO11 Architecture and Computer Vision Documentation (2024/2026).  
c. DeepInsight InsightFace: 2D and 3D Face Analysis Project (ArcFace/SCRFD).  
d. Laravel 11.x Framework Architecture and Security Standards.  

### 1.5 Gambaran Umum Sistem
Sistem ini terdiri dari tiga subsistem utama yang saling berkomunikasi sebagai berikut:
1. **AI Vision Engine (Python 3.14 + OpenCV + PyTorch)**: mengambil frame dari RTSP/file CCTV, melakukan inferensi **YOLO11** dan **ArcFace**, mengelola *state machine* presensi zona meja, serta menyajikan stream MJPEG pada port 5000.
2. **Web Backend dan Dashboard (Laravel 11 + PHP 8.2 + MySQL)**: mengelola autentikasi admin, konfigurasi zona meja, master data pegawai, *logging* presensi, dan *scheduler cron monitoring* pada port 8000.
3. **WhatsApp Gateway dan Generative Assistant (Node.js Baileys + Gemini Flash)**: berjalan pada port 3000, menangani pesan masuk/keluar WhatsApp, meneruskan query ke Laravel webhook, dan membalas otomatis dengan kecerdasan buatan Gemini.

---

## 2. DESKRIPSI UMUM

### 2.1 Perspektif Perangkat Lunak
Sistem ini memposisikan kamera CCTV yang semula bersifat pasif sebagai instrumen audit kehadiran aktif berbasis kecerdasan buatan. Sistem beroperasi secara non-invasif tanpa mengharuskan pegawai melakukan aksi manual berulang, sekaligus memberikan kepastian bagi pihak manajemen mengenai tingkat utilisasi meja kerja dan kedisiplinan jam kerja.

### 2.2 Manfaat Perangkat Lunak
a. **Objektivitas mutlak**: menghilangkan manipulasi presensi manual maupun fenomena *ghost presence*.  
b. **Peringatan preventif**: mengingatkan pegawai secara santun dan otomatis melalui WhatsApp jika meninggalkan meja kerja melebihi batas waktu yang disepakati.  
c. **Transparansi tim**: memfasilitasi pimpinan atau rekan kerja untuk memeriksa keberadaan anggota tim melalui WhatsApp AI bot tanpa perlu mencari manual ke ruangan.  
d. **Efisiensi operasional**: bersifat sepenuhnya mandiri (*self-hosted*), tanpa biaya sewa gateway pihak ketiga dan tanpa ketergantungan koneksi berbayar bulanan.  

### 2.3 Fungsi Utama Perangkat Lunak
1. *Streaming* CCTV *real-time* dengan framerate stabil (>= 20 FPS).
2. Deteksi otomatis status Bekerja atau Tidak di Tempat pada setiap *workstation* menggunakan **YOLO11**.
3. Otentikasi pengenalan wajah biometrik pegawai yang duduk di *workstation* menggunakan ArcFace 512-D.
4. Pengiriman notifikasi darurat/peringatan WhatsApp berbasis toleransi waktu.
5. Layanan chatbot tanya-jawab status presensi berbasis Gemini AI.
6. Editor kanvas pembuatan dan penyesuaian posisi meja kerja secara visual di web admin.

### 2.4 Penggolongan Karakteristik Pengguna
**Tabel 2.1 Karakteristik Pengguna**
| Kategori Pengguna | Tugas dan Tanggung Jawab | Hak Akses Sistem | Keterampilan yang Diperlukan |
| :--- | :--- | :--- | :--- |
| **Super Admin / Manajer HRD** | Mengonfigurasi zona meja, mengelola data pegawai, mengatur batas toleransi away, memantau dashboard, dan mengekspor laporan. | Akses penuh (*full access*) ke web dashboard admin, database, dan konfigurasi AI. | Pengoperasian browser web, pemahaman regulasi jam kerja kantor. |
| **Pegawai / Karyawan** | Bekerja di *workstation* masing-masing, menerima notifikasi WhatsApp, dan berinteraksi dengan AI Assistant. | Tidak memiliki hak login admin; menerima pesan WA dan dapat mengirim pertanyaan ke bot WA. | Penggunaan aplikasi pesan instan WhatsApp pada telepon pintar (*smartphone*). |

### 2.5 Kriteria Keberhasilan
a. Sistem mampu mendeteksi kehadiran orang pada meja kerja dengan akurasi >= 95%.  
b. Kecepatan pemrosesan frame video mencapai minimal 18–25 FPS pada CPU standar.  
c. Pengenalan wajah ArcFace berhasil mengidentifikasi pegawai terdaftar dengan akurasi >= 90% pada pencahayaan normal.  
d. Notifikasi WhatsApp terkirim ke nomor pegawai dalam waktu kurang dari 10 detik sejak ambang batas waktu *away* terlampaui.  
e. AI Assistant Gemini memberikan respon akurat yang sesuai dengan data telemetri CCTV dalam waktu kurang dari 4 detik.  

### 2.6 Batasan Sistem
a. Sudut pandang kamera CCTV harus mencakup area meja kerja dan kepala/bahu pegawai dengan jelas tanpa terhalang pilar permanen.  
b. Pengenalan wajah optimal membutuhkan resolusi wajah minimal 50x50 piksel pada frame video.  
c. Nomor WhatsApp pegawai harus dalam format aktif yang terdaftar di jaringan seluler.  

### 2.7 Asumsi dan Ketergantungan
a. Kamera CCTV terhubung pada jaringan lokal yang sama atau dapat diakses melalui protokol RTSP/file video.  
b. Server komputer memiliki sistem operasi Windows 10/11 atau Linux 64-bit dengan lingkungan PHP 8.2+, Node.js 18+, dan Python 3.10+.  
c. Koneksi internet aktif tersedia untuk memanggil Google Gemini API.  

---

## 3. SPESIFIKASI KEBUTUHAN

### 3.1 Kebutuhan Fungsional
**Tabel 3.1 Kebutuhan Fungsional (User Story dan Acceptance Criteria)**
| ID | User Story | Acceptance Criteria |
| :--- | :--- | :--- |
| **F-01** | Sebagai admin, saya ingin melihat video stream CCTV secara live dengan bounding box deteksi status meja. | 1. Sistem menyajikan video MJPEG dengan latensi rendah (< 300 ms).<br>2. Setiap meja diberi garis kotak (hijau = bekerja, merah = tidak di tempat). |
| **F-02** | Sebagai admin, saya dapat membuat, menggeser, dan menghapus zona meja langsung pada snapshot CCTV. | 1. Admin dapat *click-and-drag* mouse di kanvas web untuk membuat kotak meja baru.<br>2. Posisi koordinat langsung tersimpan ke backend MySQL dan file konfigurasi AI engine. |
| **F-03** | Sebagai admin, saya dapat mendaftarkan data pegawai beserta nomor WhatsApp dan foto wajah selfie. | 1. Form input mencakup nama, jabatan, nomor WA, meja kerja, dan batas toleransi away (menit).<br>2. Unggahan foto otomatis diekstrak vektor wajahnya ke database biometrik InsightFace. |
| **F-04** | Sebagai sistem AI, sistem harus dapat melacak durasi bekerja dan durasi meninggalkan meja secara akurat. | 1. Durasi bekerja bertambah saat ada orang terdeteksi di dalam zona meja.<br>2. Durasi away mulai dihitung saat meja ditinggalkan kosong. |
| **F-05** | Sebagai sistem, sistem harus mengirim pesan peringatan WhatsApp saat pegawai melewati batas waktu toleransi. | 1. Sistem memeriksa status meja secara berkala.<br>2. Jika durasi away >= batas toleransi pegawai, notifikasi WhatsApp langsung dikirim ke nomor yang bersangkutan.<br>3. Terdapat fitur jeda anti-spam (*cooldown*). |
| **F-06** | Sebagai pegawai/admin, saya dapat bertanya tentang kondisi kehadiran kantor kepada nomor WhatsApp bot. | 1. Pesan WhatsApp masuk ditangkap oleh gateway Baileys secara lokal.<br>2. Gemini AI merespon dengan data aktual CCTV tanpa emoji dan dengan gaya bahasa profesional. |
| **F-07** | Sebagai admin, saya dapat menyalakan seluruh subsistem monitoring hanya dengan satu perintah terminal. | 1. Perintah `php artisan monitor:start` menjalankan Laravel serve, Python stream server, dan Baileys WhatsApp gateway secara serentak. |

### 3.2 Kebutuhan Non-Fungsional
**Tabel 3.2 Kebutuhan Non-Fungsional**
| ID | Parameter | Spesifikasi Kebutuhan |
| :--- | :--- | :--- |
| **NF-01** | *Performance and FPS* | Pipeline computer vision dioptimasi dengan *asynchronous face check worker* dan *round-robin* sehingga menghasilkan framerate stabil 20–25 FPS pada CPU. |
| **NF-02** | *Zero-Watermark Integrity* | Pengiriman pesan WhatsApp menggunakan engine Baileys lokal sehingga sepenuhnya bebas dari watermark atau footer pihak ketiga. |
| **NF-03** | *Low Latency Streaming* | Streaming MJPEG menggunakan mekanisme *latest frame sequencing* untuk mencegah penumpukan buffer video di browser. |
| **NF-04** | *Security and Privacy* | Kata sandi akun admin dienkripsi menggunakan algoritma Bcrypt, serta API key Gemini disimpan aman di dalam environment variable `.env`. |
| **NF-05** | *Reliability and Auto-Recovery* | WhatsApp gateway Baileys dilengkapi *auto-reconnect handler* dan *EPIPE broken pipe safety* agar tidak crash saat stdout background tertutup. |
| **NF-06** | *Scalability* | Struktur database dirancang modular dengan relasi *foreign key* antara tabel `employees`, `workstation_zones`, dan `presence_notification_logs`. |

---

## 4. USE CASE DIAGRAM DAN SKENARIO

### 4.1 Deskripsi Aktor
**Tabel 4.1 Deskripsi Aktor**
| No | Aktor | Deskripsi Peran |
| :--- | :--- | :--- |
| 1 | **Super Admin / HRD** | Pengelola utama aplikasi web yang bertugas mengatur zona meja, data pegawai, dan mengevaluasi laporan presensi. |
| 2 | **Pegawai / Karyawan** | Subjek yang dipantau kehadirannya di meja kerja, penerima notifikasi WA, dan pengguna chatbot presensi. |
| 3 | **AI Vision and Gateway Engine** | Aktor sistem cerdas yang mendeteksi objek dengan YOLO11, mengenali wajah, menghitung durasi, dan membalas chat secara otomatis. |

### 4.2 Skenario Detail Use Case

#### Skenario 1: Peringatan Otomatis Melewati Batas Waktu Meja (UC-01)
- **Nama Use Case:** Pengiriman Notifikasi Peringatan Melewati Batas Away  
- **Aktor Utama:** AI Vision Engine, WhatsApp Gateway  
- **Aktor Sekunder:** Pegawai Terkait  
- **Kondisi Awal:** Pegawai terdaftar di database dengan nomor WhatsApp aktif dan batas toleransi meja (misal: 15 menit).  
- **Trigger:** Pegawai terdeteksi meninggalkan *workstation* (status: Tidak di Tempat) selama durasi >= batas toleransi.  
- **Alur Normal:**
  1. Background scheduler Laravel (`presence:check-away`) mengambil data telemetri dari `http://localhost:5000/api/status`.
  2. Sistem membandingkan `away_duration_seconds` pada zona meja dengan `max_away_minutes` pegawai.
  3. Sistem memverifikasi bahwa pegawai belum menerima notifikasi dalam masa cooldown.
  4. Sistem mengirimkan payload pesan peringatan ke WhatsApp local gateway (`http://localhost:3000/send-message`).
  5. WhatsApp gateway mengirim pesan WhatsApp resmi ke nomor pegawai tanpa watermark.
  6. Sistem mencatat log pengiriman ke tabel `presence_notification_logs`.
- **Alur Alternatif:**
  - a. Jika nomor WhatsApp pegawai kosong atau tidak valid, sistem membatalkan pengiriman dan mencatat status `FAILED` pada log.
  - b. Jika pegawai kembali ke meja sebelum batas menit tercapai, penghitung durasi away direset dan notifikasi tidak dikirim.
- **Kondisi Akhir:** Pegawai menerima pesan WhatsApp peringatan dan log tercatat di sistem.

#### Skenario 2: Tanya Jawab Status Presensi via WhatsApp AI Bot (UC-02)
- **Nama Use Case:** Interaksi Asisten AI Presensi via WhatsApp  
- **Aktor Utama:** Pegawai / Admin  
- **Aktor Sekunder:** Local WhatsApp Gateway, Gemini AI Service, Laravel Webhook  
- **Kondisi Awal:** WhatsApp gateway terhubung ke WhatsApp dan Laravel aktif.  
- **Trigger:** Pengguna mengirim pesan teks ke nomor WhatsApp bot, misalnya *"Siapa saja yang ada di kantor?"*.  
- **Alur Normal:**
  1. WhatsApp gateway menangkap event pesan masuk dan meneruskan isi pesan ke webhook Laravel.
  2. Controller memanggil `GeminiService::askAssistant()`.
  3. GeminiService mengambil status live dari Python CCTV (`/api/status`) dan daftar pegawai dari MySQL.
  4. Sistem menyusun *system prompt* profesional dengan aturan ketat 0% emoji dan menyertakan data faktual CCTV.
  5. Google Gemini Flash memproses inferensi dan menghasilkan jawaban ringkas dan akurat dalam waktu kurang dari 3 detik.
  6. Laravel mengembalikan teks jawaban ke WhatsApp gateway, lalu dikirimkan langsung ke nomor penanya.
- **Kondisi Akhir:** Penanya menerima balasan WhatsApp yang informatif dan akurat sesuai fakta visual CCTV.

---

## 5. KRITERIA PENERIMAAN DAN PENGUJIAN

### 5.1 Kriteria Penerimaan Sistem
1. **Autentikasi dan Keamanan:** akses dashboard dibatasi hanya untuk akun admin yang valid dengan proteksi CSRF token di seluruh formulir.
2. **Ketepatan Deteksi Meja:** perubahan status dari Bekerja menjadi Tidak di Tempat terjadi secara konsisten ketika evaluasi spasial postur tubuh berada di bawah ambang batas selama buffer waktu histeresis tertentu.
3. **Stabilitas Layanan Gateway:** WhatsApp gateway mampu mengirim dan menerima pesan secara terus-menerus tanpa terputus.
4. **Respon Gemini AI Bebas Halusinasi:** AI hanya menjawab status berdasarkan data telemetri aktual CCTV yang disuntikkan ke dalam *system context*.

### 5.2 Metode Pengujian
**Tabel 5.1 Metode Pengujian**
| Jenis Pengujian | Tujuan Pengujian | Pendekatan | Deskripsi Skenario Pengujian |
| :--- | :--- | :--- | :--- |
| **Unit Testing** | Menguji kebenaran logika fungsi internal. | *White box testing* | Menguji format nomor telepon, parsing bounding box IoU di Python, dan kalkulasi menit away. |
| **Integration Testing** | Memverifikasi komunikasi antar subsistem. | *White box dan black box* | Menguji aliran data: deteksi YOLO11 Python -> endpoint `/api/status` -> command Laravel -> HTTP POST gateway Baileys -> pengiriman WhatsApp. |
| **System Testing** | Memastikan keseluruhan alur aplikasi berjalan sesuai SRS. | *Black box testing* | Pengujian end-to-end: admin menggambar meja baru di kanvas, menetapkan pegawai, menyimulasikan pegawai meninggalkan meja, lalu memverifikasi WA masuk di ponsel pegawai. |
| **Performance Testing** | Mengukur kestabilan beban dan framerate streaming. | *Stress and benchmark* | Mengukur konsumsi CPU/RAM dan kestabilan FPS saat seluruh meja dipantau bersamaan selama pemantauan nonstop. |
| **Security Testing** | Menjamin keamanan data dan kredensial sistem. | *Vulnerability test* | Pengujian sanitasi input SQL injection, XSS, serta verifikasi bahwa API key tidak terekspos ke antarmuka publik. |

---

## 6. HARDWARE INTERFACE

### 6.1 Spesifikasi Perangkat Keras
**Tabel 6.1 Spesifikasi Perangkat Keras**
| Komponen | Spesifikasi Minimum | Spesifikasi Rekomendasi |
| :--- | :--- | :--- |
| **Prosesor (CPU)** | Intel Core i5 Gen 8 / AMD Ryzen 5 (4 cores, 2.5 GHz) | Intel Core i7 Gen 11+ / AMD Ryzen 7 (8 cores, 3.5 GHz+) |
| **GPU Acceleration** | Intel UHD Graphics (mode CPU Inference) | NVIDIA GeForce GTX 1650 / RTX 3060 (dukungan CUDA) |
| **Memori (RAM)** | 8 GB DDR4 | 16 GB DDR4/DDR5 |
| **Penyimpanan** | 500 MB ruang kosong untuk aplikasi dan database | 10 GB SSD NVMe (penyimpanan histori log dan rekaman) |
| **Kamera CCTV** | IP camera 720p @ 15 FPS (RTSP stream / video file) | IP camera 1080p Full HD @ 25–30 FPS (H.264/H.265 RTSP) |
| **Jaringan** | Wi-Fi / LAN 100 Mbps | Gigabit Ethernet (1000 Mbps) stabil |

---

## 7. SOFTWARE INTERFACE

### 7.1 Skema Pertukaran Data Telemetri Internal (Python ke Laravel)
Python stream engine mengekspos endpoint status terstruktur pada `GET http://127.0.0.1:5000/api/status` dengan format JSON sebagai berikut:
```json
{
  "fps": 24.5,
  "status": "online",
  "total_bekerja": 5,
  "total_away": 0,
  "zones": {
    "chair_1": {
      "zone_id": "chair_1",
      "status": "BEKERJA",
      "occupied_duration": 2450.5,
      "away_duration_seconds": 0.0,
      "verified_employee_name": "Bili",
      "chair_bbox": [1049, 398, 1212, 632]
    },
    "chair_5": {
      "zone_id": "chair_5",
      "status": "BEKERJA",
      "occupied_duration": 1800.0,
      "away_duration_seconds": 0.0,
      "verified_employee_name": "Dealya",
      "chair_bbox": [608, 661, 750, 1031]
    }
  }
}
```

### 7.2 Integrasi Google Gemini Generative AI
a. **Endpoint**: `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={GEMINI_API_KEY}`  
b. **Konfigurasi Parameter**: `temperature = 0.1`, `maxOutputTokens = 1500`.  
c. **System Instruction**: menyuntikkan fakta telemetri CCTV terkini dan menegakkan aturan respon bebas emoji (*zero-emoji policy*).  

---

## 8. COMMUNICATION INTERFACE

1. **HTTP/HTTPS (Port 8000 dan 5000)**: digunakan untuk komunikasi REST API antara Laravel dan Python engine, serta transmisi video MJPEG.
2. **WebSocket TLS (Port 443)**: digunakan oleh Baileys untuk menjaga koneksi soket dua arah (*bidirectional socket*) dengan WhatsApp Multi-Device Server.

---

## 9. LAMPIRAN

### 9.1 Glosarium Istilah
a. **Bounding Box**: koordinat area persegi panjang [x1, y1, x2, y2] yang melingkupi objek terdeteksi.  
b. **ArcFace Cosine Similarity**: perhitungan derajat kemiripan antara dua vektor biometrik wajah dalam ruang dimensi tinggi (rentang -1 hingga 1, ambang batas kecocokan >= 0.22).  
c. **Throttling & Async Face Check**: mekanisme pembatasan frekuensi inferensi wajah menggunakan background worker asinkron dan round-robin untuk menjaga framerate video tetap tinggi pada pemrosesan CPU.  

---

## 10. PERSETUJUAN DAN REVISI

### 10.1 Persetujuan Pemangku Kepentingan
**Subang, 24 Agustus 2026**

Dokumen *Software Requirements Specification* (SRS) ini telah disusun, ditinjau secara menyeluruh, dan disetujui sebagai pedoman resmi pengembangan **Sistem Pemantauan Presensi Pegawai dan Workstation Berbasis AI CCTV** oleh pihak-pihak terkait.

*© 2026 — Program Studi D4 Rekayasa Perangkat Lunak, Politeknik Negeri Subang.*
