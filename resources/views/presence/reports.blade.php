<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Rekapitulasi Presensi HRD - AI CCTV Monitor</title>

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
    </style>
</head>
<body class="font-sans antialiased min-h-screen flex flex-col">

    <!-- Header Section -->
    <header class="glass-panel sticky top-0 z-30 border-b border-gray-800 px-6 py-3.5">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="font-bold text-lg text-white tracking-tight">
                        Laporan Rekapitulasi Presensi HRD
                    </h1>
                    <p class="text-xs text-gray-400">Rekap Jam Kerja & Kehadiran Pegawai Berbasis Spatial AI</p>
                </div>
            </div>

            <!-- Back to Dashboard -->
            <a href="{{ route('presence.dashboard') }}" class="flex items-center gap-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold px-4 py-2 rounded-xl border border-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Kembali ke Live Dashboard
            </a>

        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-6 py-6 flex-1 space-y-6 w-full">

        <!-- Date Filter & Summary Header -->
        <div class="glass-panel p-5 rounded-2xl border border-gray-800 flex flex-col md:flex-row items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-white mb-1">Filter Tanggal Laporan</h2>
                <p class="text-xs text-gray-400">Pilih tanggal untuk melihat rincian jam kerja pegawai per meja</p>
            </div>

            <form action="{{ route('presence.reports') }}" method="GET" class="flex items-center gap-3">
                <input type="date" 
                       name="date" 
                       value="{{ $selectedDate }}" 
                       class="bg-gray-900 border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 font-mono">
                
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-lg shadow-indigo-600/20 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                    Tampilkan Laporan
                </button>
            </form>
        </div>

        <!-- Table Card -->
        <div class="glass-panel rounded-2xl overflow-hidden border border-gray-800 shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-800 flex items-center justify-between">
                <h3 class="font-bold text-sm text-white">Ringkasan Kehadiran Harian (Tanggal: <span class="font-mono text-indigo-400">{{ $selectedDate }}</span>)</h3>
                <span class="text-xs text-gray-400 font-mono">Total Meja: {{ count($summaries) }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-300">
                    <thead class="bg-gray-900/80 text-gray-400 font-semibold uppercase tracking-wider border-b border-gray-800">
                        <tr>
                            <th class="px-6 py-3.5">ID Meja</th>
                            <th class="px-6 py-3.5">Nama Meja Kerja</th>
                            <th class="px-6 py-3.5">Pegawai Bertugas</th>
                            <th class="px-6 py-3.5 text-emerald-400">Total Bekerja (Menit)</th>
                            <th class="px-6 py-3.5 text-rose-400">Total Tidak di Tempat (Menit)</th>
                            <th class="px-6 py-3.5">Persentase Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60">
                        @forelse($summaries as $summary)
                            @php
                                $occTotalSec = intval($summary->total_working_seconds ?? 0);
                                $occMins = intdiv($occTotalSec, 60);
                                $occSecs = $occTotalSec % 60;
                                $occDisplay = $occMins . 'm ' . $occSecs . 's';

                                $awayTotalSec = intval($summary->total_away_seconds ?? 0);
                                $awayMins = intdiv($awayTotalSec, 60);
                                $awaySecs = $awayTotalSec % 60;
                                $awayDisplay = $awayMins . 'm ' . $awaySecs . 's';

                                $totalSec = $occTotalSec + $awayTotalSec;
                                $percentage = $totalSec > 0 ? round(($occTotalSec / $totalSec) * 100, 1) : 0;
                            @endphp
                            <tr class="hover:bg-gray-800/40 transition">
                                <td class="px-6 py-4 font-mono font-bold text-indigo-400 uppercase">{{ $summary->zone_id }}</td>
                                <td class="px-6 py-4 font-semibold text-white">{{ $summary->zone->zone_name ?? 'Meja Kerja' }}</td>
                                <td class="px-6 py-4 text-gray-300 font-medium">{{ $summary->zone->employee_name ?? '-' }}</td>
                                <td class="px-6 py-4 font-mono text-emerald-400 font-bold">{{ $occDisplay }}</td>
                                <td class="px-6 py-4 font-mono text-rose-400 font-bold">{{ $awayDisplay }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-800 h-2 rounded-full overflow-hidden">
                                            <div class="bg-indigo-500 h-full rounded-full" style="width: {{ $percentage }}%"></div>
                                        </div>
                                        <span class="font-mono text-xs font-bold text-white">{{ $percentage }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 text-sm">
                                    Belum ada data rekapitulasi presensi pada tanggal {{ $selectedDate }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>
