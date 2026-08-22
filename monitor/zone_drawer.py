import cv2
import json
import os
import sys

CONFIG_PATH = "config.json"

chair_zones = []
scale_x = 1.0
scale_y = 1.0

drawing = False
start_pos = None
current_rect = None  # (x1, y1, x2, y2)

def load_config():
    if os.path.exists(CONFIG_PATH):
        try:
            with open(CONFIG_PATH, "r") as f:
                return json.load(f)
        except Exception as e:
            print(f"[ERROR] Gagal membaca {CONFIG_PATH}: {e}")
    return {
        "source": "video.mp4",
        "confidence": 0.03,
        "upper_body_ratio": 0.5,
        "iou_threshold": 0.08,
        "enter_seconds": 0.5,
        "exit_seconds": 2.5,
        "miss_tolerance_seconds": 2.5,
        "chair_zones": []
    }

def save_config(config_data):
    with open(CONFIG_PATH, "w") as f:
        json.dump(config_data, f, indent=2)
    print(f"[INFO] Konfigurasi berhasil disimpan ke {CONFIG_PATH}")

def mouse_callback(event, x, y, flags, param):
    global drawing, start_pos, current_rect, chair_zones, scale_x, scale_y

    real_x = int(x * scale_x)
    real_y = int(y * scale_y)

    if event == cv2.EVENT_LBUTTONDOWN:
        drawing = True
        start_pos = (real_x, real_y)
        current_rect = (real_x, real_y, real_x, real_y)

    elif event == cv2.EVENT_MOUSEMOVE and drawing:
        if start_pos:
            sx, sy = start_pos
            current_rect = (min(sx, real_x), min(sy, real_y), max(sx, real_x), max(sy, real_y))

    elif event == cv2.EVENT_LBUTTONUP and drawing:
        drawing = False
        if current_rect:
            x1, y1, x2, y2 = current_rect
            if abs(x2 - x1) > 10 and abs(y2 - y1) > 10:
                zone_id = f"chair_{len(chair_zones) + 1}"
                chair_zones.append({
                    "id": zone_id,
                    "bbox": [x1, y1, x2, y2]
                })
                print(f"[ZONA DITAMBAHKAN] {zone_id}: {[x1, y1, x2, y2]}")
            current_rect = None

def main():
    global chair_zones, current_rect, scale_x, scale_y

    config = load_config()
    source = sys.argv[1] if len(sys.argv) > 1 else config.get("source", "video.mp4")
    config["source"] = source

    cap_source = int(source) if str(source).isdigit() else source
    cap = cv2.VideoCapture(cap_source)

    if not cap.isOpened():
        print(f"[ERROR] Tidak dapat membuka sumber video: {source}")
        return

    ret, frame = cap.read()
    cap.release()

    if not ret or frame is None:
        print(f"[ERROR] Gagal membaca frame pertama dari: {source}")
        return

    orig_h, orig_w = frame.shape[:2]

    target_h = 850
    display_h = target_h
    display_w = int(orig_w * (target_h / float(orig_h)))

    scale_x = orig_w / float(display_w)
    scale_y = orig_h / float(display_h)

    chair_zones = config.get("chair_zones", [])

    window_name = "SKYNET Zone Drawer - Mode Persegi Panjang Sederhana"
    cv2.namedWindow(window_name, cv2.WINDOW_AUTOSIZE)
    cv2.setMouseCallback(window_name, mouse_callback)

    print("\n" + "="*60)
    print(" PETUNJUK SKYNET ZONE DRAWER (RECTANGLE MODE)")
    print("="*60)
    print(" - Drag Mouse Kiri  : Gambar Kotak Zona Kursi Persegi Panjang")
    print(" - Tekan 's'        : Simpan zona ke config.json & keluar")
    print(" - Tekan 'z'        : Batal (Undo) zona terakhir")
    print(" - Tekan 'r'        : Reset / hapus semua zona")
    print(" - Tekan 'q' / ESC  : Keluar tanpa menyimpan")
    print("="*60 + "\n")

    while True:
        try:
            display = cv2.resize(frame, (display_w, display_h))
        except Exception:
            display = frame.copy()

        # Gambar zona-zona yang sudah tersimpan
        for zone in chair_zones:
            x1, y1, x2, y2 = zone["bbox"]
            dx1, dy1 = int(x1 / scale_x), int(y1 / scale_y)
            dx2, dy2 = int(x2 / scale_x), int(y2 / scale_y)
            cv2.rectangle(display, (dx1, dy1), (dx2, dy2), (255, 191, 0), 2)
            cv2.putText(display, zone["id"], (dx1, max(dy1 - 8, 15)),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 191, 0), 2)

        # Gambar draft kotak persegi panjang yang sedang di-drag
        if current_rect:
            cx1, cy1, cx2, cy2 = current_rect
            dcx1, dcy1 = int(cx1 / scale_x), int(cy1 / scale_y)
            dcx2, dcy2 = int(cx2 / scale_x), int(cy2 / scale_y)
            cv2.rectangle(display, (dcx1, dcy1), (dcx2, dcy2), (0, 255, 255), 2)

            temp_id = f"chair_{len(chair_zones) + 1}"
            cv2.putText(display, temp_id, (dcx1, max(dcy1 - 8, 15)),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 255), 2)

        info_text = f"Zona: {len(chair_zones)} | 's': Simpan | 'z': Undo | 'r': Reset | 'q': Keluar"
        cv2.rectangle(display, (0, display_h - 35), (display_w, display_h), (30, 30, 30), -1)
        cv2.putText(display, info_text, (12, display_h - 12),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.52, (255, 255, 255), 1, cv2.LINE_AA)

        cv2.imshow(window_name, display)
        key = cv2.waitKey(20) & 0xFF

        if key == ord('s'):
            config["chair_zones"] = chair_zones
            save_config(config)
            print(f"[BERHASIL] {len(chair_zones)} zona disimpan ke {CONFIG_PATH}.")
            break
        elif key == ord('z'):
            if chair_zones:
                removed_z = chair_zones.pop()
                print(f"[UNDO] Menghapus zona {removed_z['id']}")
        elif key == ord('r'):
            chair_zones.clear()
            print("[RESET] Semua zona dibersihkan.")
        elif key == ord('q') or key == 27:
            print("[KELUAR] Keluar tanpa menyimpan.")
            break

    cv2.destroyAllWindows()

if __name__ == "__main__":
    main()
