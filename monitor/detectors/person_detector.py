from ultralytics import YOLO
import cv2

class PersonDetector:
    def __init__(self, model_name: str = "yolo11m.pt", confidence: float = 0.1, upper_body_ratio: float = 0.5, imgsz: int = 1280, chair_zones: list = None):
        """
        Pendeteksi Personel YOLO11 dengan Pemotongan Tubuh Bagian Atas (Upper-Body)
        dan Pelacak ByteTrack untuk monitoring kehadiran personel.
        """
        self.model = YOLO(model_name)
        self.confidence = float(confidence)
        self.upper_body_ratio = float(upper_body_ratio)
        self.imgsz = int(imgsz)

    def detect(self, frame):
        """
        Mendeteksi dan melacak personel dalam frame menggunakan ByteTrack.
        """
        if frame is None:
            return []

        results = self.model.track(
            frame,
            verbose=False,
            conf=self.confidence,
            imgsz=self.imgsz,
            classes=[0],
            tracker="bytetrack.yaml",
            persist=True,
        )[0]

        detections = []
        if results.boxes is not None and len(results.boxes) > 0:
            for box in results.boxes:
                conf = float(box.conf[0].cpu().numpy())
                xyxy = box.xyxy[0].cpu().numpy()
                x1, y1, x2, y2 = float(xyxy[0]), float(xyxy[1]), float(xyxy[2]), float(xyxy[3])

                track_id = int(box.id[0].cpu().numpy()) if box.id is not None else None

                full_body_bbox = [int(x1), int(y1), int(x2), int(y2)]
                height = y2 - y1
                y2_upper = y1 + (height * self.upper_body_ratio)
                upper_body_bbox = [int(x1), int(y1), int(x2), int(y2_upper)]

                detections.append({
                    "upper_body_bbox": upper_body_bbox,
                    "full_body_bbox": full_body_bbox,
                    "confidence": conf,
                    "track_id": track_id,
                })

        return detections
