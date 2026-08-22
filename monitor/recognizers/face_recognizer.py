import os
import cv2
import numpy as np

try:
    import insightface
    from insightface.app import FaceAnalysis
    INSIGHTFACE_AVAILABLE = True
except ImportError:
    INSIGHTFACE_AVAILABLE = False


class InsightFaceRecognizer:
    def __init__(self, faces_dir="faces_db", model_name="buffalo_s", similarity_threshold=0.40, use_gpu=False):
        """
        Modul Face Recognition Deep Learning Modern berbasis InsightFace (SCRFD + ArcFace).
        
        - SCRFD: Single-stage Face Detector (Frontal & Side-Profile 30-90 derajat)
        - ArcFace: 512-dimensional L2 Normalized Feature Embeddings
        - Similarity: Cosine Similarity Matching (Zero-Shot)
        """
        self.faces_dir = faces_dir
        self.model_name = model_name
        self.similarity_threshold = similarity_threshold
        self.use_gpu = use_gpu

        # Pre-cache dictionary embedding 512-dim: { 'Bili': np.array([512]), ... }
        self.known_face_embeddings = {}
        self.known_names = []

        if not INSIGHTFACE_AVAILABLE:
            print("[WARNING InsightFace] Library 'insightface' belum terpasang.")
            self.app = None
            return

        # Tentukan Execution Provider ONNX Runtime (CPU / GPU)
        providers = ['CUDAExecutionProvider', 'CPUExecutionProvider'] if use_gpu else ['CPUExecutionProvider']
        
        print(f"[INFO InsightFace] Inisialisasi InsightFace (Model: {model_name}, Providers: {providers})...")
        try:
            self.app = FaceAnalysis(name=model_name, providers=providers)
            ctx_id = 0 if use_gpu else -1
            self.app.prepare(ctx_id=ctx_id, det_size=(320, 320))
            print(f"[INFO InsightFace] Model SCRFD + ArcFace '{model_name}' berhasil dimuat!")
        except Exception as e:
            print(f"[ERROR InsightFace] Gagal memuat InsightFace: {e}")
            self.app = None

        self.load_face_database()

    def load_face_database(self):
        """
        Mengekstrak 512-dim vector embedding dari foto-foto karyawan di faces_db/ (Caching sekali di awal).
        """
        if not os.path.exists(self.faces_dir):
            try:
                os.makedirs(self.faces_dir, exist_ok=True)
            except Exception:
                pass
            return

        if self.app is None:
            return

        valid_exts = ('.jpg', '.jpeg', '.png', '.bmp')
        for fname in os.listdir(self.faces_dir):
            if fname.lower().endswith(valid_exts):
                name = os.path.splitext(fname)[0].replace('_', ' ').title()
                img_path = os.path.join(self.faces_dir, fname)
                img = cv2.imread(img_path)
                if img is not None:
                    # Jalankan deteksi SCRFD & ekstraksi embedding ArcFace
                    faces = self.app.get(img)
                    if len(faces) > 0:
                        # Ambil wajah terbesar dari foto template
                        largest_face = max(faces, key=lambda f: (f.bbox[2]-f.bbox[0]) * (f.bbox[3]-f.bbox[1]))
                        embedding = largest_face.embedding
                        # Normalisasi L2 untuk Cosine Similarity
                        norm = np.linalg.norm(embedding)
                        if norm > 0:
                            embedding = embedding / norm

                        self.known_face_embeddings[name] = embedding
                        if name not in self.known_names:
                            self.known_names.append(name)
                        print(f"[INFO InsightFace] Generated 512-D embedding template untuk '{name}'")
                    else:
                        print(f"[WARNING InsightFace] Tidak terdeteksi wajah pada foto '{fname}' di faces_db/")

        print(f"[INFO InsightFace] Database memuat {len(self.known_face_embeddings)} identitas wajah: {', '.join(self.known_names) if self.known_names else 'Kosong (Tambahkan foto ke faces_db/)'}")

    def match_face(self, face_crop):
        """
        Mencocokkan potongan gambar wajah (face_crop BGR) dengan database karyawan secara real-time.
        
        :return: (matched_name, confidence_score_pct) jika Cosine Similarity >= threshold, else (None, 0.0)
        """
        if self.app is None or not self.known_face_embeddings or face_crop is None or face_crop.size == 0:
            return None, 0.0

        try:
            faces = self.app.get(face_crop)
            if len(faces) == 0:
                return None, 0.0

            largest_face = max(faces, key=lambda f: (f.bbox[2]-f.bbox[0]) * (f.bbox[3]-f.bbox[1]))
            query_embedding = largest_face.embedding
            q_norm = np.linalg.norm(query_embedding)
            if q_norm > 0:
                query_embedding = query_embedding / q_norm

            best_match_name = None
            best_similarity = -1.0

            for name, db_embedding in self.known_face_embeddings.items():
                # Cosine Similarity antara dua vektor L2-normalized: dot product
                similarity = float(np.dot(query_embedding, db_embedding))
                if similarity > best_similarity:
                    best_similarity = similarity
                    best_match_name = name

            if best_similarity >= self.similarity_threshold and best_match_name:
                confidence_pct = max(60.0, min(99.0, round(best_similarity * 100.0)))
                return best_match_name, confidence_pct

            return None, 0.0

        except Exception as e:
            return None, 0.0

    def verify_identity(self, frame, upper_body_bbox):
        """
        Fungsi wrapper pendukung untuk kompatibilitas penuh dengan rule_zone_presence.py.
        Memotong area kepala/wajah dari upper_body_bbox dan mencocokkan embedding wajahnya.
        """
        if self.app is None or not self.known_face_embeddings or frame is None or upper_body_bbox is None:
            return None, 0.0

        try:
            h_frame, w_frame = frame.shape[:2]
            x1, y1, x2, y2 = [int(v) for v in upper_body_bbox]
            box_w = x2 - x1
            box_h = y2 - y1

            # Perlebar area crop kepala secara agresif agar wajah miring/samping tetap tercakup
            pad_x = int(box_w * 0.35)  # Padding horizontal 35% (tangkap wajah miring)
            pad_top = int(box_h * 0.3)  # Padding atas 30%
            head_h = max(30, int(box_h * 0.75))  # Ambil 75% tinggi boks sebagai area kepala

            hx1 = max(0, x1 - pad_x)
            hy1 = max(0, y1 - pad_top)
            hx2 = min(w_frame, x2 + pad_x)
            hy2 = min(h_frame, y1 + head_h)

            head_crop = frame[hy1:hy2, hx1:hx2]
            if head_crop.size == 0 or head_crop.shape[0] < 20 or head_crop.shape[1] < 20:
                return None, 0.0

            return self.match_face(head_crop)

        except Exception:
            return None, 0.0
