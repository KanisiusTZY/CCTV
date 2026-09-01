from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor
import time
import numpy as np

def compute_box_metrics(boxA, boxB):
    """
    Menghitung Intersection over Union (IoU), rasio Containment boxA di dalam boxB,
    serta memeriksa apakah centroid boxA berada di dalam boxB untuk Bounding Box axis-aligned [x1, y1, x2, y2].
    """
    xA = max(boxA[0], boxB[0])
    yA = max(boxA[1], boxB[1])
    xB = min(boxA[2], boxB[2])
    yB = min(boxA[3], boxB[3])

    inter_w = max(0, xB - xA)
    inter_h = max(0, yB - yA)
    inter_area = inter_w * inter_h

    if inter_area == 0:
        return 0.0, 0.0, False

    areaA = max(0, boxA[2] - boxA[0]) * max(0, boxA[3] - boxA[1])
    areaB = max(0, boxB[2] - boxB[0]) * max(0, boxB[3] - boxB[1])

    union_area = areaA + areaB - inter_area
    iou = inter_area / float(union_area) if union_area > 0 else 0.0
    containment = inter_area / float(areaA) if areaA > 0 else 0.0

    # Centroid boxA
    cxA = (boxA[0] + boxA[2]) / 2.0
    cyA = (boxA[1] + boxA[3]) / 2.0
    center_inside = (boxB[0] <= cxA <= boxB[2]) and (boxB[1] <= cyA <= boxB[3])

    return iou, containment, center_inside

def compute_iou(boxA, boxB):
    """Fungsi pembantu kompatibilitas mundur untuk IoU."""
    iou, _, _ = compute_box_metrics(boxA, boxB)
    return iou


class RuleZonePresence:
    def __init__(self, config: dict, fps: float = 30.0):
        """
        State Machine Occupancy Deteksi Kehadiran Per-Zona (Independen dari Track ID).
        Menggunakan Smart One-Shot Face Recognition dengan Round-Robin Throttling untuk performa 30 FPS.
        """
        self.fps = float(fps) if fps and fps > 0 else 30.0
        self.update_config(config)

        # State Machine Per-Zona (Counter consecutive frames)
        self.consecutive_occupied = defaultdict(int)
        self.consecutive_empty = defaultdict(int)
        self.status = defaultdict(lambda: "TIDAK_DI_TEMPAT")
        self.matched_bbox = defaultdict(lambda: None)
        
        # Durasi Akumulatif & Timer
        self.total_occupied_seconds = defaultdict(float)
        self.total_away_seconds = defaultdict(float)
        self.away_start_time = defaultdict(lambda: None)
        self.last_process_time = None
        self.frame_count = 0

        # Verifikasi Biometrik Wajah Asynchronous One-Shot
        self.verified_identity_cache = defaultdict(lambda: None)
        self.last_global_face_check_time = 0.0
        self.face_executor = ThreadPoolExecutor(max_workers=1)
        self.is_checking_face = False

    def _async_verify_worker(self, zone_id, frame_crop, face_recognizer):
        """Worker thread latar belakang untuk memproses ArcFace tanpa membebani FPS utama"""
        try:
            v_name, v_conf = face_recognizer.verify_identity(frame_crop, [0, 0, frame_crop.shape[1], frame_crop.shape[0]])
            if v_name:
                self.verified_identity_cache[zone_id] = v_name
                print(f"[FACE VERIFIED] Zona {zone_id} teridentifikasi sebagai: {v_name} (conf={v_conf:.1f}%)")
        except Exception:
            pass
        finally:
            self.is_checking_face = False

    def set_fps(self, fps: float):
        """Update FPS video aktual untuk kalkulasi dinamis ambang batas frame."""
        if fps and fps > 0:
            self.fps = float(fps)
            self._recalculate_threshold_frames()

    def update_config(self, config: dict):
        """Memuat dan memperbarui konfigurasi parameter zona & toleransi."""
        self.iou_threshold = float(config.get("iou_threshold", 0.15))
        self.enter_seconds = float(config.get("enter_seconds", 0.3))
        self.exit_seconds = float(config.get("exit_seconds", 0.5))
        self.chair_zones = config.get("chair_zones", [])

        self.zone_iou_threshold = {}
        for zone in self.chair_zones:
            zone_id = zone["id"]
            self.zone_iou_threshold[zone_id] = float(zone.get("iou_threshold", self.iou_threshold))

        self._recalculate_threshold_frames()

    def _recalculate_threshold_frames(self):
        """Mengonversi toleransi detik ke jumlah frame berturut-turut sesuai FPS aktual."""
        self.enter_frames = max(1, int(round(self.enter_seconds * self.fps)))
        self.exit_frames = max(1, int(round(self.exit_seconds * self.fps)))

    def reset(self):
        """Mereset semua state machine dan akumulasi durasi."""
        self.consecutive_occupied.clear()
        self.consecutive_empty.clear()
        self.status.clear()
        self.matched_bbox.clear()
        self.total_occupied_seconds.clear()
        self.total_away_seconds.clear()
        self.away_start_time.clear()
        self.verified_identity_cache.clear()
        self.last_global_face_check_time = 0.0
        self.is_checking_face = False
        self.last_process_time = None
        self.frame_count = 0

    def process(self, frame, detections: list, current_time: float = None, face_recognizer = None, fps: float = None):
        """
        Mengevaluasi occupancy per zona murni dari deteksi per-frame secara asinkron.
        """
        if current_time is None:
            current_time = time.time()

        if fps is not None and fps > 0 and abs(fps - self.fps) > 1.0:
            self.set_fps(fps)

        self.frame_count += 1

        if self.last_process_time is not None:
            dt = max(0.0, min(1.0, current_time - self.last_process_time))
        else:
            dt = 1.0 / self.fps
        self.last_process_time = current_time

        # -------------------------------------------------------------
        # 1. EVALUASI SPASIAL DETEKSI PER-FRAME MURNI TERHADAP ZONA
        # -------------------------------------------------------------
        zone_best_candidate = {}

        for det in detections:
            upper_body = det.get("upper_body_bbox")
            full_body  = det.get("full_body_bbox", upper_body)
            conf       = det.get("confidence", 0.0)

            if upper_body is None:
                continue

            for zone in self.chair_zones:
                zone_id    = zone["id"]
                chair_bbox = zone["bbox"]
                thresh     = self.zone_iou_threshold.get(zone_id, self.iou_threshold)

                chair_h = max(20, chair_bbox[3] - chair_bbox[1])
                target_zone_shape = [
                    max(0, chair_bbox[0] - 10),
                    max(0, chair_bbox[1] - int(chair_h * 0.25) - 10),
                    chair_bbox[2] + 10,
                    chair_bbox[3] + 10
                ]

                iou_upper, cont_upper, center_upper = compute_box_metrics(upper_body, target_zone_shape)
                iou_full, cont_full, center_full    = compute_box_metrics(full_body, target_zone_shape)

                is_overlap = (
                    iou_upper >= thresh or 
                    iou_full >= thresh or 
                    cont_upper >= 0.22 or 
                    cont_full >= 0.25 or 
                    center_upper or 
                    center_full
                )

                if is_overlap:
                    score = (
                        (max(iou_upper, iou_full) * 3.0) +
                        (max(cont_upper, cont_full) * 2.0) +
                        (1.0 if center_upper or center_full else 0.0) +
                        (conf * 1.5)
                    )

                    if zone_id not in zone_best_candidate or score > zone_best_candidate[zone_id][0]:
                        zone_best_candidate[zone_id] = (score, upper_body, full_body, conf)

        # -------------------------------------------------------------
        # 2. STATE MACHINE & DEBOUNCE PER-ZONA (MURNI PER-FRAME)
        # -------------------------------------------------------------
        results = {}
        unverified_occupied_zone = None

        for zone in self.chair_zones:
            zone_id = zone["id"]
            chair_bbox = zone["bbox"]

            raw_occupied = zone_id in zone_best_candidate
            prev_status = self.status[zone_id]

            if raw_occupied:
                self.consecutive_occupied[zone_id] += 1
                self.consecutive_empty[zone_id] = 0
                _, best_upper, best_full, best_conf = zone_best_candidate[zone_id]

                if self.consecutive_occupied[zone_id] >= self.enter_frames:
                    self.status[zone_id] = "BEKERJA"
                    self.matched_bbox[zone_id] = best_upper
                    self.away_start_time[zone_id] = None
                elif prev_status == "BEKERJA":
                    self.matched_bbox[zone_id] = best_upper

            else:
                self.consecutive_empty[zone_id] += 1
                self.consecutive_occupied[zone_id] = 0

                if self.consecutive_empty[zone_id] >= self.exit_frames:
                    self.status[zone_id] = "TIDAK_DI_TEMPAT"
                    self.matched_bbox[zone_id] = None
                    self.verified_identity_cache[zone_id] = None
                    if self.away_start_time[zone_id] is None:
                        self.away_start_time[zone_id] = current_time

            curr_status = self.status[zone_id]
            verified_name = self.verified_identity_cache[zone_id]

            # Cari 1 zona yang butuh verifikasi wajah
            if curr_status == "BEKERJA" and verified_name is None and self.matched_bbox[zone_id] is not None:
                if unverified_occupied_zone is None:
                    unverified_occupied_zone = (zone_id, self.matched_bbox[zone_id])

            # Akumulasi durasi bekerja dan away
            if curr_status == "BEKERJA":
                self.total_occupied_seconds[zone_id] += dt
            else:
                self.total_away_seconds[zone_id] += dt

            results[zone_id] = {
                "zone_id":                 zone_id,
                "chair_bbox":              chair_bbox,
                "track_id":                None,
                "status":                  curr_status,
                "matched_upper_body_bbox": self.matched_bbox[zone_id],
                "verified_employee_name":  verified_name,
                "away_start_time":         self.away_start_time[zone_id],
                "away_duration_seconds":   self.total_away_seconds[zone_id],
                "occupied_duration":       self.total_occupied_seconds[zone_id],
                "empty_duration":          self.total_away_seconds[zone_id],
            }

        # -------------------------------------------------------------
        # 3. ROUND-ROBIN ASYNC FACE RECOGNITION (MAKS 1 ZONA / 0.5s)
        # -------------------------------------------------------------
        if current_time < self.last_global_face_check_time:
            self.last_global_face_check_time = 0.0

        if face_recognizer and unverified_occupied_zone is not None and not self.is_checking_face:
            if (current_time - self.last_global_face_check_time) >= 0.5 or self.last_global_face_check_time == 0.0:
                self.last_global_face_check_time = current_time
                target_zid, target_bbox = unverified_occupied_zone
                
                x1, y1, x2, y2 = [int(v) for v in target_bbox]
                h_f, w_f = frame.shape[:2]
                pad_w = int((x2 - x1) * 0.20)
                pad_h = int((y2 - y1) * 0.20)
                crop = frame[max(0, y1 - pad_h):min(h_f, y2 + pad_h), max(0, x1 - pad_w):min(w_f, x2 + pad_w)].copy()
                
                if crop.size > 0:
                    self.is_checking_face = True
                    self.face_executor.submit(self._async_verify_worker, target_zid, crop, face_recognizer)

        # Uniqueness constraint: Satu identitas nama pegawai tidak boleh muncul di 2 meja berbeda
        seen_names = {}
        for zid, res in results.items():
            name = res.get("verified_employee_name")
            if name:
                if name in seen_names:
                    res["verified_employee_name"] = None
                    self.verified_identity_cache[zid] = None
                else:
                    seen_names[name] = zid

        return results
