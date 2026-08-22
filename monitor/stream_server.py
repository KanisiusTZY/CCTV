import argparse
import cv2
import json
import os
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
config_data = {}
latest_frame = None
latest_results = {}
current_fps = 0.0
is_running = True

def load_config():
    if not os.path.exists(CONFIG_PATH):
        print(f"[ERROR] File konfigurasi '{CONFIG_PATH}' tidak ditemukan!")
        sys.exit(1)
    with open(CONFIG_PATH, "r") as f:
        return json.load(f)

def init_engine(source_override=None):
    global detector, face_recognizer, rule_engine, visualizer, config_data, current_source
    config_data = load_config()
    current_source = source_override if source_override is not None else config_data.get("source", "video.mp4")
    
    chair_zones = config_data.get("chair_zones", [])
    detector = PersonDetector(
        model_name=config_data.get("model_name", "yolo11n.pt"),
        confidence=config_data.get("confidence", 0.13),
        upper_body_ratio=config_data.get("upper_body_ratio", 0.5),
        imgsz=config_data.get("imgsz", 640),
        chair_zones=chair_zones
    )
    face_cfg = config_data.get("face_recognition", {})
    face_model = face_cfg.get("model_name", "buffalo_s")
    face_thresh = face_cfg.get("similarity_threshold", 0.40)
    use_gpu = face_cfg.get("use_gpu", False)
    try:
        face_recognizer = FaceRecognizerModule("faces_db", model_name=face_model, similarity_threshold=face_thresh, use_gpu=use_gpu)
    except Exception:
        face_recognizer = FaceRecognizerModule("faces_db")
    rule_engine = RuleZonePresence(config_data)
    visualizer = Visualizer()
    print(f"[INFO StreamServer] Engine diinisialisasi dengan source: {current_source} | Model: {config_data.get('model_name', 'yolo11n.pt')} | ImgSz: {config_data.get('imgsz', 640)}")

def video_processing_thread():
    global latest_frame, latest_results, current_fps, is_running, current_source
    
    while is_running:
        cap_source = int(current_source) if str(current_source).isdigit() else current_source
        cap = cv2.VideoCapture(cap_source)
        if hasattr(cv2, 'CAP_PROP_BUFFERSIZE'):
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        
        if not cap.isOpened():
            print(f"[ERROR StreamServer] Gagal membuka sumber video: {current_source}. Mencoba lagi dalam 3 detik...")
            time.sleep(3)
            continue

        fps_in = cap.get(cv2.CAP_PROP_FPS)
        if fps_in <= 0 or fps_in > 60:
            fps_in = 25.0
            
        frame_count = 0
        prev_time = time.time()
        
        print(f"[INFO StreamServer] Memulai pemrosesan stream dari: {current_source}")

        while is_running and cap.isOpened():
            loop_start = time.time()
            ret, frame = cap.read()
            if not ret or frame is None:
                is_live = str(current_source).isdigit() or str(current_source).startswith("rtsp://") or str(current_source).startswith("http://")
                if not is_live:
                    # Reset video loop jika file mp4 habis (Tetap simpan akumulasi durasi presensi)
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

            # Frame Skipping Optimization (Jalankan deteksi YOLO tiap 3 frame untuk kecepatan 3x lipat)
            if frame_count % 3 == 1 or 'last_detections' not in locals():
                last_detections = detector.detect(frame)

            # Detect & Process
            presence_results = rule_engine.process(frame, last_detections, current_time=simulated_time, face_recognizer=face_recognizer)
            annotated_frame = visualizer.render(frame, presence_results, fps=current_fps)

            # Encode to JPEG for MJPEG stream
            ret_jpg, jpeg = cv2.imencode('.jpg', annotated_frame, [int(cv2.IMWRITE_JPEG_QUALITY), 75])
            if ret_jpg:
                with lock:
                    latest_frame = jpeg.tobytes()
                    latest_results = presence_results

            # Pacing Pengereman Halus untuk Mencegah CPU Overheat Thermal Throttling
            if not is_live:
                target_frame_time = 1.0 / min(fps_in, 30.0)
                elapsed = time.time() - loop_start
                sleep_time = max(0.002, target_frame_time - elapsed)
                time.sleep(sleep_time)

        cap.release()
        print(f"[INFO StreamServer] Stream dihentikan/di-reset.")

def generate_mjpeg_stream():
    global latest_frame
    while is_running:
        with lock:
            if latest_frame is None:
                time.sleep(0.05)
                continue
            frame_data = latest_frame

        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + frame_data + b'\r\n')
        time.sleep(0.03)

@app.route('/video_feed')
def video_feed():
    """Endpoint HTTP MJPEG Streaming untuk Browser & Laravel <img> tag"""
    return Response(generate_mjpeg_stream(),
                    mimetype='multipart/x-mixed-replace; boundary=frame')

@app.route('/api/status')
def api_status():
    """Endpoint REST API JSON untuk membaca status presensi real-time"""
    with lock:
        bekerja_count = sum(1 for z in latest_results.values() if z.get("status") == "BEKERJA")
        away_count = sum(1 for z in latest_results.values() if z.get("status") != "BEKERJA")
        res = jsonify({
            "source": current_source,
            "fps": round(current_fps, 1),
            "total_bekerja": bekerja_count,
            "total_away": away_count,
            "zones": latest_results
        })
        res.headers.add("Access-Control-Allow-Origin", "*")
        return res

@app.route('/api/set_source', methods=['POST', 'GET'])
def api_set_source():
    """Endpoint API untuk mengganti sumber video secara dinamis (misal dari Laravel)"""
    global current_source, rule_engine
    new_src = request.args.get('source') or (request.json and request.json.get('source'))
    if not new_src:
        return jsonify({"status": "error", "message": "Parameter 'source' diperlukan"}), 400
    
    with lock:
        current_source = new_src
        rule_engine.reset()
    
    return jsonify({
        "status": "success",
        "message": f"Sumber video berhasil diubah menjadi: {new_src}"
    })

@app.route('/')
def index():
    """Dashboard Web HTML Sederhana untuk Testing Live Stream"""
    html_content = """
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Live Streaming Monitoring Kehadiran Pegawai</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 20px; }
            .container { max-width: 1200px; margin: 0 auto; }
            header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; padding-bottom: 15px; margin-bottom: 20px; }
            h1 { font-size: 24px; color: #38bdf8; margin: 0; }
            .main-content { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
            .video-card { background: #1e293b; border-radius: 12px; padding: 15px; border: 1px solid #334155; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); }
            .video-card img { width: 100%; height: auto; border-radius: 8px; display: block; }
            .side-card { background: #1e293b; border-radius: 12px; padding: 20px; border: 1px solid #334155; }
            .stat-box { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
            .stat-card { background: #0f172a; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #334155; }
            .stat-val { font-size: 28px; font-weight: bold; }
            .val-bekerja { color: #10b981; }
            .val-away { color: #ef4444; }
            .stat-lbl { font-size: 12px; color: #94a3b8; margin-top: 4px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 6px; }
            input[type="text"] { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: #fff; box-sizing: border-box; }
            button { width: 100%; padding: 10px; background: #0284c7; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.2s; }
            button:hover { background: #0369a1; }
            .zone-list { margin-top: 15px; max-height: 250px; overflow-y: auto; }
            .zone-item { display: flex; justify-content: space-between; padding: 8px 12px; background: #0f172a; border-radius: 6px; margin-bottom: 6px; font-size: 13px; }
            .badge { padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; }
            .badge-bekerja { background: #065f46; color: #34d399; }
            .badge-away { background: #991b1b; color: #fca5a5; }
        </style>
    </head>
    <body>
        <div class="container">
            <header>
                <h1>Sistem Monitoring Kehadiran Pegawai - Live Streaming</h1>
                <div id="fps-badge" style="background:#0284c7; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: bold;">FPS: --</div>
            </header>
            
            <div class="main-content">
                <div class="video-card">
                    <img src="/video_feed" alt="Live CCTV Monitoring Stream">
                </div>
                
                <div class="side-card">
                    <div class="stat-box">
                        <div class="stat-card">
                            <div class="stat-val val-bekerja" id="total-bekerja">0</div>
                            <div class="stat-lbl">BEKERJA</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-val val-away" id="total-away">0</div>
                            <div class="stat-lbl">TIDAK DI TEMPAT</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="src-input">Ganti Sumber Video (File MP4 / RTSP URL / Webcam Index):</label>
                        <input type="text" id="src-input" placeholder="misal: video.mp4 atau rtsp://..." value="{{ source }}">
                        <button onclick="changeSource()" style="margin-top: 8px;">Terapkan Sumber Video</button>
                    </div>

                    <h3 style="font-size: 14px; color: #38bdf8; margin-top: 20px; margin-bottom: 10px;">Status Zona Meja Real-Time</h3>
                    <div class="zone-list" id="zone-container">
                        <!-- Statis item di-populate via JS -->
                    </div>
                </div>
            </div>
        </div>

        <script>
            function changeSource() {
                const src = document.getElementById('src-input').value;
                fetch('/api/set_source?source=' + encodeURIComponent(src))
                    .then(res => res.json())
                    .then(data => alert(data.message));
            }

            function updateStatus() {
                fetch('/api/status')
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById('fps-badge').innerText = 'FPS: ' + data.fps;
                        document.getElementById('total-bekerja').innerText = data.total_bekerja;
                        document.getElementById('total-away').innerText = data.total_away;
                        
                        const container = document.getElementById('zone-container');
                        container.innerHTML = '';
                        for (const [zid, zdata] of Object.entries(data.zones)) {
                            const isBekerja = zdata.status === 'BEKERJA';
                            const item = document.createElement('div');
                            item.className = 'zone-item';
                            item.innerHTML = `
                                <span><strong>${zid}</strong> ${zdata.track_id ? '(Track: ' + zdata.track_id + ')' : ''}</span>
                                <span class="badge ${isBekerja ? 'badge-bekerja' : 'badge-away'}">${zdata.status}</span>
                            `;
                            container.appendChild(item);
                        }
                    });
            }

            setInterval(updateStatus, 1000);
        </script>
    </body>
    </html>
    """
    return render_template_string(html_content, source=current_source)

def main():
    parser = argparse.ArgumentParser(description="Live MJPEG Video Streamer & API Server")
    parser.add_argument("--source", type=str, default=None, help="Sumber video (path file .mp4, index webcam 0/1, atau RTSP URL)")
    parser.add_argument("--port", type=int, default=5000, help="Port server HTTP (default: 5000)")
    args = parser.parse_args()

    init_engine(args.source)

    # Jalankan background thread pemrosesan video AI
    t = threading.Thread(target=video_processing_thread, daemon=True)
    t.start()

    print(f"\n" + "="*60)
    print(f" SERVER STREAMING & REST API MONITORING BERJALAN")
    print(f" Web Dashboard : http://localhost:{args.port}")
    print(f" Video Feed URL: http://localhost:{args.port}/video_feed")
    print(f" REST API Status: http://localhost:{args.port}/api/status")
    print("="*60 + "\n")

    app.run(host="0.0.0.0", port=args.port, debug=False, threaded=True)

if __name__ == "__main__":
    main()
