@extends('layouts.app')

@section('title', 'Kelola Zona Meja Kerja (Zone Drawer) - AI CCTV Monitor')

@section('content')
<div class="space-y-6">

    <!-- Top Header -->
    <div class="glass-panel p-5 rounded-2xl border border-gray-800 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-white">Visual Zone Drawer (Pengaturan Area Meja)</h2>
                <p class="text-xs text-gray-400">Klik dan tarik mouse pada gambar kamera untuk menggambar kotak zona meja baru</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" onclick="refreshSnapshot()" class="flex items-center gap-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold px-4 py-2.5 rounded-xl border border-gray-700 transition">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Ambil Snapshot Baru
            </button>
            <button type="button" onclick="saveAllZones()" id="btnSave" class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-5 py-2.5 rounded-xl transition shadow-lg shadow-emerald-600/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Simpan Semua Zona
            </button>
        </div>
    </div>

    <!-- Main Workspace Grid (Canvas + Sidebar List) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Canvas Container (8 Cols) -->
        <div class="lg:col-span-8 space-y-4">
            <div class="glass-panel p-4 rounded-2xl border border-gray-800 relative select-none">
                <div class="flex items-center justify-between mb-3 px-2">
                    <div class="flex items-center gap-2 text-xs font-medium text-gray-300">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        <span>Mode Gambar: <strong class="text-indigo-400">Klik & Tarik Mouse</strong> pada area meja</span>
                    </div>
                    <div id="cursorCoord" class="text-xs font-mono text-gray-500">X: 0 | Y: 0</div>
                </div>

                <div class="relative bg-black rounded-xl overflow-hidden border border-gray-800 flex items-center justify-center min-h-[420px]">
                    <canvas id="zoneCanvas" class="cursor-crosshair max-w-full h-auto block"></canvas>
                    <div id="loadingOverlay" class="absolute inset-0 bg-gray-950/80 backdrop-blur-sm flex flex-col items-center justify-center text-center p-6 transition-opacity duration-300">
                        <div class="w-10 h-10 border-4 border-indigo-500/20 border-t-indigo-500 rounded-full animate-spin mb-3"></div>
                        <p class="text-xs text-gray-300 font-medium">Memuat Snapshot Frame CCTV...</p>
                    </div>
                </div>

                <!-- Canvas Control Toolbar -->
                <div class="flex items-center justify-between pt-3 px-2 text-xs text-gray-400">
                    <div class="flex items-center gap-4">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-500/30 border border-emerald-500"></span> Zona Tersimpan</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-indigo-500/30 border border-indigo-400"></span> Zona Aktif/Baru</span>
                    </div>
                    <button type="button" onclick="clearAllZones()" class="text-rose-400 hover:text-rose-300 transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        Reset / Hapus Semua Kotak
                    </button>
                </div>
            </div>
        </div>

        <!-- Sidebar Zone List & Metadata (4 Cols) -->
        <div class="lg:col-span-4 space-y-4">
            <div class="glass-panel p-5 rounded-2xl border border-gray-800 flex flex-col h-[520px]">
                <div class="flex items-center justify-between pb-3 border-b border-gray-800 mb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Daftar Zona Meja (<span id="zoneCount">0</span>)
                    </h3>
                </div>

                <!-- Scrollable List of Zones -->
                <div id="zoneListContainer" class="flex-1 overflow-y-auto space-y-2.5 pr-1">
                    <!-- Dynamic Zone Items populated via JavaScript -->
                </div>

                <!-- Bottom Helper Note -->
                <div class="pt-3 border-t border-gray-800 text-[11px] text-gray-500">
                    💡 <em>Klik "Simpan Semua Zona" setelah selesai agar AI Engine langsung membaca area baru.</em>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
    // Initial Zones data from Database
    let zones = [
        @foreach($zones as $z)
            {
                id: "{{ $z->zone_id }}",
                zone_name: "{{ $z->zone_name }}",
                bbox: [{{ $z->bbox_x1 }}, {{ $z->bbox_y1 }}, {{ $z->bbox_x2 }}, {{ $z->bbox_y2 }}]
            },
        @endforeach
    ];

    const canvas = document.getElementById('zoneCanvas');
    const ctx = canvas.getContext('2d');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const snapshotUrl = '{{ route("admin.zones.snapshot") }}';

    let snapshotImg = new Image();
    let isDrawing = false;
    let startX = 0;
    let startY = 0;
    let currentX = 0;
    let currentY = 0;
    let selectedZoneIndex = -1;

    // Load Snapshot
    function loadSnapshot() {
        loadingOverlay.classList.remove('opacity-0', 'pointer-events-none');
        snapshotImg = new Image();
        snapshotImg.crossOrigin = "anonymous";
        snapshotImg.src = snapshotUrl + '?t=' + new Date().getTime();
        
        snapshotImg.onload = function() {
            canvas.width = snapshotImg.naturalWidth || 640;
            canvas.height = snapshotImg.naturalHeight || 480;
            redrawCanvas();
            renderZoneList();
            loadingOverlay.classList.add('opacity-0', 'pointer-events-none');
        };

        snapshotImg.onerror = function() {
            canvas.width = 640;
            canvas.height = 480;
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(0, 0, 640, 480);
            ctx.fillStyle = '#94a3b8';
            ctx.font = '14px Inter, sans-serif';
            ctx.fillText('Gagal memuat snapshot. Pastikan Python Engine aktif di port 5000.', 100, 240);
            loadingOverlay.classList.add('opacity-0', 'pointer-events-none');
        };
    }

    function refreshSnapshot() {
        loadSnapshot();
    }

    // Get Mouse Canvas Position
    function getCanvasCoordinates(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        return {
            x: Math.round((e.clientX - rect.left) * scaleX),
            y: Math.round((e.clientY - rect.top) * scaleY)
        };
    }

    // Canvas Event Listeners
    canvas.addEventListener('mousedown', function(e) {
        const pos = getCanvasCoordinates(e);
        isDrawing = true;
        startX = pos.x;
        startY = pos.y;
        currentX = pos.x;
        currentY = pos.y;
    });

    canvas.addEventListener('mousemove', function(e) {
        const pos = getCanvasCoordinates(e);
        document.getElementById('cursorCoord').innerText = `X: ${pos.x} | Y: ${pos.y}`;

        if (isDrawing) {
            currentX = pos.x;
            currentY = pos.y;
            redrawCanvas();
            drawPreviewRect(startX, startY, currentX, currentY);
        }
    });

    canvas.addEventListener('mouseup', function(e) {
        if (!isDrawing) return;
        isDrawing = false;
        const pos = getCanvasCoordinates(e);

        const x1 = Math.min(startX, pos.x);
        const y1 = Math.min(startY, pos.y);
        const x2 = Math.max(startX, pos.x);
        const y2 = Math.max(startY, pos.y);

        // Hanya simpan jika ukuran kotak cukup besar (minimal 20x20 px)
        if ((x2 - x1) >= 20 && (y2 - y1) >= 20) {
            const nextNum = getNextZoneNumber();
            const newZone = {
                id: `chair_${nextNum}`,
                zone_name: `Meja ${nextNum}`,
                bbox: [x1, y1, x2, y2]
            };
            zones.push(newZone);
            selectedZoneIndex = zones.length - 1;
        }

        redrawCanvas();
        renderZoneList();
    });

    function getNextZoneNumber() {
        let maxNum = 0;
        zones.forEach(z => {
            const num = parseInt(z.id.replace('chair_', ''));
            if (!isNaN(num) && num > maxNum) maxNum = num;
        });
        return maxNum + 1;
    }

    function drawPreviewRect(x1, y1, x2, y2) {
        const bx = Math.min(x1, x2);
        const by = Math.min(y1, y2);
        const bw = Math.abs(x2 - x1);
        const bh = Math.abs(y2 - y1);

        ctx.strokeStyle = '#6366f1';
        ctx.lineWidth = 2;
        ctx.setLineDash([6, 6]);
        ctx.strokeRect(bx, by, bw, bh);

        ctx.fillStyle = 'rgba(99, 102, 241, 0.2)';
        ctx.fillRect(bx, by, bw, bh);
        ctx.setLineDash([]);
    }

    function redrawCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (snapshotImg.complete && snapshotImg.naturalWidth > 0) {
            ctx.drawImage(snapshotImg, 0, 0, canvas.width, canvas.height);
        }

        // Draw All Zones
        zones.forEach((z, idx) => {
            const [x1, y1, x2, y2] = z.bbox;
            const w = x2 - x1;
            const h = y2 - y1;
            const isSelected = (idx === selectedZoneIndex);

            // Box Fill & Stroke
            ctx.fillStyle = isSelected ? 'rgba(99, 102, 241, 0.25)' : 'rgba(34, 197, 94, 0.15)';
            ctx.fillRect(x1, y1, w, h);

            ctx.strokeStyle = isSelected ? '#818cf8' : '#22c55e';
            ctx.lineWidth = isSelected ? 3 : 2;
            ctx.strokeRect(x1, y1, w, h);

            // Label Badge
            const label = z.zone_name || z.id;
            ctx.font = 'bold 12px Inter, sans-serif';
            const textMetrics = ctx.measureText(label);
            const badgeW = textMetrics.width + 12;
            const badgeH = 20;

            ctx.fillStyle = isSelected ? '#4f46e5' : '#16a34a';
            ctx.fillRect(x1, Math.max(0, y1 - badgeH), badgeW, badgeH);

            ctx.fillStyle = '#ffffff';
            ctx.fillText(label, x1 + 6, Math.max(0, y1 - badgeH) + 14);
        });
    }

    function renderZoneList() {
        const container = document.getElementById('zoneListContainer');
        document.getElementById('zoneCount').innerText = zones.length;
        container.innerHTML = '';

        if (zones.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12 text-gray-500 text-xs">
                    Belum ada zona meja. Klik & tarik kursor pada gambar kamera di sebelah kiri untuk menambah zona baru.
                </div>
            `;
            return;
        }

        zones.forEach((z, idx) => {
            const isSelected = (idx === selectedZoneIndex);
            const card = document.createElement('div');
            card.className = `p-3 rounded-xl border transition-all duration-200 cursor-pointer ${
                isSelected ? 'bg-indigo-500/10 border-indigo-500/40 shadow-lg' : 'bg-gray-900/60 border-gray-800 hover:border-gray-700'
            }`;
            card.onclick = () => {
                selectedZoneIndex = idx;
                redrawCanvas();
                renderZoneList();
            };

            card.innerHTML = `
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-mono font-bold text-indigo-400 uppercase">${z.id}</span>
                    <button onclick="event.stopPropagation(); deleteZone(${idx});" class="text-gray-500 hover:text-rose-400 p-1 rounded transition" title="Hapus Zona">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
                <div class="space-y-1.5">
                    <input type="text" 
                           value="${z.zone_name || ''}" 
                           onchange="updateZoneName(${idx}, this.value)"
                           placeholder="Nama Meja (misal: Meja 1)"
                           class="w-full bg-gray-950 border border-gray-800 text-white text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-indigo-500">
                    <div class="text-[10px] font-mono text-gray-500 flex justify-between">
                        <span>X: [${z.bbox[0]}, ${z.bbox[2]}]</span>
                        <span>Y: [${z.bbox[1]}, ${z.bbox[3]}]</span>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    }

    function updateZoneName(idx, newName) {
        if (zones[idx]) {
            zones[idx].zone_name = newName.trim();
            redrawCanvas();
        }
    }

    function deleteZone(idx) {
        zones.splice(idx, 1);
        if (selectedZoneIndex >= zones.length) selectedZoneIndex = zones.length - 1;
        redrawCanvas();
        renderZoneList();
    }

    function clearAllZones() {
        if (confirm('Yakin ingin menghapus semua kotak zona meja?')) {
            zones = [];
            selectedZoneIndex = -1;
            redrawCanvas();
            renderZoneList();
        }
    }

    async function saveAllZones() {
        const btn = document.getElementById('btnSave');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="animate-spin mr-1">⏳</span> Menyimpan...`;

        try {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const response = await fetch('{{ route("admin.zones.save-all") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ zones: zones })
            });

            const resData = await response.json();
            if (response.ok) {
                alert('✅ ' + (resData.message || 'Zona meja berhasil disimpan!'));
            } else {
                alert('❌ Gagal menyimpan zona: ' + (resData.message || 'Terjadi kesalahan'));
            }
        } catch (err) {
            alert('❌ Gagal menghubungi server: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    // Auto initialize on page load
    window.addEventListener('DOMContentLoaded', loadSnapshot);
</script>
@endsection
