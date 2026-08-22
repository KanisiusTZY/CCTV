<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI CCTV Workplace Monitoring - Dashboard Presensi Pegawai</title>

    <!-- Google Fonts & TailwindCSS CDN -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            500: '#22c55e',
                            600: '#16a34a',
                            900: '#14532d',
                        },
                        darkbg: '#090d16',
                        darkcard: '#111827',
                        darkborder: '#1f2937'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #090d16; color: #f3f4f6; }
        .glass-panel {
            background: rgba(17, 24, 39, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .pulse-red { animation: pulseRed 2s infinite; }
        @keyframes pulseRed {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); }
        }
    </style>
</head>
<body class="font-sans antialiased min-h-screen flex flex-col">

    <!-- Header Section (Following Community Template Design) -->
    <header class="glass-panel sticky top-0 z-30 border-b border-gray-800 px-6 py-3.5">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            
            <!-- App Brand & Title -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="font-bold text-lg text-white tracking-tight flex items-center gap-2">
                        AI CCTV Workplace Monitor
                    </h1>
                </div>
            </div>

            <!-- Header Status & Action Controls -->
            <div class="flex items-center gap-4">
                
                <!-- Live Counter Stats -->
                <div class="hidden md:flex items-center gap-2 bg-gray-900/60 border border-gray-800 px-3.5 py-1.5 rounded-xl">
                    <div class="flex items-center gap-1.5 border-r border-gray-800 pr-3">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-xs text-gray-300 font-medium"><strong id="totalBekerja" class="text-emerald-400 font-bold">{{ $streamStatus['total_bekerja'] ?? 0 }}</strong> Bekerja</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                        <span class="text-xs text-gray-300 font-medium"><strong id="totalAway" class="text-rose-400 font-bold">{{ $streamStatus['total_away'] ?? 0 }}</strong> Tidak di Tempat</span>
                    </div>
                </div>

                <!-- FPS Indicator -->
                <div class="hidden sm:flex items-center gap-1.5 bg-gray-900/60 border border-gray-800 px-3 py-1.5 rounded-xl text-xs text-gray-400">
                    <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    FPS: <span id="fpsVal" class="text-white font-mono font-semibold">{{ $streamStatus['fps'] ?? '0.0' }}</span>
                </div>

                <!-- Navigation Link to HRD Report -->
                <a href="{{ route('presence.reports') }}" class="flex items-center gap-2 bg-indigo-600/90 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2 rounded-xl transition shadow-lg shadow-indigo-600/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Laporan HRD
                </a>

            </div>
        </div>
    </header>

    <!-- Alert / Banner Flash Message -->
    @if(session('success'))
        <div class="max-w-7xl mx-auto px-6 mt-4">
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm px-4 py-3 rounded-xl flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
            </div>
        </div>
    @endif

    <!-- Main Container Grid -->
    <main class="max-w-7xl mx-auto px-6 py-6 flex-1 grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT COLUMN: Live Hero CCTV Stream & Workstation Grid (8 Cols) -->
        <section class="lg:col-span-8 space-y-6">

            <!-- CCTV Stream Player Card -->
            <div class="glass-panel rounded-2xl overflow-hidden shadow-2xl relative border border-gray-800">
                
                <!-- Player Sub-Header Bar -->
                <div class="bg-gray-900/90 px-4 py-3 border-b border-gray-800/80 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        <span class="text-xs font-semibold text-white tracking-wide uppercase">CCTV Camera #01 - Office Room</span>
                        <span class="text-xs text-gray-400 font-mono ml-2">Source: <span id="sourceLabel">{{ $streamStatus['source'] ?? 'f.mp4' }}</span></span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button onclick="toggleFullscreen()" class="p-1.5 text-gray-400 hover:text-white rounded-lg hover:bg-gray-800 transition" title="Fullscreen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Video Screen Wrapper -->
                <div id="videoContainer" class="relative aspect-video bg-black overflow-hidden flex items-center justify-center">
                    <img id="streamFeed" 
                         src="http://localhost:5000/video_feed" 
                         alt="AI CCTV Stream Feed" 
                         class="w-full h-full object-contain"
                         onerror="this.style.display='none'; document.getElementById('offlineOverlay').classList.remove('hidden');">
                    
                    <div id="offlineOverlay" class="hidden absolute inset-0 bg-gray-950 flex flex-col items-center justify-center text-center p-6">
                        <div class="w-12 h-12 rounded-full bg-rose-500/10 border border-rose-500/20 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m-12.728 0a9 9 0 010-12.728m12.728 0L5.636 18.364"></path></svg>
                        </div>
                        <h4 class="text-sm font-bold text-white mb-1">Python AI Stream Server Disconnected</h4>
                        <p class="text-xs text-gray-400">Pastikan server aktif lewat `php artisan monitor:start`</p>
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
                    <span class="text-xs text-gray-400">Realtime Update (Auto-refresh)</span>
                </div>

                <!-- Cards Grid -->
                <div id="workstationGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    @forelse($streamStatus['zones'] ?? [] as $zoneId => $zone)
                        @php
                            $isWorking = ($zone['status'] ?? '') === 'BEKERJA';
                        @endphp
                        <div id="card-{{ $zoneId }}" class="glass-panel p-4 rounded-xl border transition-all duration-300 hover:translate-y-[-2px] {{ $isWorking ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-rose-500/30 bg-rose-500/5' }}">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold font-mono uppercase text-gray-300">{{ str_replace('_', ' ', $zoneId) }}</span>
                                <span id="badge-{{ $zoneId }}" class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider {{ $isWorking ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30' }}">
                                    {{ $isWorking ? 'BEKERJA' : 'TIDAK DI TEMPAT' }}
                                </span>
                            </div>
                            
                            <div class="text-sm font-semibold text-white mb-1">
                                {{ $zone['zone_name'] ?? 'Meja ' . str_replace('chair_', '', $zoneId) }}
                            </div>

                            <div class="space-y-1.5 text-xs text-gray-400 mt-3 pt-2.5 border-t border-gray-800">
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

        <!-- RIGHT COLUMN: Control Panel & Live Activity Log (4 Cols) -->
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
                               value="{{ $streamStatus['source'] ?? 'f.mp4' }}" 
                               placeholder="f.mp4 atau rtsp://192.168.1.100:554/stream"
                               class="w-full bg-gray-900 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold py-2.5 rounded-xl transition shadow-lg shadow-indigo-600/20">
                        Simpan & Reload Video Engine
                    </button>
                </form>
            </div>

            <!-- Recent Event Log Sidebar Panel -->
            <div class="glass-panel p-5 rounded-2xl border border-gray-800 flex flex-col h-[400px]">
                <h3 class="text-sm font-bold text-white mb-3 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        Riwayat Event Terkini
                    </span>
                    <span class="text-[10px] text-gray-400 font-mono">Live Logs</span>
                </h3>

                <div id="eventLogContainer" class="flex-1 overflow-y-auto space-y-2 pr-1 text-xs">
                    @forelse($recentLogs as $log)
                        <div class="p-2.5 rounded-xl bg-gray-900/60 border border-gray-800 flex items-start gap-2.5">
                            <span class="w-2 h-2 rounded-full mt-1 flex-shrink-0 {{ $log->event_type === 'ENTER' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                            <div class="flex-1">
                                <div class="flex items-center justify-between text-gray-300">
                                    <strong class="font-semibold">{{ $log->zone->zone_name ?? $log->zone_id }}</strong>
                                    <span class="text-[10px] text-gray-500 font-mono">{{ \Carbon\Carbon::parse($log->timestamp)->format('H:i:s') }}</span>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-0.5">
                                    Status: <span class="{{ $log->event_type === 'ENTER' ? 'text-emerald-400' : 'text-rose-400' }} font-bold">{{ $log->event_type === 'ENTER' ? 'BEKERJA' : 'TIDAK DI TEMPAT' }}</span>
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-gray-500 text-xs">
                            Belum ada riwayat event presensi hari ini.
                        </div>
                    @endforelse
                </div>
            </div>

        </aside>

    </main>

    <!-- JavaScript Real-time Auto-Update Logic -->
    <script>
        // Live Clock Updater
        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').innerText = now.toLocaleTimeString();
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
                    if (el) el.innerText = data.source;
                }

                // Update Grid Meja Kerja
                if (data.zones) {
                    const gridContainer = document.getElementById('workstationGrid');
                    
                    // Jika kontainer grid masih berisi placeholder "Memuat data...", bersihkan!
                    if (gridContainer && gridContainer.children.length === 1 && gridContainer.children[0].innerText.includes('Memuat')) {
                        gridContainer.innerHTML = '';
                    }

                    Object.keys(data.zones).forEach(zoneId => {
                        const zone = data.zones[zoneId];
                        const isWorking = zone.status === 'BEKERJA';
                        let card = document.getElementById(`card-${zoneId}`);

                        // Buat elemen kartu meja secara dinamis jika belum ada di HTML
                        if (!card && gridContainer) {
                            const cardDiv = document.createElement('div');
                            cardDiv.id = `card-${zoneId}`;
                            cardDiv.className = `glass-panel p-4 rounded-xl border transition-all duration-300 hover:translate-y-[-2px] ${
                                isWorking ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-rose-500/30 bg-rose-500/5'
                            }`;
                            cardDiv.innerHTML = `
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold font-mono uppercase text-gray-300">${zoneId.replace('_', ' ')}</span>
                                    <span id="badge-${zoneId}" class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${
                                        isWorking ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                                    }">
                                        ${isWorking ? 'BEKERJA' : 'TIDAK DI TEMPAT'}
                                    </span>
                                </div>
                                <div class="text-sm font-semibold text-white mb-1">
                                    ${zone.zone_name || ('Meja ' + zoneId.replace('chair_', ''))}
                                </div>
                                <div class="space-y-1.5 text-xs text-gray-400 mt-3 pt-2.5 border-t border-gray-800">
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
                        const workSpan = document.getElementById(`time-work-${zoneId}`);
                        const awaySpan = document.getElementById(`time-away-${zoneId}`);

                        if (card && badge) {
                            card.className = `glass-panel p-4 rounded-xl border transition-all duration-300 hover:translate-y-[-2px] ${
                                isWorking ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-rose-500/30 bg-rose-500/5'
                            }`;
                            badge.className = `text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${
                                isWorking ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                            }`;
                            badge.innerText = isWorking ? 'BEKERJA' : 'TIDAK DI TEMPAT';

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
</body>
</html>
