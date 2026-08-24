@extends('layouts.app')

@section('title', 'Kelola Meja & Pegawai - AI CCTV Monitor')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">

    <!-- Top Header -->
    <div class="glass-panel p-5 rounded-2xl border border-gray-800 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 flex items-center justify-center shadow-lg shadow-indigo-500/20 shrink-0">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-white">Kelola Meja & Penugasan Pegawai (Visual Workspace Manager)</h2>
                <p class="text-xs text-gray-400">Gambar area meja pada kamera dan langsung tentukan pegawai yang menempatinya tanpa lupa posisi</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" onclick="refreshSnapshot()" class="flex items-center gap-1.5 bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold px-3.5 py-2.5 rounded-xl border border-gray-700 transition">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Snapshot Baru
            </button>
            <button type="button" onclick="openEmployeeModal()" class="flex items-center gap-1.5 bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-lg shadow-purple-600/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                + Pegawai Baru
            </button>
            <button type="button" onclick="saveAllZones()" id="btnSave" class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-5 py-2.5 rounded-xl transition shadow-lg shadow-emerald-600/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Simpan Semua Perubahan
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-xl text-xs flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- SECTION 1: Interactive Canvas Zone Drawer & Direct Assign Sidebar -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Canvas Container (7 Cols) -->
        <div class="lg:col-span-7 space-y-4">
            <div class="glass-panel p-4 rounded-2xl border border-gray-800 relative select-none">
                <div class="flex items-center justify-between mb-3 px-2">
                    <div class="flex items-center gap-2 text-xs font-medium text-gray-300">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        <span>Mode Gambar: <strong class="text-indigo-400">Klik & Tarik Mouse</strong> pada area meja CCTV</span>
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
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-500/30 border border-emerald-500"></span> Terisi Pegawai</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-500/30 border border-amber-500"></span> Belum Ada Pegawai</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-indigo-500/30 border border-indigo-400"></span> Meja Terpilih</span>
                    </div>
                    <button type="button" onclick="clearAllZones()" class="text-rose-400 hover:text-rose-300 transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        Reset / Hapus Semua Meja
                    </button>
                </div>
            </div>
        </div>

        <!-- Sidebar Zone & Direct Employee Assignment List (5 Cols) -->
        <div class="lg:col-span-5 space-y-4">
            <div class="glass-panel p-5 rounded-2xl border border-gray-800 flex flex-col h-[540px]">
                <div class="flex items-center justify-between pb-3 border-b border-gray-800 mb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Daftar Meja & Pegawai (<span id="zoneCount">0</span>)
                    </h3>
                    <span class="text-[11px] text-gray-400">Pilih pegawai langsung per meja</span>
                </div>

                <!-- Scrollable List of Zones -->
                <div id="zoneListContainer" class="flex-1 overflow-y-auto space-y-3 pr-1">
                    <!-- Dynamic Zone Items populated via JavaScript -->
                </div>

                <!-- Bottom Helper Note -->
                <div class="pt-3 border-t border-gray-800 flex items-center justify-between text-[11px] text-gray-400">
                    <span>💡 <em>Klik kartu untuk sorot meja di CCTV</em></span>
                    <button type="button" onclick="saveAllZones()" class="text-emerald-400 hover:text-emerald-300 font-medium">Simpan Perubahan &rarr;</button>
                </div>
            </div>
        </div>

    </div>

    <!-- SECTION 2: Master Database Pegawai & Foto Wajah AI -->
    <div class="glass-panel p-5 rounded-2xl border border-gray-800 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-800">
            <div>
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Master Database Pegawai & Registrasi Wajah AI (Total: {{ $employees->count() }})
                </h3>
                <p class="text-xs text-gray-400">Daftar semua pegawai terdaftar beserta foto wajah selfie untuk model pengenalan AI</p>
            </div>
            <div class="flex items-center gap-2">
                <form action="{{ route('admin.employees.reload') }}" method="POST">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold px-3 py-2 rounded-xl border border-gray-700 transition">
                        <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Reload AI Face DB
                    </button>
                </form>
                <button type="button" onclick="openEmployeeModal()" class="flex items-center gap-1.5 bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold px-3.5 py-2 rounded-xl transition shadow-lg shadow-purple-600/20">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Pegawai
                </button>
            </div>
        </div>

        <!-- Employee Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3.5">
            @forelse($employees as $emp)
                <div class="bg-gray-900/90 border border-gray-800 rounded-xl p-3.5 flex flex-col justify-between hover:border-gray-700 transition space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl overflow-hidden bg-gray-800 shrink-0 border border-gray-700">
                            @if($emp->photo_filename && file_exists(public_path('uploads/employees/' . $emp->photo_filename)))
                                <img src="{{ asset('uploads/employees/' . $emp->photo_filename) }}" alt="{{ $emp->name }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-400 bg-purple-950/30">
                                    {{ strtoupper(substr($emp->name, 0, 2)) }}
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-xs font-bold text-white truncate">{{ $emp->name }}</h4>
                            <p class="text-[11px] text-gray-400 truncate">{{ $emp->position ?? 'Staff' }}</p>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @if($emp->assigned_zone_id)
                                    <span class="inline-flex items-center gap-1 text-[10px] text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/20 font-medium">
                                        📍 {{ $emp->zone->zone_name ?? $emp->assigned_zone_id }}
                                    </span>
                                @endif
                                @if($emp->phone_number)
                                    <span class="inline-flex items-center gap-1 text-[10px] text-green-400 bg-green-500/10 px-1.5 py-0.5 rounded border border-green-500/20 font-mono">
                                        📱 {{ $emp->phone_number }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center text-[10px] text-indigo-300 bg-indigo-500/10 px-1.5 py-0.5 rounded border border-indigo-500/20">
                                    ⏱️ {{ $emp->max_away_minutes ?? 15 }}m
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-1.5 pt-2 border-t border-gray-800/80">
                        <button type="button" onclick="editEmployee({{ $emp->id }}, '{{ addslashes($emp->name) }}', '{{ addslashes($emp->position ?? '') }}', '{{ $emp->assigned_zone_id }}', '{{ $emp->phone_number ?? '' }}', {{ $emp->max_away_minutes ?? 15 }})" class="p-1.5 text-gray-400 hover:text-indigo-400 hover:bg-gray-800 rounded-lg transition" title="Edit Pegawai">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        </button>
                        <form action="{{ route('admin.employees.destroy', $emp->id) }}" method="POST" onsubmit="return confirm('Hapus pegawai {{ $emp->name }}? Foto wajah di database AI juga akan terhapus.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1.5 text-gray-400 hover:text-rose-400 hover:bg-gray-800 rounded-lg transition" title="Hapus Pegawai">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-8 text-center text-gray-500 text-xs">
                    Belum ada data pegawai. Klik "+ Pegawai Baru" untuk mendaftarkan pegawai dan mengunggah foto wajah selfie.
                </div>
            @endforelse
        </div>
    </div>

</div>

<!-- MODAL TAMBAH / EDIT PEGAWAI -->
<div id="employeeModal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-800">
            <h3 id="modalTitle" class="text-sm font-bold text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                Pendaftaran Pegawai Baru
            </h3>
            <button type="button" onclick="closeEmployeeModal()" class="text-gray-400 hover:text-white p-1 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <form id="employeeForm" action="{{ route('admin.employees.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">

            <div>
                <label class="text-xs text-gray-400 mb-1 block">Nama Lengkap Pegawai <span class="text-rose-400">*</span></label>
                <input type="text" name="name" id="empName" required placeholder="misal: Bili, Gea, Rangga, Bunga..." class="w-full bg-gray-950 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Posisi / Jabatan</label>
                    <input type="text" name="position" id="empPosition" placeholder="misal: Staff IT" class="w-full bg-gray-950 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Nomor WhatsApp</label>
                    <input type="text" name="phone_number" id="empPhone" placeholder="misal: 08123456789" class="w-full bg-gray-950 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Penempatan Meja</label>
                    <select name="assigned_zone_id" id="empZone" class="w-full bg-gray-950 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500">
                        <option value="">-- Fleksibel --</option>
                        @foreach($zones as $z)
                            <option value="{{ $z->zone_id }}">{{ $z->zone_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-400 mb-1 block">Batas Away (Menit)</label>
                    <input type="number" name="max_away_minutes" id="empMaxAway" value="15" min="1" max="180" class="w-full bg-gray-950 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div>
                <label class="text-xs text-gray-400 mb-1 block">Foto Wajah Selfie (InsightFace AI) <span id="photoRequired" class="text-rose-400">*</span></label>
                <input type="file" name="photo" id="empPhoto" accept="image/*" class="w-full bg-gray-950 border border-gray-700 text-gray-300 text-xs rounded-xl p-2 focus:outline-none file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-purple-600 file:text-white hover:file:bg-purple-500">
                <p class="text-[11px] text-gray-500 mt-1">Gunakan foto selfie wajah tampak depan yang jelas dan terang untuk akurasi pengenalan optimal.</p>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-gray-800">
                <button type="button" onclick="closeEmployeeModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold rounded-xl transition">Batal</button>
                <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold rounded-xl transition shadow-lg shadow-purple-600/20">Simpan Pegawai</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Master data pegawai dari PHP
    const employeeData = [
        @foreach($employees as $e)
            {
                id: {{ $e->id }},
                name: "{{ addslashes($e->name) }}",
                position: "{{ addslashes($e->position ?? '') }}",
                zone_id: "{{ $e->assigned_zone_id ?? '' }}",
                photo: "{{ $e->photo_filename ? asset('uploads/employees/' . $e->photo_filename) : '' }}"
            },
        @endforeach
    ];

    // Initial Zones data from Database
    let zones = [
        @foreach($zones as $z)
            {
                id: "{{ $z->zone_id }}",
                zone_name: "{{ $z->zone_name }}",
                bbox: [{{ $z->bbox_x1 }}, {{ $z->bbox_y1 }}, {{ $z->bbox_x2 }}, {{ $z->bbox_y2 }}],
                employee_id: {{ $z->employee ? $z->employee->id : 'null' }}
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
            ctx.fillText('Gagal memuat snapshot CCTV. Pastikan Python Engine aktif di port 5000.', 80, 240);
            loadingOverlay.classList.add('opacity-0', 'pointer-events-none');
        };
    }

    function refreshSnapshot() {
        loadSnapshot();
    }

    function getCanvasCoordinates(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        return {
            x: Math.round((e.clientX - rect.left) * scaleX),
            y: Math.round((e.clientY - rect.top) * scaleY)
        };
    }

    // Canvas Events
    canvas.addEventListener('mousedown', function(e) {
        const pos = getCanvasCoordinates(e);
        
        // Cek apakah klik pada zona yang sudah ada untuk memilihnya
        let clickedIndex = -1;
        for (let i = zones.length - 1; i >= 0; i--) {
            const [x1, y1, x2, y2] = zones[i].bbox;
            if (pos.x >= Math.min(x1, x2) && pos.x <= Math.max(x1, x2) &&
                pos.y >= Math.min(y1, y2) && pos.y <= Math.max(y1, y2)) {
                clickedIndex = i;
                break;
            }
        }

        if (clickedIndex !== -1 && !e.shiftKey) {
            selectedZoneIndex = clickedIndex;
            redrawCanvas();
            renderZoneList();
            scrollToZoneCard(clickedIndex);
            return;
        }

        isDrawing = true;
        startX = pos.x;
        startY = pos.y;
        currentX = pos.x;
        currentY = pos.y;
        selectedZoneIndex = -1;
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

        // Hanya simpan jika ukuran boks valid (minimal 25x25 px)
        if ((x2 - x1) >= 25 && (y2 - y1) >= 25) {
            const nextNum = zones.length + 1;
            const newZoneId = `chair_${nextNum}`;
            const newZone = {
                id: newZoneId,
                zone_name: `Meja ${nextNum}`,
                bbox: [x1, y1, x2, y2],
                employee_id: null
            };
            zones.push(newZone);
            selectedZoneIndex = zones.length - 1;
        }

        redrawCanvas();
        renderZoneList();
        scrollToZoneCard(selectedZoneIndex);
    });

    // Draw Preview Box saat drag mouse
    function drawPreviewRect(x1, y1, x2, y2) {
        const minX = Math.min(x1, x2);
        const minY = Math.min(y1, y2);
        const w = Math.abs(x2 - x1);
        const h = Math.abs(y2 - y1);

        ctx.strokeStyle = '#6366f1';
        ctx.lineWidth = 2;
        ctx.setLineDash([4, 4]);
        ctx.strokeRect(minX, minY, w, h);
        ctx.setLineDash([]);
        ctx.fillStyle = 'rgba(99, 102, 241, 0.15)';
        ctx.fillRect(minX, minY, w, h);
    }

    // Redraw Full Canvas
    function redrawCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        if (snapshotImg.complete && snapshotImg.naturalWidth > 0) {
            ctx.drawImage(snapshotImg, 0, 0, canvas.width, canvas.height);
        }

        zones.forEach((z, index) => {
            const [x1, y1, x2, y2] = z.bbox;
            const minX = Math.min(x1, x2);
            const minY = Math.min(y1, y2);
            const w = Math.abs(x2 - x1);
            const h = Math.abs(y2 - y1);

            const isSelected = (index === selectedZoneIndex);
            const emp = employeeData.find(e => e.id == z.employee_id);
            const hasEmployee = !!emp;

            // Box Styling
            if (isSelected) {
                ctx.strokeStyle = '#818cf8'; // Indigo bright
                ctx.lineWidth = 3;
                ctx.fillStyle = 'rgba(99, 102, 241, 0.20)';
            } else if (hasEmployee) {
                ctx.strokeStyle = '#10b981'; // Emerald
                ctx.lineWidth = 2;
                ctx.fillStyle = 'rgba(16, 185, 129, 0.12)';
            } else {
                ctx.strokeStyle = '#f59e0b'; // Amber (belum ada pegawai)
                ctx.lineWidth = 2;
                ctx.fillStyle = 'rgba(245, 158, 11, 0.10)';
            }

            ctx.fillRect(minX, minY, w, h);
            ctx.strokeRect(minX, minY, w, h);

            // Badge Label di atas Kotak
            const labelTitle = z.zone_name || `Meja ${index + 1}`;
            const labelEmp = emp ? `👤 ${emp.name}` : `[Kosong]`;
            const fullLabel = `${labelTitle} • ${labelEmp}`;

            ctx.font = 'bold 12px Inter, sans-serif';
            const textWidth = ctx.measureText(fullLabel).width;
            const badgeH = 22;
            const badgeW = textWidth + 14;
            const badgeY = Math.max(0, minY - badgeH);

            ctx.fillStyle = isSelected ? '#4f46e5' : (hasEmployee ? '#059669' : '#d97706');
            ctx.beginPath();
            ctx.roundRect(minX, badgeY, badgeW, badgeH, [4, 4, 0, 0]);
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.fillText(fullLabel, minX + 7, badgeY + 15);
        });
    }

    // Render Dynamic Zone List in Sidebar
    function renderZoneList() {
        const container = document.getElementById('zoneListContainer');
        document.getElementById('zoneCount').innerText = zones.length;
        container.innerHTML = '';

        if (zones.length === 0) {
            container.innerHTML = `
                <div class="h-full flex flex-col items-center justify-center text-center p-6 text-gray-500">
                    <svg class="w-12 h-12 text-gray-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
                    <p class="text-xs font-medium text-gray-400">Belum ada zona meja</p>
                    <p class="text-[11px] text-gray-600 mt-1">Tarik kursor mouse di gambar CCTV sebelah kiri untuk membuat meja baru.</p>
                </div>
            `;
            return;
        }

        zones.forEach((z, index) => {
            const isSelected = (index === selectedZoneIndex);
            const emp = employeeData.find(e => e.id == z.employee_id);

            // Build Employee Options Dropdown
            let empOptions = `<option value="">-- Belum Ada Pegawai (Kosong) --</option>`;
            employeeData.forEach(e => {
                const selected = (e.id == z.employee_id) ? 'selected' : '';
                empOptions += `<option value="${e.id}" ${selected}>${e.name} (${e.position || 'Staff'})</option>`;
            });

            const card = document.createElement('div');
            card.id = `zoneCard_${index}`;
            card.className = `p-3.5 rounded-xl border transition cursor-pointer space-y-2.5 ${
                isSelected 
                    ? 'bg-indigo-950/40 border-indigo-500 shadow-lg shadow-indigo-500/10' 
                    : (emp ? 'bg-gray-900/90 border-emerald-900/40 hover:border-gray-700' : 'bg-gray-900/90 border-amber-900/40 hover:border-gray-700')
            }`;
            
            card.onclick = (e) => {
                if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT' && e.target.tagName !== 'BUTTON' && !e.target.closest('button')) {
                    selectedZoneIndex = index;
                    redrawCanvas();
                    renderZoneList();
                }
            };

            card.innerHTML = `
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 flex-1">
                        <span class="w-2.5 h-2.5 rounded-full ${emp ? 'bg-emerald-500' : 'bg-amber-500'}"></span>
                        <input type="text" 
                               value="${z.zone_name}" 
                               onchange="updateZoneName(${index}, this.value)"
                               placeholder="Nama Meja..."
                               class="bg-gray-950 border border-gray-800 focus:border-indigo-500 rounded-lg px-2.5 py-1 text-xs text-white font-semibold flex-1 focus:outline-none">
                    </div>
                    <button type="button" onclick="deleteZone(${index})" class="p-1.5 text-gray-500 hover:text-rose-400 hover:bg-gray-800 rounded-lg transition" title="Hapus Meja">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-[11px] text-gray-400 shrink-0">Pegawai:</label>
                    <select onchange="updateZoneEmployee(${index}, this.value)" class="w-full bg-gray-950 border border-gray-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-indigo-500">
                        ${empOptions}
                    </select>
                </div>

                <div class="flex items-center justify-between text-[10px] text-gray-500 font-mono pt-1">
                    <span>ID: ${z.id}</span>
                    <span>BBox: [${z.bbox.join(', ')}]</span>
                </div>
            `;

            container.appendChild(card);
        });
    }

    function scrollToZoneCard(index) {
        setTimeout(() => {
            const card = document.getElementById(`zoneCard_${index}`);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 50);
    }

    function updateZoneName(index, newName) {
        zones[index].zone_name = newName;
        redrawCanvas();
    }

    function updateZoneEmployee(zoneIndex, employeeId) {
        const empId = employeeId ? parseInt(employeeId) : null;
        zones[zoneIndex].employee_id = empId;

        // Update di master employeeData juga
        employeeData.forEach(e => {
            if (e.id == empId) {
                e.zone_id = zones[zoneIndex].id;
            } else if (e.zone_id == zones[zoneIndex].id) {
                e.zone_id = '';
            }
        });

        redrawCanvas();
        renderZoneList();
    }

    function deleteZone(index) {
        const zid = zones[index].id;
        employeeData.forEach(e => {
            if (e.zone_id === zid) e.zone_id = '';
        });
        zones.splice(index, 1);
        selectedZoneIndex = -1;
        redrawCanvas();
        renderZoneList();
    }

    function clearAllZones() {
        if (confirm('Yakin ingin menghapus SEMUA zona meja dari gambar?')) {
            zones = [];
            employeeData.forEach(e => e.zone_id = '');
            selectedZoneIndex = -1;
            redrawCanvas();
            renderZoneList();
        }
    }

    // Bulk Save all zones and assignments to Backend & AI Engine
    function saveAllZones() {
        const btnSave = document.getElementById('btnSave');
        const originalText = btnSave.innerHTML;
        btnSave.disabled = true;
        btnSave.innerHTML = `
            <div class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></div>
            <span>Menyimpan...</span>
        `;

        const payload = {
            zones: zones.map(z => ({
                id: z.id,
                zone_name: z.zone_name,
                bbox: z.bbox,
                employee_id: z.employee_id
            }))
        };

        fetch('{{ route("admin.zones.save-all") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            btnSave.disabled = false;
            btnSave.innerHTML = originalText;
            if (data.status === 'success') {
                alert('✅ ' + data.message);
                window.location.reload();
            } else {
                alert('❌ Gagal menyimpan: ' + (data.message || 'Error'));
            }
        })
        .catch(err => {
            btnSave.disabled = false;
            btnSave.innerHTML = originalText;
            alert('❌ Terjadi kesalahan jaringan saat menyimpan.');
        });
    }

    // Modal Add / Edit Employee
    function openEmployeeModal() {
        document.getElementById('modalTitle').innerText = 'Pendaftaran Pegawai Baru';
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('employeeForm').action = '{{ route("admin.employees.store") }}';
        document.getElementById('empName').value = '';
        document.getElementById('empPosition').value = '';
        document.getElementById('empPhone').value = '';
        document.getElementById('empZone').value = '';
        document.getElementById('empMaxAway').value = 15;
        document.getElementById('photoRequired').style.display = 'inline';
        document.getElementById('empPhoto').required = true;
        document.getElementById('employeeModal').classList.remove('hidden');
    }

    function editEmployee(id, name, position, zoneId, phone = '', maxAway = 15) {
        document.getElementById('modalTitle').innerText = `Edit Data Pegawai: ${name}`;
        document.getElementById('formMethod').value = 'PUT';
        document.getElementById('employeeForm').action = `/admin/employees/${id}`;
        document.getElementById('empName').value = name;
        document.getElementById('empPosition').value = position;
        document.getElementById('empZone').value = zoneId || '';
        document.getElementById('empPhone').value = phone || '';
        document.getElementById('empMaxAway').value = maxAway || 15;
        document.getElementById('photoRequired').style.display = 'none';
        document.getElementById('empPhoto').required = false;
        document.getElementById('employeeModal').classList.remove('hidden');
    }

    function closeEmployeeModal() {
        document.getElementById('employeeModal').classList.add('hidden');
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadSnapshot();
    });
</script>
@endsection
