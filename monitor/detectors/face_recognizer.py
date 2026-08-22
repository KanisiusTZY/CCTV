import os
import cv2
import numpy as np

class FaceRecognizerModule:
    def __init__(self, faces_dir="faces_db"):
        """
        Modul Verifikasi Identitas Wajah Hybrid CCTV.
        Membaca foto karyawan dari folder faces_db/ (misal bili.jpeg, Budi_Santoso.jpg).
        """
        self.faces_dir = faces_dir
        self.known_face_templates = {}
        self.known_names = []

        base_dir = os.path.dirname(__file__)
        frontal_xml = os.path.join(base_dir, "..", "data", "haarcascade_frontalface_default.xml")
        profile_xml = os.path.join(base_dir, "..", "data", "haarcascade_profileface.xml")

        if not os.path.exists(frontal_xml):
            frontal_xml = "monitor/data/haarcascade_frontalface_default.xml"
        if not os.path.exists(profile_xml):
            profile_xml = "monitor/data/haarcascade_profileface.xml"
        if not os.path.exists(frontal_xml):
            frontal_xml = "D:/MonitorKETUA/monitor/data/haarcascade_frontalface_default.xml"
        if not os.path.exists(profile_xml):
            profile_xml = "D:/MonitorKETUA/monitor/data/haarcascade_profileface.xml"

        self.frontal_cascade = cv2.CascadeClassifier(frontal_xml) if os.path.exists(frontal_xml) else None
        self.profile_cascade = cv2.CascadeClassifier(profile_xml) if os.path.exists(profile_xml) else None

        self.load_known_faces()

    def load_known_faces(self):
        """Memuat foto karyawan dari faces_db/ & mengekstrak matriks wajahnya"""
        if not os.path.exists(self.faces_dir):
            try:
                os.makedirs(self.faces_dir, exist_ok=True)
            except Exception:
                pass
            return

        valid_exts = ('.jpg', '.jpeg', '.png', '.bmp')
        for fname in os.listdir(self.faces_dir):
            if fname.lower().endswith(valid_exts):
                name = os.path.splitext(fname)[0].replace('_', ' ').title()
                img_path = os.path.join(self.faces_dir, fname)
                img = cv2.imread(img_path)
                if img is not None:
                    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
                    gray_eq = cv2.equalizeHist(gray)
                    
                    # Ekstrak area wajah (Frontal atau Profile)
                    face_crop = self._detect_any_face(gray_eq)
                    if face_crop is None:
                        face_crop = gray_eq

                    resized = cv2.resize(face_crop, (100, 100))
                    self.known_face_templates[name] = resized

                    if name not in self.known_names:
                        self.known_names.append(name)
                        
        print(f"[INFO FaceRecognizer] Loaded {len(self.known_face_templates)} face identities from '{self.faces_dir}/': {', '.join(self.known_names) if self.known_names else 'Kosong'}")

    def _detect_any_face(self, gray_img):
        """Mendeteksi wajah tampak depan maupun tampak samping (profile face)"""
        if gray_img is None or gray_img.size == 0:
            return None

        # 1. Coba Frontal Face
        if self.frontal_cascade is not None:
            faces = self.frontal_cascade.detectMultiScale(gray_img, scaleFactor=1.1, minNeighbors=3, minSize=(20, 20))
            if len(faces) > 0:
                fx, fy, fw, fh = max(faces, key=lambda b: b[2] * b[3])
                return gray_img[fy:fy+fh, fx:fx+fw]

        # 2. Coba Profile Face (Tampak Samping Kanan)
        if self.profile_cascade is not None:
            profiles = self.profile_cascade.detectMultiScale(gray_img, scaleFactor=1.1, minNeighbors=3, minSize=(20, 20))
            if len(profiles) > 0:
                fx, fy, fw, fh = max(profiles, key=lambda b: b[2] * b[3])
                return gray_img[fy:fy+fh, fx:fx+fw]

            # 3. Coba Profile Face (Tampak Samping Kiri dengan Flip Horizontal)
            flipped = cv2.flip(gray_img, 1)
            profiles_flip = self.profile_cascade.detectMultiScale(flipped, scaleFactor=1.1, minNeighbors=3, minSize=(20, 20))
            if len(profiles_flip) > 0:
                fx, fy, fw, fh = max(profiles_flip, key=lambda b: b[2] * b[3])
                crop_flip = flipped[fy:fy+fh, fx:fx+fw]
                return cv2.flip(crop_flip, 1)

        return None

    def verify_identity(self, frame, upper_body_bbox):
        """
        Percobaan verifikasi identitas wajah dari boks upper_body_bbox di CCTV.
        """
        if not self.known_face_templates or frame is None or upper_body_bbox is None:
            return None, 0.0

        try:
            h_frame, w_frame = frame.shape[:2]
            x1, y1, x2, y2 = [int(v) for v in upper_body_bbox]

            # Limit ROI ke area atas upper body (lokasi kepala/wajah)
            head_h = max(20, int((y2 - y1) * 0.60))
            hx1 = max(0, x1 - 10)
            hy1 = max(0, y1 - 15)
            hx2 = min(w_frame, x2 + 10)
            hy2 = min(h_frame, y1 + head_h)

            head_crop = frame[hy1:hy2, hx1:hx2]
            if head_crop.size == 0 or head_crop.shape[0] < 20 or head_crop.shape[1] < 20:
                return None, 0.0

            gray_head = cv2.cvtColor(head_crop, cv2.COLOR_BGR2GRAY)
            gray_head_eq = cv2.equalizeHist(gray_head)

            # Deteksi area wajah (Frontal atau Profile)
            face_crop = self._detect_any_face(gray_head_eq)
            is_face_detected = face_crop is not None
            if not is_face_detected:
                face_crop = gray_head_eq

            target_resized = cv2.resize(face_crop, (100, 100))

            best_match_name = None
            best_corr = -1.0

            for name, template in self.known_face_templates.items():
                res = cv2.matchTemplate(target_resized, template, cv2.TM_CCOEFF_NORMED)
                corr = float(res[0][0])
                if corr > best_corr:
                    best_corr = corr
                    best_match_name = name

            # Jika boks wajah spesifik terdeteksi menghadap kamera (misal Bili di Meja 1) -> Gunakan threshold CCTV yang pas
            if is_face_detected and best_corr >= 0.20 and best_match_name:
                confidence = max(65.0, min(98.0, round(best_corr * 100.0 + 45.0)))
                return best_match_name, confidence

            # Jika tidak ada boks wajah lurus -> fallback (tidak pasang nama Bili sembarangan)
            return None, 0.0

        except Exception:
            return None, 0.0
