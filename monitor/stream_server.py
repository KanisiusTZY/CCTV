import argparse
import cv2
import json
import numpy as np
import os
import re
import sys
import time
import threading

# Prevent OSError [Errno 22] when running as Windows background process
class SafeWriter:
    def __init__(self, original_stream):
        self.original_stream = original_stream
    def write(self, text):
        try:
            if self.original_stream:
                self.original_stream.write(text)
                self.original_stream.flush()
        except Exception:
            pass
    def flush(self):
        try:
            if self.original_stream:
                self.original_stream.flush()
        except Exception:
            pass

sys.stdout = SafeWriter(sys.stdout)
sys.stderr = SafeWriter(sys.stderr)

from flask import Flask, Response, jsonify, request, render_template_string

from detectors.person_detector import PersonDetector
try:
    from recognizers.face_recognizer import InsightFaceRecognizer as FaceRecognizerModule
    print("[INFO StreamServer] Menggunakan modul Face Recognition Modern berbasis InsightFace (SCRFD + ArcFace).")
except Exception as e:
    from detectors.face_recognizer_deprecated import FaceRecognizerModule
    print(f"[WARNING StreamServer] InsightFace belum tersedia, beralih ke modul lama: {e}")
from rules.rule_zone_presence import RuleZonePresence
from visualizer import Visualizer

CONFIG_PATH = "config.json"

app = Flask(__name__)

# Global variables for thread safety
lock = threading.Lock()
current_source = None
detector = None
face_recognizer = None
rule_engine = None
visualizer = None
latest_frame = None
latest_clean_frame = None
latest_results = {}
current_fps = 0.0
is_running = True
config_data = {}

def load_config():
    global config_data
    if os.path.exists(CONFIG_PATH):
        try:
            with open(CONFIG_PATH, "r") as f:
                config_data = json.load(f)
                return config_data
        except Exception as e:
            print(f"[ERROR StreamServer] Gagal membaca {CONFIG_PATH}: {e}")
    config_data = {"source": "f.mp4", "model_name": "yolo11n.pt", "confidence": 0.13, "imgsz": 640}
    return config_data

def init_engine(source_override=None):
    global current_source, detector, face_recognizer, rule_engine, visualizer, config_data
    config_data = load_config()
    current_source = source_override if source_override is not None else config_data.get("source", "f.mp4")
    
    # Inisialisasi Detektor Person
    model_name = config_data.get("model_name", "yolo11n.pt")
    detector = PersonDetector(
        model_name=model_name,
        confidence=config_data.get("confidence", 0.13),
        imgsz=config_data.get("imgsz", 640)
    )
    
    # Inisialisasi Face Recognition Module
    face_cfg = config_data.get("face_recognition", {})
    face_model = face_cfg.get("model_name", "buffalo_s")
    face_thresh = face_cfg.get("similarity_threshold", 0.28)
    use_gpu = face_cfg.get("use_gpu", False)
    try:
        face_recognizer = FaceRecognizerModule("faces_db", model_name=face_model, similarity_threshold=face_thresh, use_gpu=use_gpu)
    except Exception:
        face_recognizer = FaceRecognizerModule("faces_db")
        
    rule_engine = RuleZonePresence(config_data)
    visualizer = Visualizer()
    print(f"[INFO StreamServer] Engine diinisialisasi dengan source: {current_source} | Model: {model_name}")

def video_processing_thread():
    global latest_frame, latest_clean_frame, latest_results, current_fps, is_running, current_source
    
    while is_running:
        cap_source = int(current_source) if str(current_source).isdigit() else current_source
        cap = cv2.VideoCapture(cap_source)
        if hasattr(cv2, 'CAP_PROP_BUFFERSIZE'):
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        
        if not cap.isOpened():
            print(f"[ERROR StreamServer] Gagal membuka sumber video: {current_source}. Mencoba lagi...")
            time.sleep(2)
            continue

        fps_in = cap.get(cv2.CAP_PROP_FPS)
        if fps_in <= 0 or fps_in > 60:
            fps_in = 25.0
            
        frame_count = 0
        prev_time = time.time()
        
        print(f"[INFO StreamServer] Memulai pemrosesan stream dari: {current_source}")

        cached_detections = []
        cached_results = {}

        while is_running and cap.isOpened():
            loop_start = time.time()
            ret, frame = cap.read()
            if not ret or frame is None:
                is_live = str(current_source).isdigit() or str(current_source).startswith("rtsp://") or str(current_source).startswith("http://")
                if not is_live:
                    cap.set(cv2.CAP_PROP_POS_FRAMES, 0)
                    frame_count = 0
                    continue
                else:
                    break

            frame_count += 1
            curr_time = time.time()
            time_diff = curr_time - prev_time
            if time_diff > 0:
                calc_fps = 1.0 / time_diff
                current_fps = 0.9 * current_fps + 0.1 * calc_fps if current_fps > 0 else calc_fps
            prev_time = curr_time

            is_live = str(current_source).isdigit() or str(current_source).startswith("rtsp://") or str(current_source).startswith("http://")
            simulated_time = curr_time if is_live else (frame_count / fps_in)

            # Simpan frame bersih untuk snapshot Admin Zone Drawer
            ret_clean_jpg, clean_jpeg = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 85])

            # Frame Skipping (YOLO tiap 3 frame)
            if frame_count % 3 == 0 or not cached_detections:
                detections = detector.detect(frame)
                cached_detections = detections
                cached_results = rule_engine.process(frame, detections, current_time=simulated_time, face_recognizer=face_recognizer)
            else:
                detections = cached_detections
                cached_results = rule_engine.process(frame, detections, current_time=simulated_time, face_recognizer=face_recognizer)

            # Render Visualizer
            annotated_frame = visualizer.render(frame, cached_results, fps=current_fps)
            
            
            ret_jpg, jpeg = cv2.imencode('.jpg', annotated_frame, [cv2.IMWRITE_JPEG_QUALITY, 75])
            if ret_jpg:
                with lock:
                    latest_frame = jpeg.tobytes()
                    if ret_clean_jpg:
                        latest_clean_frame = clean_jpeg.tobytes()
                    latest_results = cached_results

            # Sinkronisasi Kecepatan Video Lokal
            if not is_live:
                proc_time = time.time() - loop_start
                target_frame_time = 1.0 / fps_in
                if proc_time < target_frame_time:
                    time.sleep(target_frame_time - proc_time)

        cap.release()
        print(f"[INFO StreamServer] Stream selesai/terputus dari: {current_source}")
        time.sleep(1)

def generate_mjpeg_stream():
    """Generator multipart MJPEG stream untuk tag <img> browser"""
    global latest_frame
    while is_running:
        with lock:
            if latest_frame is None:
                time.sleep(0.04)
                continue
            frame_data = latest_frame
        
        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + frame_data + b'\r\n')
        time.sleep(0.033)

@app.route('/health')
@app.route('/healthz')
def health_check():
    return "OK", 200

@app.route('/video_feed')
def video_feed():
    """Endpoint Stream Video MJPEG Real-Time"""
    return Response(generate_mjpeg_stream(),
                    mimetype='multipart/x-mixed-replace; boundary=frame')

@app.route('/api/snapshot')
def api_snapshot():
    """Endpoint untuk mengambil 1 frame gambar bersih (clean frame) untuk Admin Zone Drawer Canvas"""
    with lock:
        frame_bytes = latest_clean_frame if latest_clean_frame is not None else latest_frame
        if frame_bytes is None:
            # Fallback jika belum ada frame: buat gambar placeholder
            blank = np.zeros((480, 640, 3), dtype=np.uint8)
            cv2.putText(blank, "Menunggu Video Feed...", (180, 240), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
            _, jpeg = cv2.imencode('.jpg', blank)
            frame_bytes = jpeg.tobytes()
            
    res = Response(frame_bytes, mimetype='image/jpeg')
    res.headers.add("Access-Control-Allow-Origin", "*")
    res.headers.add("Cache-Control", "no-cache, no-store, must-revalidate")
    return res

@app.route('/api/status')
def api_status():
    """Endpoint REST API JSON untuk membaca status presensi real-time"""
    with lock:
        bekerja_count = sum(1 for z in latest_results.values() if z.get("status") == "BEKERJA") if latest_results else 0
        away_count = sum(1 for z in latest_results.values() if z.get("status") != "BEKERJA") if latest_results else 0
        res = jsonify({
            "status": "online" if latest_results else "initializing",
            "source": current_source,
            "fps": round(current_fps, 1),
            "total_bekerja": bekerja_count,
            "total_away": away_count,
            "zones": latest_results or {}
        })
        res.headers.add("Access-Control-Allow-Origin", "*")
        return res, 200

@app.route('/api/set_source', methods=['POST', 'GET'])
def api_set_source():
    """Endpoint API untuk mengganti sumber video secara dinamis"""
    global current_source, rule_engine
    new_src = request.args.get('source') or (request.json and request.json.get('source'))
    if not new_src:
        return jsonify({"status": "error", "message": "Parameter 'source' diperlukan"}), 400
    
    with lock:
        current_source = new_src
        if rule_engine:
            rule_engine.reset()
    
    return jsonify({
        "status": "success",
        "message": f"Sumber video berhasil diubah menjadi: {new_src}"
    })

@app.route('/api/reload_zones', methods=['POST', 'GET'])
def api_reload_zones():
    """Endpoint API untuk memuat ulang daftar zona meja dari config.json secara dinamis"""
    global rule_engine, config_data
    with lock:
        config_data = load_config()
        if rule_engine:
            rule_engine.chair_zones = config_data.get("chair_zones", [])
            print(f"[INFO StreamServer] Reloaded {len(rule_engine.chair_zones)} zones from config.json")
    
    return jsonify({
        "status": "success",
        "message": f"Zona meja berhasil dimuat ulang! Total: {len(config_data.get('chair_zones', []))} zona",
        "zones": config_data.get("chair_zones", [])
    })

@app.route('/api/reload_faces', methods=['POST', 'GET'])
def api_reload_faces():
    """Endpoint API untuk memuat ulang database wajah dari folder faces_db/ secara dinamis"""
    global face_recognizer
    count = 0
    with lock:
        if face_recognizer and hasattr(face_recognizer, 'reload_database'):
            count = face_recognizer.reload_database()
        elif face_recognizer and hasattr(face_recognizer, 'load_face_database'):
            face_recognizer.known_face_embeddings = {}
            face_recognizer.known_names = []
            face_recognizer.load_face_database()
            count = len(getattr(face_recognizer, 'known_face_embeddings', {}))
    
    return jsonify({
        "status": "success",
        "message": f"Database wajah berhasil di-reload! Total: {count} wajah terdaftar",
        "count": count
    })

@app.route('/')
def index():
    return jsonify({
        "name": "AI CCTV Workplace Monitoring API",
        "status": "online",
        "video_feed": "/video_feed",
        "snapshot": "/api/snapshot",
        "status_api": "/api/status",
        "reload_zones": "/api/reload_zones",
        "reload_faces": "/api/reload_faces"
    })

def main():
    parser = argparse.ArgumentParser(description="Live MJPEG Video Streamer & API Server")
    parser.add_argument("--source", type=str, default=None, help="Sumber video (path file .mp4, index webcam 0/1, atau RTSP URL)")
    parser.add_argument("--port", type=int, default=5000, help="Port server HTTP (default: 5000)")
    args = parser.parse_args()

    init_engine(args.source)

    t = threading.Thread(target=video_processing_thread, daemon=True)
    t.start()

    print(f"\n" + "="*60)
    print(f" SERVER STREAMING & REST API MONITORING BERJALAN")
    print(f" Port HTTP     : {args.port}")
    print(f" Video Feed URL: http://localhost:{args.port}/video_feed")
    print(f" Snapshot URL  : http://localhost:{args.port}/api/snapshot")
    print(f" REST API      : http://localhost:{args.port}/api/status")
    print("="*60 + "\n")

    app.run(host="0.0.0.0", port=args.port, debug=False, threaded=True)

if __name__ == "__main__":
    main()
