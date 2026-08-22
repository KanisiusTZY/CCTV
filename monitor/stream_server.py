import sys
try:
    if hasattr(sys.stdout, 'reconfigure'):
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    if hasattr(sys.stderr, 'reconfigure'):
        sys.stderr.reconfigure(encoding='utf-8', errors='replace')
except Exception:
    pass

class SafeStdout:
    def __init__(self, stream):
        self.stream = stream
    def write(self, data):
        try:
            if self.stream:
                self.stream.write(data)
                self.stream.flush()
        except OSError:
            pass
    def flush(self):
        try:
            if self.stream:
                self.stream.flush()
        except OSError:
            pass

sys.stdout = SafeStdout(sys.stdout)
sys.stderr = SafeStdout(sys.stderr)
import warnings
warnings.filterwarnings('ignore', category=FutureWarning)
try:
    import onnxruntime
    onnxruntime.set_default_logger_severity(3)
except Exception:
    pass

import os
import sys
import time
import json
import argparse
import threading
import cv2
import numpy as np
from flask import Flask, Response, jsonify, request
# from flask_cors import CORS

# Tambahkan direktori root 'monitor' ke sys.path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from detectors.person_detector import PersonDetector
from rules.rule_zone_presence import RuleZonePresence
from visualizer import Visualizer

try:
    from recognizers.face_recognizer import InsightFaceRecognizer as FaceRecognizerModule
    FACE_RECOGNIZER_TYPE = "InsightFace"
except ImportError:
    from detectors.face_recognizer import HaarFaceRecognizer as FaceRecognizerModule
    FACE_RECOGNIZER_TYPE = "HaarCascade"

app = Flask(__name__)
# CORS(app)

# Global Variables untuk Sharing Stream
latest_frame = None
latest_clean_frame = None
latest_results = {}
current_fps = 0.0
is_running = True
lock = threading.Lock()

# Global Instances
config_data = {}
detector = None
rule_engine = None
face_recognizer = None
visualizer = None
current_source = None

def load_config():
    """Memuat file konfigurasi zona meja dan parameter engine."""
    config_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "config.json")
    if not os.path.exists(config_path):
        print(f"[ERROR StreamServer] File konfigurasi tidak ditemukan: {config_path}")
        sys.exit(1)
    with open(config_path, "r") as f:
        return json.load(f)

def init_engine(source=None):
    """Inisialisasi model YOLO, Face Recognizer, dan Rule Engine Spasial."""
    global config_data, detector, rule_engine, face_recognizer, visualizer, current_source
    
    config_data = load_config()
    current_source = source if source is not None else config_data.get("source", 0)
    
    print(f"[INFO StreamServer] Menggunakan modul Face Recognition Modern berbasis {FACE_RECOGNIZER_TYPE} (SCRFD + ArcFace).")

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
    face_thresh = face_cfg.get("similarity_threshold", 0.22)
    use_gpu = face_cfg.get("use_gpu", False)
    monitor_dir = os.path.dirname(os.path.abspath(__file__))
    faces_dir = os.path.join(monitor_dir, "faces_db")
    try:
        face_recognizer = FaceRecognizerModule(faces_dir, model_name=face_model, similarity_threshold=face_thresh, use_gpu=use_gpu)
    except Exception as e:
        print(f"[ERROR FaceRecognizer Init] {e}")
        face_recognizer = FaceRecognizerModule(faces_dir)
        
    rule_engine = RuleZonePresence(config_data)
    visualizer = Visualizer()
    print(f"[INFO StreamServer] Engine diinisialisasi dengan source: {current_source} | Model: {model_name}")

def video_processing_thread():
    global latest_frame, latest_clean_frame, latest_results, current_fps, is_running, current_source
    
    while is_running:
        opened_source = current_source
        cap_source = int(opened_source) if str(opened_source).isdigit() else opened_source
        if isinstance(cap_source, str) and not cap_source.startswith("rtsp://") and not cap_source.startswith("http://"):
            if not os.path.isabs(cap_source):
                monitor_dir = os.path.dirname(os.path.abspath(__file__))
                candidate = os.path.join(monitor_dir, cap_source)
                if os.path.exists(candidate):
                    cap_source = candidate

        cap = cv2.VideoCapture(cap_source)
        if hasattr(cv2, 'CAP_PROP_BUFFERSIZE'):
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        
        if not cap.isOpened():
            print(f"[ERROR StreamServer] Gagal membuka sumber video: {cap_source}. Mencoba lagi...")
            time.sleep(2)
            continue

        fps_in = cap.get(cv2.CAP_PROP_FPS)
        if fps_in <= 0 or fps_in > 60:
            fps_in = 25.0
            
        frame_count = 0
        prev_time = time.time()
        
        print(f"[INFO StreamServer] Memulai pemrosesan stream dari: {opened_source} ({cap_source})")

        cached_detections = []
        cached_results = {}

        while is_running and cap.isOpened():
            # Deteksi pergantian sumber video secara realtime
            if current_source != opened_source:
                print(f"[INFO StreamServer] Sumber video berganti: {opened_source} -> {current_source}. Memuat ulang VideoCapture...")
                break

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

            try:
                # Frame Skipping (YOLO tiap 3 frame)
                if frame_count % 3 == 0 or not cached_detections:
                    detections = detector.detect(frame)
                    cached_detections = detections
                    cached_results = rule_engine.process(frame, detections, current_time=simulated_time, face_recognizer=face_recognizer)
                else:
                    detections = cached_detections
                    cached_results = rule_engine.process(frame, detections, current_time=simulated_time, face_recognizer=face_recognizer)
            except Exception as e:
                print(f"[ERROR StreamServer Loop] {e}")
                cached_results = {}

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
        print(f"[INFO StreamServer] Stream selesai/dilepas dari: {opened_source}")
        time.sleep(0.5)

def generate_frames():
    """Generator Frame MJPEG untuk dikirim ke Browser via HTTP"""
    global latest_frame, lock, is_running
    last_sent = None
    while is_running:
        with lock:
            frame_bytes = latest_frame
        
        if frame_bytes is not None and frame_bytes != last_sent:
            last_sent = frame_bytes
            try:
                yield (b'--frame\r\n'
                       b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')
            except Exception:
                break
        time.sleep(0.02)

@app.route('/video_feed')
def video_feed():
    """Endpoint Stream Video MJPEG Real-time"""
    return Response(generate_frames(), mimetype='multipart/x-mixed-replace; boundary=frame')

@app.route('/api/snapshot')
def api_snapshot():
    """Mengambil 1 snapshot frame bersih CCTV (format JPEG) untuk keperluan Admin Zone Drawing"""
    global latest_clean_frame, latest_frame, lock
    with lock:
        frame_bytes = latest_clean_frame if latest_clean_frame is not None else latest_frame
    
    if frame_bytes is None:
        blank = np.zeros((480, 640, 3), dtype=np.uint8)
        _, jpeg = cv2.imencode('.jpg', blank)
        return Response(jpeg.tobytes(), mimetype='image/jpeg')
    
    return Response(frame_bytes, mimetype='image/jpeg')

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
    
    print(f"[INFO StreamServer] API /api/set_source dipanggil dengan sumber: {new_src}")
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
            face_recognizer.load_face_database()
            count = len(face_recognizer.known_face_embeddings)
    
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
