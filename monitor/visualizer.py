import cv2
import numpy as np
import time

class Visualizer:
    def __init__(self):
        self.color_bekerja  = (0, 220, 80)       # Hijau BGR (Bekerja / Ada Orang)
        self.color_away     = (0, 60, 220)       # Merah BGR (Kosong / Tidak di Tempat)
        self.font           = cv2.FONT_HERSHEY_SIMPLEX

    def format_duration(self, seconds: float) -> str:
        """Format durasi dalam detik ke format 'XmYYs'."""
        total_sec = int(max(0, seconds))
        mins  = total_sec // 60
        secs  = total_sec % 60
        return f"{mins}m{secs:02d}s"

    def render(self, frame, presence_results: dict, fps: float = 0.0):
        """
        Merender visualisasi monitoring kehadiran standar:
        - KOTAK HIJAU : Zona terisi (BEKERJA / Ada Orang) dengan nama pegawai
        - KOTAK MERAH (Hanya Garis Tepi/Edge Fade In-Out): Zona kosong (Tidak di Tempat)
        """
        if frame is None:
            return frame

        output = frame.copy()
        h, w = output.shape[:2]

        total_bekerja = 0
        total_tidak   = 0

        # Hitung faktor fade in / out untuk garis tepi (alpha 0.3 s.d. 1.0)
        pulse_val = 0.5 + 0.5 * np.sin(time.time() * 3.5)
        pulse_alpha = 0.30 + 0.70 * pulse_val

        for zone_id, res in presence_results.items():
            status        = res["status"]
            chair_bbox    = res["chair_bbox"]
            matched_bbox  = res["matched_upper_body_bbox"]
            verified_name = res.get("verified_employee_name")

            if status == "BEKERJA":
                total_bekerja += 1
                draw_box = matched_bbox if matched_bbox is not None else chair_bbox
                color    = self.color_bekerja
                display_label = verified_name if verified_name else "Bekerja"

                x1, y1, x2, y2 = [int(v) for v in draw_box]
                cv2.rectangle(output, (x1, y1), (x2, y2), color, 2)

                (tw, th), baseline = cv2.getTextSize(display_label, self.font, 0.5, 1)
                cv2.rectangle(output, (x1, max(0, y1 - th - 6)), (x1 + tw + 8, y1), color, -1)
                cv2.putText(output, display_label, (x1 + 4, max(12, y1 - 4)),
                            self.font, 0.5, (255, 255, 255), 1, cv2.LINE_AA)

            else:
                total_tidak += 1
                draw_box = chair_bbox
                display_label = "Tidak di Tempat"

                x1, y1, x2, y2 = [int(v) for v in draw_box]

                # Hanya garis tepi (edges) + badge label dengan efek Fade In / Fade Out
                overlay = output.copy()
                # Garis tepi merah murni (ketebalan 2px, TANPA background fill)
                cv2.rectangle(overlay, (x1, y1), (x2, y2), self.color_away, 2)

                # Badge label "Tidak di Tempat" di atas boks
                (tw, th), baseline = cv2.getTextSize(display_label, self.font, 0.45, 1)
                cv2.rectangle(overlay, (x1, max(0, y1 - th - 6)), (x1 + tw + 8, y1), self.color_away, -1)
                cv2.putText(overlay, display_label, (x1 + 4, max(12, y1 - 4)),
                            self.font, 0.45, (255, 255, 255), 1, cv2.LINE_AA)

                # Alpha blend hanya pada garis tepi & label (fade in / out halus)
                cv2.addWeighted(overlay, pulse_alpha, output, 1.0 - pulse_alpha, 0, output)

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
