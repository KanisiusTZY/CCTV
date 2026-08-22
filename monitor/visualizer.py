import cv2
import numpy as np

class Visualizer:
    def __init__(self):
        self.color_bekerja  = (0, 220, 80)      # Hijau BGR (Bekerja / Ada Orang)
        self.color_away     = (0, 60, 220)       # Merah BGR (Kosong / Tidak di Tempat)
        self.color_waiting  = (0, 180, 255)      # Oranye BGR (Akumulasi waktu masuk)
        self.font           = cv2.FONT_HERSHEY_SIMPLEX

    def format_duration(self, seconds: float) -> str:
        """Format durasi dalam detik ke format 'XmYYs'."""
        total_sec = int(max(0, seconds))
        mins  = total_sec // 60
        secs  = total_sec % 60
        return f"{mins}m{secs:02d}s"

    def render(self, frame, presence_results: dict, fps: float = 0.0):
        """
        Merender visualisasi monitoring kehadiran standar (1 kotak per zona):
        - KOTAK HIJAU : Zona terisi (BEKERJA / Ada Orang)
        - KOTAK ORANYE: Sedang proses masuk (AKUMULASI)
        - KOTAK MERAH : Zona kosong (TIDAK DI TEMPAT)
        
        :param frame: Citra BGR input
        :param presence_results: Dict output dari RuleZonePresence
        :param fps: Nilai FPS saat ini
        :return: Frame BGR terannotasi
        """
        if frame is None:
            return frame

        output = frame.copy()
        h, w = output.shape[:2]

        total_bekerja = 0
        total_tidak   = 0

        for zone_id, res in presence_results.items():
            status        = res["status"]
            chair_bbox    = res["chair_bbox"]
            matched_bbox  = res["matched_upper_body_bbox"]
            occ_dur       = res.get("occupied_duration", 0.0)

            verified_name = res.get("verified_employee_name")

            # Tentukan warna, boks, dan label visualizer berdasarkan STATUS (bukan durasi kumulatif)
            if status == "BEKERJA":
                total_bekerja += 1
                draw_box = matched_bbox if matched_bbox is not None else chair_bbox
                color    = self.color_bekerja
                # Hirarki Label:
                # 1. Jika terverifikasi -> Nama (misal: Bili)
                # 2. Jika belum terverifikasi -> 'Bekerja'
                display_label = verified_name if verified_name else "Bekerja"

            else:
                total_tidak += 1
                draw_box = chair_bbox
                color    = self.color_away
                # 3. Jika kosong / tidak di tempat -> Nama Meja (misal: chair_1)
                display_label = zone_id

            x1, y1, x2, y2 = draw_box
            cv2.rectangle(output, (x1, y1), (x2, y2), color, 2)

            (tw, th), baseline = cv2.getTextSize(display_label, self.font, 0.5, 1)
            cv2.rectangle(output, (x1, max(0, y1 - th - 6)), (x1 + tw + 8, y1), color, -1)
            cv2.putText(output, display_label, (x1 + 4, max(12, y1 - 4)),
                        self.font, 0.5, (255, 255, 255), 1, cv2.LINE_AA)

        # --- Bilah Informasi Atas (Top Info Bar) ---
        bar_h = 44
        cv2.rectangle(output, (0, 0), (w, bar_h), (18, 18, 18), -1)

        cv2.putText(output, "Monitoring Kehadiran Personel",
                    (14, 28), self.font, 0.6, (255, 255, 255), 1, cv2.LINE_AA)

        bekerja_text = f"BEKERJA: {total_bekerja}"
        tidak_text   = f"TIDAK DI TEMPAT: {total_tidak}"
        fps_text     = f"FPS: {fps:.1f}"

        cv2.putText(output, bekerja_text,
                    (w - 450, 28), self.font, 0.55, self.color_bekerja, 1, cv2.LINE_AA)
        cv2.putText(output, tidak_text,
                    (w - 280, 28), self.font, 0.55, self.color_away, 1, cv2.LINE_AA)
        cv2.putText(output, fps_text,
                    (w - 90, 28), self.font, 0.5, (180, 180, 180), 1, cv2.LINE_AA)

        return output
