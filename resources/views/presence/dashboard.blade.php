@extends('layouts.app')

@section('title', 'Live Monitoring Kehadiran - AI CCTV Monitor')

@section('content')
<div class="space-y-6">
    <!-- Top Status Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 glass-panel p-4 rounded-2xl border border-gray-800">
        <div class="flex items-center gap-3">
            <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
            <div>
                <h2 class="text-base font-bold text-white">Live Monitoring Stream</h2>
                <p class="text-xs text-gray-400">Deteksi realtime zona meja kerja & rekognisi wajah pegawai</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 bg-gray-900/80 border border-gray-800 px-3.5 py-1.5 rounded-xl">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs text-gray-300 font-medium"><strong id="totalBekerja" class="text-emerald-400 font-bold">{{ $streamStatus['total_bekerja'] ?? 0 }}</strong> Bekerja</span>
                <span class="text-gray-600">|</span>
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                <span class="text-xs text-gray-300 font-medium"><strong id="totalAway" class="text-rose-400 font-bold">{{ $streamStatus['total_away'] ?? 0 }}</strong> Tidak di Tempat</span>
            </div>
            <div class="flex items-center gap-1.5 bg-gray-900/80 border border-gray-800 px-3 py-1.5 rounded-xl text-xs text-gray-400">
                <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                FPS: <span id="fpsVal" class="text-white font-mono font-semibold">{{ $streamStatus['fps'] ?? '0.0' }}</span>
            </div>
        </div>
    </div>

    <!-- Alert Success/Error -->
    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-xl text-xs flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-rose-500/10 border border-rose-500/30 text-rose-400 rounded-xl text-xs flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            {{ session('error') }}
        </div>
    @endif

    <!-- Main Grid Content -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT COLUMN: Live Stream Video & Workstation Status Cards (8 Cols) -->
        <section class="lg:col-span-8 space-y-6">

            <!-- Video Player Card Component -->
            <div id="videoContainer" class="glass-panel rounded-2xl overflow-hidden border border-gray-800 shadow-2xl relative group">
                <div class="bg-gray-900/90 px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-xs font-bold text-gray-200 uppercase tracking-wider">CCTV Live Stream</span>
                        <span id="sourceLabel" class="text-[10px] font-mono bg-indigo-500/10 text-indigo-400 px-2 py-0.5 rounded border border-indigo-500/20">Source: {{ $streamStatus['source'] ?? 'Default' }}</span>
                    </div>
                    <button onclick="toggleFullscreen()" class="text-gray-400 hover:text-white transition p-1 rounded-lg hover:bg-gray-800" title="Fullscreen View">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                    </button>
                </div>

                <div class="relative aspect-video bg-black flex items-center justify-center overflow-hidden">
                    <img id="mjpegFeed" 
                         src="http://localhost:5000/video_feed" 
                         alt="CCTV Video Feed"
                         class="w-full h-full object-contain"
                         onerror="this.style.display='none'; document.getElementById('offlineOverlay').classList.remove('hidden');">
                    
                    <div id="offlineOverlay" class="hidden absolute inset-0 bg-gray-950 flex flex-col items-center justify-center text-center p-6">
                        <div class="w-12 h-12 rounded-full bg-rose-500/10 border border-rose-500/20 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m-12.728 0a9 9 0 010-12.728m12.728 0L5.636 18.364"></path></svg>
                        </div>
                        <h4 class="text-sm font-bold text-white mb-1">Python AI Stream Server Disconnected</h4>
                        <p class="text-xs text-gray-400">Pastikan Python engine aktif di port 5000</p>
                    </div>
                    
                    <!-- Live Watermark Overlay -->
                    <div class="absolute top-4 left-4 pointer-events-none bg-black/60 backdrop-blur-md px-3 py-1.5 rounded-lg border border-white/10 text-white text-xs font-mono">
                        REC • <span id="liveClock">00:00:00</span>
                    </div>
                </div>
            </div>

            <!-- Workstation Status Grid Cards (Component Layout) -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        Status Presensi Meja Kerja (Workstation Zones)
                    </h2>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.employees') }}" class="text-xs text-purple-400 hover:text-purple-300 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Atur Penugasan Pegawai
                        </a>
                        <span class="text-gray-600">|</span>
                        <a href="{{ route('admin.zones') }}" class="text-xs text-indigo-400 hover:text-indigo-300 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            Edit Zona
                        </a>
                    </div>
                </div>

                <!-- Cards Grid -->
                <div id="workstationGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    @forelse($streamStatus['zones'] ?? [] as $zoneId => $zone)
                        @php
                            $isWorking = ($zone['status'] ?? '') === 'BEKERJA';
                            $empName = $zone['employee_name'] ?? null;
                            $empPhoto = $zone['employee_photo'] ?? null;
                            $mainTitle = $empName ? $empName : ($zone['zone_name'] ?? 'Meja ' . str_replace('chair_', '', $zoneId));
                            $subTitle = $empName 
                                ? (($zone['zone_name'] ?? str_replace('_', ' ', $zoneId)) . ' • ' . ($zone['employee_position'] ?? 'Pegawai'))
                                : (str_replace('_', ' ', $zoneId) . ' • Belum Ditugaskan');
                        @endphp
                        <div id="card-{{ $zoneId }}" class="glass-panel p-4 rounded-xl border transition-all duration-300 hover:translate-y-[-2px] {{ $isWorking ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-rose-500/30 bg-rose-500/5' }}">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2.5">
                                    @if($empPhoto)
                                        <img id="photo-{{ $zoneId }}" 
                                             src="{{ asset('uploads/employees/' . $empPhoto) }}" 
                                             alt="{{ $mainTitle }}" 
                                             class="w-9 h-9 rounded-xl object-cover border border-gray-700 shadow"
                                             onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($mainTitle) }}&background=6366f1&color=fff'">
                                    @else
                                        <div id="photo-{{ $zoneId }}" class="w-9 h-9 rounded-xl bg-gray-800 text-gray-400 flex items-center justify-center font-bold text-xs border border-gray-700">
                                            {{ strtoupper(substr($mainTitle, 0, 2)) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div id="name-{{ $zoneId }}" class="text-sm font-bold text-white leading-tight">
                                            {{ $mainTitle }}
                                        </div>
                                        <div id="sub-{{ $zoneId }}" class="text-[11px] text-gray-400">
                                            {{ $subTitle }}
                                        </div>
                                    </div>
                                </div>
                                <span id="badge-{{ $zoneId }}" class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider shrink-0 {{ $isWorking ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30' }}">
                                    {{ $isWorking ? 'BEKERJA' : 'TIDAK DI TEMPAT' }}
                                </span>
                            </div>

                            <div class="space-y-1.5 text-xs text-gray-400 pt-2.5 border-t border-gray-800">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 text-emerald-400 font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        Bekerja:
                                    </span>
                                    @php $occSec = intval($zone['occupied_duration'] ?? 0); @endphp
                                    <span id="time-work-{{ $zoneId }}" class="font-mono text-emerald-400 font-bold">
                                        {{ intdiv($occSec, 60) }}m {{ $occSec % 60 }}s
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 text-rose-400 font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        Tidak Di Tempat:
                                    </span>
                                    @php $awaySec = intval($zone['away_duration_seconds'] ?? ($zone['empty_duration'] ?? 0)); @endphp
                                    <span id="time-away-{{ $zoneId }}" class="font-mono text-rose-400 font-bold">
                                        {{ intdiv($awaySec, 60) }}m {{ $awaySec % 60 }}s
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-3 text-center py-8 text-gray-500 text-sm">
                            Memuat data zona meja kerja...
                        </div>
                    @endforelse
                </div>
            </div>

        </section>

        <!-- RIGHT COLUMN: Control Panel & Quick Links (4 Cols) -->
        <aside class="lg:col-span-4 space-y-6">

            <!-- Video Source Switcher Panel -->
            <div class="glass-panel p-5 rounded-2xl border border-gray-800">
                <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                    Ganti Sumber Video CCTV
                </h3>

                <form action="{{ route('presence.change-source') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block">Pilih / Ketik Sumber Video:</label>
                        <input type="text" 
                               name="source" 
                               value="{{ $streamStatus['source'] ?? 'h.mp4' }}" 
                               placeholder="h.mp4 atau rtsp://192.168.1.100:554/stream"
                               class="w-full bg-gray-900 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold py-2.5 rounded-xl transition shadow-lg shadow-indigo-600/20">
                        Simpan & Reload Video Engine
                    </button>
                </form>
            </div>

            <!-- Quick Action Links -->
            <div class="glass-panel p-5 rounded-2xl border border-gray-800 space-y-3">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Akses Cepat Pengaturan
                </h3>
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <a href="{{ route('admin.zones') }}" class="p-3 bg-gray-900/80 hover:bg-gray-800 border border-gray-800 rounded-xl transition text-center group">
                        <div class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center mx-auto mb-1.5 group-hover:scale-110 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-300 block">Kelola Zona</span>
                    </a>
                    <a href="{{ route('admin.employees') }}" class="p-3 bg-gray-900/80 hover:bg-gray-800 border border-gray-800 rounded-xl transition text-center group">
                        <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center mx-auto mb-1.5 group-hover:scale-110 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-300 block">Daftar Pegawai</span>
                    </a>
                </div>
            </div>

        </aside>

    </div>
</div>
@endsection

@section('scripts')
<script>
    // Live Clock Updater
    function updateClock() {
        const now = new Date();
        const el = document.getElementById('liveClock');
        if (el) el.innerText = now.toLocaleTimeString('id-ID');
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Fullscreen Stream Toggle
    function toggleFullscreen() {
        const elem = document.getElementById('videoContainer');
        if (!document.fullscreenElement) {
            elem.requestFullscreen().catch(err => alert(`Error: ${err.message}`));
        } else {
            document.exitFullscreen();
        }
    }

    // Format detik ke Xm Ys
    function fmtDuration(totalSec) {
        const sec = Math.round(totalSec);
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return `${m}m ${s}s`;
    }

    // AJAX Auto-Refresh Live Status (Setiap 1 Detik)
    let isFetchingStatus = false;
    async function fetchLiveStatus() {
        if (isFetchingStatus) return;
        isFetchingStatus = true;
        try {
            const response = await fetch('{{ route("presence.live-status") }}');
            let data = null;
            if (response.ok) data = await response.json();

            if (!data) return;

            // Update Stats Counter
            if (data.total_bekerja !== undefined) {
                const el = document.getElementById('totalBekerja');
                if (el) el.innerText = data.total_bekerja;
            }
            if (data.total_away !== undefined) {
                const el = document.getElementById('totalAway');
                if (el) el.innerText = data.total_away;
            }
            if (data.fps !== undefined) {
                const el = document.getElementById('fpsVal');
                if (el) el.innerText = data.fps;
            }
            if (data.source) {
                const el = document.getElementById('sourceLabel');
                if (el) el.innerText = 'Source: ' + data.source;
            }

            // Update Grid Meja Kerja
            if (data.zones) {
                const gridContainer = document.getElementById('workstationGrid');
                
                if (gridContainer && gridContainer.children.length === 1 && gridContainer.children[0].innerText.includes('Memuat')) {
                    gridContainer.innerHTML = '';
                }

                Object.keys(data.zones).forEach(zoneId => {
                    const zone = data.zones[zoneId];
                    const isWorking = zone.status === 'BEKERJA';
                    const empName = zone.employee_name || null;
                    const empPhoto = zone.employee_photo || null;
                    const empPos = zone.employee_position || 'Pegawai';
                    const mainTitle = empName ? empName : (zone.zone_name || ('Meja ' + zoneId.replace('chair_', '')));
                    const subTitle = empName 
                        ? ((zone.zone_name || zoneId.replace('_', ' ')) + ' • ' + empPos)
                        : (zoneId.replace('_', ' ') + ' • Belum Ditugaskan');

                    let card = document.getElementById(`card-${zoneId}`);

                    if (!card && gridContainer) {
                        const cardDiv = document.createElement('div');
                        cardDiv.id = `card-${zoneId}`;
                        cardDiv.className = `glass-panel p-4 rounded-xl border transition-all duration-300 hover:translate-y-[-2px] ${
                            isWorking ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-rose-500/30 bg-rose-500/5'
                        }`;
                        cardDiv.innerHTML = `
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2.5">
                                    ${empPhoto 
                                        ? `<img id="photo-${zoneId}" src="/uploads/employees/${empPhoto}" alt="${mainTitle}" class="w-9 h-9 rounded-xl object-cover border border-gray-700 shadow">`
                                        : `<div id="photo-${zoneId}" class="w-9 h-9 rounded-xl bg-gray-800 text-gray-400 flex items-center justify-center font-bold text-xs border border-gray-700">${mainTitle.substring(0,2).toUpperCase()}</div>`
                                    }
                                    <div>
                                        <div id="name-${zoneId}" class="text-sm font-bold text-white leading-tight">
                                            ${mainTitle}
                                        </div>
                                        <div id="sub-${zoneId}" class="text-[11px] text-gray-400">
                                            ${subTitle}
                                        </div>
                                    </div>
                                </div>
                                <span id="badge-${zoneId}" class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider shrink-0 ${
                                    isWorking ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                                }">
                                    ${isWorking ? 'BEKERJA' : 'TIDAK DI TEMPAT'}
                                </span>
                            </div>
                            <div class="space-y-1.5 text-xs text-gray-400 pt-2.5 border-t border-gray-800">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 text-emerald-400 font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        Bekerja:
                                    </span>
                                    <span id="time-work-${zoneId}" class="font-mono text-emerald-400 font-bold">
                                        ${fmtDuration(zone.occupied_duration || 0)}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 text-rose-400 font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        Tidak Di Tempat:
                                    </span>
                                    <span id="time-away-${zoneId}" class="font-mono text-rose-400 font-bold">
                                        ${fmtDuration(zone.away_duration_seconds !== undefined ? zone.away_duration_seconds : (zone.empty_duration || 0))}
                                    </span>
                                </div>
                            </div>
                        `;
                        gridContainer.appendChild(cardDiv);
                        return;
                    }

                    const badge = document.getElementById(`badge-${zoneId}`);
                    const nameEl = document.getElementById(`name-${zoneId}`);
                    const subEl = document.getElementById(`sub-${zoneId}`);
                    const workSpan = document.getElementById(`time-work-${zoneId}`);
                    const awaySpan = document.getElementById(`time-away-${zoneId}`);

                    if (card && badge) {
                        card.className = `glass-panel p-4 rounded-xl border transition-all duration-300 hover:translate-y-[-2px] ${
                            isWorking ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-rose-500/30 bg-rose-500/5'
                        }`;
                        badge.className = `text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider shrink-0 ${
                            isWorking ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                        }`;
                        badge.innerText = isWorking ? 'BEKERJA' : 'TIDAK DI TEMPAT';

                        if (nameEl) nameEl.innerText = mainTitle;
                        if (subEl) subEl.innerText = subTitle;

                        if (workSpan) {
                            workSpan.innerText = fmtDuration(zone.occupied_duration || 0);
                        }
                        if (awaySpan) {
                            const awaySec = zone.away_duration_seconds !== undefined ? zone.away_duration_seconds : (zone.empty_duration || 0);
                            awaySpan.innerText = fmtDuration(awaySec);
                        }
                    }
                });
            }

        } catch (err) {
            console.error("Gagal memperbarui status live:", err);
        } finally {
            isFetchingStatus = false;
        }
    }

    setInterval(fetchLiveStatus, 1000);
</script>
@endsection
