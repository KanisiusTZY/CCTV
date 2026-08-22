@extends('layouts.app')

@section('title', 'Live Dashboard Presensi - AI CCTV Monitor')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">

    <!-- Top Status Bar & Stats Counter -->
    <div class="glass-panel p-4 rounded-2xl border border-gray-800 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <span>{{ session('active_room_name', 'Ruang Kerja IT & Developer') }}</span>
                <span class="text-[10px] font-mono font-semibold px-2 py-0.5 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-full">CCTV ONLINE</span>
            </h2>
            <p class="text-xs text-gray-400">Monitoring spasial kehadiran meja kerja & rekognisi wajah pegawai</p>
        </div>

        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 bg-gray-900/90 border border-gray-800 px-3.5 py-1.5 rounded-xl">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs text-gray-300 font-medium"><strong id="totalBekerja" class="text-emerald-400 font-bold">{{ $streamStatus['total_bekerja'] ?? 0 }}</strong> Bekerja</span>
                <span class="text-gray-600">|</span>
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                <span class="text-xs text-gray-300 font-medium"><strong id="totalAway" class="text-rose-400 font-bold">{{ $streamStatus['total_away'] ?? 0 }}</strong> Tidak di Tempat</span>
            </div>
            <div class="flex items-center gap-1.5 bg-gray-900/90 border border-gray-800 px-3 py-1.5 rounded-xl text-xs text-gray-400 font-mono">
                <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                FPS: <span id="fpsVal" class="text-white font-semibold">{{ $streamStatus['fps'] ?? '0.0' }}</span>
            </div>
        </div>
    </div>

    <!-- Alert / Banner Flash Message -->
    @if(session('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs px-4 py-3 rounded-xl flex items-center justify-between">
            <span>{{ session('success') }}</span>
            <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
        </div>
    @endif

    <!-- Main Container Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT COLUMN: Live CCTV Stream & Workstation Grid (8 Cols) -->
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
                        <span class="text-xs font-semibold text-white tracking-wide uppercase">CCTV Live Feed</span>
                        <span class="text-xs text-gray-400 font-mono ml-2">Source: <span id="sourceLabel">{{ $streamStatus['source'] ?? 'h.mp4' }}</span></span>
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
                        <p class="text-xs text-gray-400">Pastikan server aktif di port 5000</p>
                    </div>
                    
                    <!-- Live Watermark Overlay -->
                    <div class="absolute top-4 left-4 pointer-events-none bg-black/60 backdrop-blur-md px-3 py-1.5 rounded-lg border border-white/10 text-white text-xs font-mono">
                        REC • <span id="liveClock">00:00:00</span>
                    </div>
                </div>
            </div>

            <!-- Workstation Status Grid Cards -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        Status Meja Kerja Pegawai
                    </h3>
                    <span class="text-xs text-gray-400">Realtime Update (Auto-refresh)</span>
                </div>

                <!-- Cards Grid -->
                <div id="workstationGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    @forelse($streamStatus['zones'] ?? [] as $zoneId => $zone)
                        @php
                            $isWorking = ($zone['status'] ?? '') === 'BEKERJA';
                            $hasEmployee = !empty($zone['employee_name']);
                            $cardTitle = $zone['display_title'] ?? ($zone['zone_name'] ?? 'Meja ' . str_replace('chair_', '', $zoneId));
                            $cardSubtitle = $zone['display_subtitle'] ?? 'Meja Kerja';
                            $photoUrl = !empty($zone['employee_photo']) ? asset('uploads/employees/' . $zone['employee_photo']) : null;
                        @endphp
                        <div id="card-{{ $zoneId }}" class="glass-panel p-4 rounded-xl border transition-all duration-300 hover:translate-y-[-2px] {{ $isWorking ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-rose-500/30 bg-rose-500/5' }}">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-800 border border-gray-700 flex items-center justify-center shrink-0">
                                        @if($photoUrl)
                                            <img src="{{ $photoUrl }}" alt="{{ $cardTitle }}" class="w-full h-full object-cover">
                                        @else
                                            <span class="text-xs font-bold text-gray-400">{{ strtoupper(substr($cardTitle, 0, 1)) }}</span>
                                        @endif
                                    </div>
                                    <div class="overflow-hidden">
                                        <h4 class="text-xs font-bold text-white truncate">{{ $cardTitle }}</h4>
                                        <p class="text-[10px] text-gray-400 truncate">{{ $cardSubtitle }}</p>
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
                               value="{{ $streamStatus['source'] ?? 'h.mp4' }}" 
                               placeholder="h.mp4 atau rtsp://..."
                               class="w-full bg-gray-900 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500 font-mono">
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold py-2.5 rounded-xl transition shadow-lg shadow-indigo-600/20">
                        Simpan & Reload Video
                    </button>
                </form>
            </div>

            <!-- Activity Logs Card -->
            <div class="glass-panel p-5 rounded-2xl border border-gray-800">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Log Event Presensi
                    </h3>
                    <span class="text-[10px] text-gray-500 font-mono">Live Feed</span>
                </div>

                <div id="eventLogsList" class="space-y-2.5 max-h-[380px] overflow-y-auto pr-1 text-xs">
                    <div class="p-3 bg-gray-900/60 rounded-xl border border-gray-800/80 text-gray-400">
                        Memuat riwayat aktivitas...
                    </div>
                </div>
            </div>

        </aside>

    </div>
</div>
@endsection

@section('scripts')
<script>
    function updateClock() {
        const now = new Date();
        const str = now.toTimeString().split(' ')[0];
        const clockEl = document.getElementById('liveClock');
        if (clockEl) clockEl.innerText = str;
    }
    setInterval(updateClock, 1000);
    updateClock();

    function toggleFullscreen() {
        const elem = document.getElementById('videoContainer');
        if (!document.fullscreenElement) {
            elem.requestFullscreen().catch(err => alert(`Error: ${err.message}`));
        } else {
            document.exitFullscreen();
        }
    }

    function formatTime(seconds) {
        const total = Math.max(0, Math.floor(seconds || 0));
        const m = Math.floor(total / 60);
        const s = total % 60;
        return `${m}m ${s}s`;
    }

    async function fetchLiveStatus() {
        try {
            const res = await fetch("{{ route('presence.live-status') }}");
            if (!res.ok) return;
            const data = await res.json();

            // Update Top Counters
            if (data.total_bekerja !== undefined) {
                document.getElementById('totalBekerja').innerText = data.total_bekerja;
            }
            if (data.total_away !== undefined) {
                document.getElementById('totalAway').innerText = data.total_away;
            }
            if (data.fps !== undefined) {
                document.getElementById('fpsVal').innerText = parseFloat(data.fps).toFixed(1);
            }
            if (data.source) {
                const srcEl = document.getElementById('sourceLabel');
                if (srcEl) srcEl.innerText = data.source;
            }

            // Update Workstation Cards
            if (data.zones) {
                const grid = document.getElementById('workstationGrid');
                let html = '';
                for (const [zoneId, zone] of Object.entries(data.zones)) {
                    const isWorking = zone.status === 'BEKERJA';
                    const title = zone.display_title || zone.zone_name || zoneId;
                    const subtitle = zone.display_subtitle || 'Meja Kerja';
                    const photo = zone.employee_photo ? `/uploads/employees/${zone.employee_photo}` : null;
                    const initial = title.charAt(0).toUpperCase();

                    const occSec = parseInt(zone.occupied_duration || 0);
                    const awaySec = parseInt(zone.away_duration_seconds !== undefined ? zone.away_duration_seconds : (zone.empty_duration || 0));

                    const cardBorder = isWorking ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-rose-500/30 bg-rose-500/5';
                    const badgeClass = isWorking ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30';
                    const statusText = isWorking ? 'BEKERJA' : 'TIDAK DI TEMPAT';

                    html += `
                        <div id="card-${zoneId}" class="glass-panel p-4 rounded-xl border transition-all duration-300 hover:translate-y-[-2px] ${cardBorder}">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-800 border border-gray-700 flex items-center justify-center shrink-0">
                                        ${photo ? `<img src="${photo}" alt="${title}" class="w-full h-full object-cover">` : `<span class="text-xs font-bold text-gray-400">${initial}</span>`}
                                    </div>
                                    <div class="overflow-hidden">
                                        <h4 class="text-xs font-bold text-white truncate">${title}</h4>
                                        <p class="text-[10px] text-gray-400 truncate">${subtitle}</p>
                                    </div>
                                </div>
                                <span id="badge-${zoneId}" class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider shrink-0 ${badgeClass}">
                                    ${statusText}
                                </span>
                            </div>

                            <div class="space-y-1.5 text-xs text-gray-400 pt-2.5 border-t border-gray-800">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 text-emerald-400 font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        Bekerja:
                                    </span>
                                    <span id="time-work-${zoneId}" class="font-mono text-emerald-400 font-bold">
                                        ${formatTime(occSec)}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 text-rose-400 font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        Tidak Di Tempat:
                                    </span>
                                    <span id="time-away-${zoneId}" class="font-mono text-rose-400 font-bold">
                                        ${formatTime(awaySec)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `;
                }
                if (html) grid.innerHTML = html;
            }

            // Update Event Logs
            if (data.recent_logs && data.recent_logs.length > 0) {
                const logsList = document.getElementById('eventLogsList');
                let logsHtml = '';
                data.recent_logs.forEach(log => {
                    const isBekerja = log.status === 'BEKERJA';
                    const iconColor = isBekerja ? 'text-emerald-400' : 'text-rose-400';
                    const badgeBg = isBekerja ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400';
                    logsHtml += `
                        <div class="p-2.5 bg-gray-900/60 rounded-xl border border-gray-800/80 flex items-center justify-between">
                            <div class="flex items-center gap-2 overflow-hidden">
                                <span class="w-1.5 h-1.5 rounded-full ${isBekerja ? 'bg-emerald-400' : 'bg-rose-400'} shrink-0"></span>
                                <div class="truncate">
                                    <span class="font-bold text-white">${log.zone_id}</span>
                                    <span class="text-gray-400 text-[11px] block">${log.employee_name || 'Pegawai'} &bull; ${log.status}</span>
                                </div>
                            </div>
                            <span class="text-[10px] font-mono text-gray-500 shrink-0">${log.timestamp || ''}</span>
                        </div>
                    `;
                });
                logsList.innerHTML = logsHtml;
            }

        } catch (e) {
            console.error('Error fetching live status:', e);
        }
    }

    setInterval(fetchLiveStatus, 2000);
</script>
@endsection
