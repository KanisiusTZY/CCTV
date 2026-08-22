from collections import defaultdict
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
    def __init__(self, config: dict):
        """
        Aturan untuk memeriksa kehadiran pada zona kursi statis menggunakan ambang batas waktu,
        dikombinasikan dengan mekanisme Zone Lock berbasis ByteTrack ID untuk mencegah flicker.
        
        :param config: Dictionary berisi iou_threshold, enter_seconds, exit_seconds,
                       miss_tolerance_seconds, chair_zones, dll.
        """
        self.update_config(config)

        self.occupied_since  = defaultdict(lambda: None)
        self.empty_since     = defaultdict(lambda: None)
        self.last_seen       = defaultdict(lambda: None)

        self.status          = defaultdict(lambda: "TIDAK_DI_TEMPAT")
        self.away_start_time = defaultdict(lambda: None)
        self.matched_bbox    = defaultdict(lambda: None)
        self.frame_count     = 0

        self.verified_identity_cache = defaultdict(lambda: None)
        self.last_identity_check_time = defaultdict(lambda: 0.0)
        self.total_occupied_seconds = defaultdict(lambda: 0.0)
        self.total_away_seconds = defaultdict(lambda: 0.0)

        # Structure untuk Zone Lock berbasis track_id & logging switch assignment
        self.track_lock_frames     = {}
        self.track_current_zone    = {}
        self.prev_zone_assignments = {}

    def update_config(self, config: dict):
        self.iou_threshold = float(config.get("iou_threshold", 0.08))

        _fallback_fps = 25.0
        self.enter_seconds = float(config.get(
            "enter_seconds",
            config.get("enter_frames", config.get("persistence_frames", 12)) / _fallback_fps
        ))
        self.exit_seconds = float(config.get(
            "exit_seconds",
            config.get("exit_frames", config.get("persistence_frames", 12)) / _fallback_fps
        ))
        self.miss_tolerance_seconds = float(config.get("miss_tolerance_seconds", 0.5))

        self.chair_zones = config.get("chair_zones", [])

        self.zone_iou_threshold = {}
        self.zone_miss_tolerance = {}

        self.verified_identity_cache = {}
        self.last_identity_check_time = {}
        self.total_occupied_seconds = {}
        self.total_away_seconds = {}

        for zone in self.chair_zones:
            zone_id = zone["id"]
            self.total_occupied_seconds[zone_id] = 0.0
            self.total_away_seconds[zone_id] = 0.0
            self.zone_iou_threshold[zone_id] = float(zone.get("iou_threshold", self.iou_threshold))
            self.zone_miss_tolerance[zone_id] = float(zone.get("miss_tolerance_seconds", self.miss_tolerance_seconds))

    def reset(self):
        """Mereset semua timer, tracker lock, dan status zona."""
        self.occupied_since.clear()
        self.empty_since.clear()
        self.last_seen.clear()
        self.status.clear()
        self.away_start_time.clear()
        self.matched_bbox.clear()
        self.track_lock_frames.clear()
        self.track_current_zone.clear()
        self.prev_zone_assignments.clear()
        self.verified_identity_cache.clear()
        self.last_identity_check_time.clear()
        self.total_occupied_seconds.clear()
        self.total_away_seconds.clear()
        self.frame_count = 0

    def process(self, frame, detections: list, current_time: float = None, face_recognizer = None):
        """
        Mengevaluasi kehadiran untuk setiap zona kursi yang terdaftar berbasis waktu
        dengan perketatan threshold dan Zone Lock berbasis track_id.
        """
        if current_time is None:
            current_time = time.time()

        self.frame_count += 1
        results = {}

        # 1. Bangun semua pasangan kandidat yang valid (final_score, det_idx, zone_id, upper_body_bbox, track_id)
        candidate_pairs = []

        for det_idx, det in enumerate(detections):
            upper_body = det["upper_body_bbox"]
            full_body  = det.get("full_body_bbox", upper_body)
            track_id   = det.get("track_id")
            conf       = det.get("confidence", 0.0)

            for zone in self.chair_zones:
                zone_id    = zone["id"]
                chair_bbox = zone["bbox"]
                zone_iou_thresh = self.zone_iou_threshold.get(zone_id, self.iou_threshold)

                chair_h = max(20, chair_bbox[3] - chair_bbox[1])
                target_zone_shape = [
                    max(0, chair_bbox[0] - 8),
                    max(0, chair_bbox[1] - int(chair_h * 0.20) - 10),
                    chair_bbox[2] + 8,
                    chair_bbox[3] + 8
                ]

                iou1, cont1, center1 = compute_box_metrics(upper_body, target_zone_shape)
                iou2, cont2, center2 = compute_box_metrics(full_body, target_zone_shape)

                best_iou = max(iou1, iou2)
                best_cont = max(cont1, cont2)
                center_inside = center1 or center2

                # Kriteria Match Presisi: Centroid di dalam boks meja, atau IoU/Containment minimal 30% (mencegah spillover dari meja sebelah)
                is_match = (
                    center_inside or
                    (best_iou >= zone_iou_thresh) or
                    (best_cont >= 0.30)
                )

                if is_match:
                    raw_score = max(best_iou, best_cont)

                    lock_bonus = 0.0
                    if track_id is not None:
                        locked_zone = self.track_current_zone.get(track_id)
                        if locked_zone == zone_id:
                            lock_bonus = 0.25
                        elif locked_zone is not None and locked_zone != zone_id:
                            lock_bonus = -0.15

                    final_score = raw_score + lock_bonus
                    candidate_pairs.append((final_score, det_idx, zone_id, upper_body, track_id, conf, best_iou, best_cont))

        # 2. Greedy 1-to-1 matching: urutkan dari skor akhir tertinggi
        candidate_pairs.sort(key=lambda x: x[0], reverse=True)

        assigned_detections = set()
        assigned_zones      = {}
        current_zone_tracks = {}
        winning_cand_info   = {}

        for score, det_idx, zone_id, upper_body, track_id, conf, best_iou, best_cont in candidate_pairs:
            if det_idx not in assigned_detections and zone_id not in assigned_zones:
                assigned_detections.add(det_idx)
                assigned_zones[zone_id] = upper_body
                current_zone_tracks[zone_id] = track_id
                winning_cand_info[zone_id] = {
                    "conf": conf,
                    "iou": best_iou,
                    "cont": best_cont,
                    "track_id": track_id
                }

        # 3. UPDATE TRACKER ZONE LOCK & LOGGING SWITCH ASSIGNMENT
        for zone in self.chair_zones:
            zone_id    = zone["id"]
            curr_track = current_zone_tracks.get(zone_id)
            prev_track = self.prev_zone_assignments.get(zone_id)

            if curr_track != prev_track:
                if prev_track is not None and curr_track is not None:
                    print(f"[ZONE SWITCH] Frame {self.frame_count} ({current_time:.2f}s) | {zone_id}: track_id {prev_track} -> track_id {curr_track}")
                elif prev_track is None and curr_track is not None:
                    print(f"[ZONE ASSIGN] Frame {self.frame_count} ({current_time:.2f}s) | {zone_id}: assigned track_id {curr_track}")
                elif prev_track is not None and curr_track is None:
                    print(f"[ZONE UNASSIGN] Frame {self.frame_count} ({current_time:.2f}s) | {zone_id}: unassigned track_id {prev_track}")

            if curr_track is not None:
                old_zone = self.track_current_zone.get(curr_track)
                if old_zone == zone_id:
                    self.track_lock_frames[curr_track] = self.track_lock_frames.get(curr_track, 0) + 1
                else:
                    if old_zone is not None and old_zone != zone_id:
                        print(f"[TRACK REASSIGN] Frame {self.frame_count} ({current_time:.2f}s) | track_id {curr_track} berpindah dari {old_zone} -> {zone_id}")
                    self.track_current_zone[curr_track] = zone_id
                    self.track_lock_frames[curr_track] = 1

            self.prev_zone_assignments[zone_id] = curr_track

        # 4. Perbarui tracker kehadiran berbasis waktu untuk setiap zona kursi (Timer Histeresis)
        for zone in self.chair_zones:
            zone_id    = zone["id"]
            chair_bbox = zone["bbox"]

            if zone_id not in self.status:
                self.occupied_since[zone_id]  = None
                self.empty_since[zone_id]     = current_time
                self.last_seen[zone_id]       = None
                self.status[zone_id]          = "TIDAK_DI_TEMPAT"
                self.away_start_time[zone_id] = current_time
                self.matched_bbox[zone_id]    = None

            if zone_id in assigned_zones:
                temp_matched_bbox = assigned_zones[zone_id]
                self.last_seen[zone_id] = current_time

                if self.occupied_since[zone_id] is None:
                    self.occupied_since[zone_id] = current_time

                self.empty_since[zone_id] = None

            else:
                temp_matched_bbox = None

                last = self.last_seen[zone_id]
                miss_duration = (current_time - last) if last is not None else float("inf")

                zone_tolerance = self.zone_miss_tolerance.get(zone_id, self.miss_tolerance_seconds)
                if miss_duration > zone_tolerance:
                    if self.occupied_since[zone_id] is not None:
                        print(f"[MISS RESET] Frame {self.frame_count} ({current_time:.2f}s) | "
                              f"{zone_id}: miss_duration={miss_duration:.2f}s > toleransi={zone_tolerance:.2f}s, "
                              f"occupied_since direset")
                    self.occupied_since[zone_id] = None

                if self.empty_since[zone_id] is None:
                    self.empty_since[zone_id] = current_time

            occupied_duration = (
                current_time - self.occupied_since[zone_id]
                if self.occupied_since[zone_id] is not None else 0.0
            )
            empty_duration = (
                current_time - self.empty_since[zone_id]
                if self.empty_since[zone_id] is not None else 0.0
            )

            if occupied_duration >= self.enter_seconds:
                self.status[zone_id]          = "BEKERJA"
                self.away_start_time[zone_id] = None
                if temp_matched_bbox is not None:
                    self.matched_bbox[zone_id] = temp_matched_bbox

            elif empty_duration >= self.exit_seconds:
                self.status[zone_id] = "TIDAK_DI_TEMPAT"
                if self.away_start_time[zone_id] is None:
                    self.away_start_time[zone_id] = current_time
                self.matched_bbox[zone_id] = None
                # Bersihkan cache identitas agar bisa terdeteksi ulang saat orang kembali
                self.verified_identity_cache[zone_id] = None
                self.last_identity_check_time[zone_id] = 0.0

            else:
                if self.status[zone_id] == "BEKERJA":
                    if temp_matched_bbox is not None:
                        self.matched_bbox[zone_id] = temp_matched_bbox

            away_duration = 0.0
            if self.status[zone_id] == "TIDAK_DI_TEMPAT" and self.away_start_time[zone_id] is not None:
                away_duration = max(0.0, current_time - self.away_start_time[zone_id])

            # Layer Verifikasi Identitas Wajah Opsional (Interval 1.0s untuk deteksi cepat & FPS 20+ FPS)
            verified_name = self.verified_identity_cache.get(zone_id)
            last_check = self.last_identity_check_time.get(zone_id, 0.0)

            if face_recognizer and self.status[zone_id] == "BEKERJA" and self.matched_bbox[zone_id] is not None:
                if verified_name is None or (current_time - last_check) > 0.5:
                    self.last_identity_check_time[zone_id] = current_time
                    v_name, v_conf = face_recognizer.verify_identity(frame, self.matched_bbox[zone_id])
                    if v_name:
                        verified_name = v_name
                        self.verified_identity_cache[zone_id] = verified_name
                    # Pertahankan cache identitas yang sudah terverifikasi selama pegawai masih di meja

            if not hasattr(self, 'total_occupied_seconds'):
                self.total_occupied_seconds = {}
            if not hasattr(self, 'total_away_seconds'):
                self.total_away_seconds = {}

            # Accumulate cumulative frame time (~0.1s per frame)
            zone_stat = self.status.get(zone_id, "TIDAK_DI_TEMPAT")
            if zone_stat == "BEKERJA":
                self.total_occupied_seconds[zone_id] = self.total_occupied_seconds.get(zone_id, 0.0) + 0.1
            else:
                self.total_away_seconds[zone_id] = self.total_away_seconds.get(zone_id, 0.0) + 0.1

            results[zone_id] = {
                "zone_id":                 zone_id,
                "chair_bbox":              chair_bbox,
                "track_id":                self.prev_zone_assignments.get(zone_id),
                "status":                  zone_stat,
                "matched_upper_body_bbox": self.matched_bbox.get(zone_id),
                "verified_employee_name":  verified_name,
                "away_start_time":         self.away_start_time.get(zone_id),
                "away_duration_seconds":   self.total_away_seconds.get(zone_id, 0.0),
                "occupied_duration":       self.total_occupied_seconds.get(zone_id, 0.0),
                "empty_duration":          empty_duration,
            }

            # LOGGING PERUBAHAN STATE UNIVERSAL
            curr_trk = self.prev_zone_assignments.get(zone_id)
            curr_stat = self.status.get(zone_id, "TIDAK_DI_TEMPAT")

            if not hasattr(self, "last_logged_trk"):
                self.last_logged_trk = {}
                self.last_logged_status = {}

            prev_trk = self.last_logged_trk.get(zone_id, "PERTAMA")
            prev_stat = self.last_logged_status.get(zone_id, "PERTAMA")

            if curr_trk != prev_trk or curr_stat != prev_stat:
                self.last_logged_trk[zone_id] = curr_trk
                self.last_logged_status[zone_id] = curr_stat

                winfo = winning_cand_info.get(zone_id, {})
                c_conf = f"{winfo.get('conf', 0.0):.3f}" if winfo else "N/A"
                c_iou  = f"{winfo.get('iou', 0.0):.3f}" if winfo else "N/A"
                c_cont = f"{winfo.get('cont', 0.0):.3f}" if winfo else "N/A"

                print(f"[PERUBAHAN STATE] Frame {self.frame_count:3d} ({current_time:5.2f}s) | Zona: {zone_id:7s} | "
                      f"Track: {str(prev_trk):>7s} -> {str(curr_trk):>7s} | "
                      f"Status: {str(prev_stat):>15s} -> {str(curr_stat):>15s} | "
                      f"Identitas: {verified_name if verified_name else ('Pegawai' if curr_stat == 'BEKERJA' else 'Kosong')} | "
                      f"Candidate: conf={c_conf}, IoU={c_iou}, Cont={c_cont}")

        # Uniqueness constraint: Satu orang tidak boleh muncul di 2 meja secara bersamaan
        seen_identities = {}
        for zid, res in results.items():
            name = res.get("verified_employee_name")
            if name:
                if name in seen_identities:
                    # Sudah terdeteksi di meja lain -> hilangkan duplikasi
                    res["verified_employee_name"] = None
                    self.verified_identity_cache[zid] = None
                else:
                    seen_identities[name] = zid

        if self.frame_count % 30 == 0:
            log_parts = []
            for zid, res in results.items():
                trk = self.prev_zone_assignments.get(zid)
                log_parts.append(
                    f"{zid}(trk={trk}): occ={res['occupied_duration']:.1f}s, "
                    f"emp={res['empty_duration']:.1f}s ({res['status']})"
                )
            print(f"[TIMER Frame {self.frame_count}] " + " | ".join(log_parts))

        return results