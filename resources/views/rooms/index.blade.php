<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pilih Ruangan - AI CCTV Workplace Monitoring</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (Play CDN) -->
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
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #090d16;
            color: #f3f4f6;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(168, 85, 247, 0.10) 0px, transparent 50%);
            background-attachment: fixed;
        }
        .glass-panel {
            background: rgba(17, 24, 39, 0.75);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between font-sans antialiased p-6 sm:p-10">

    <!-- Top Navigation Bar -->
    <header class="max-w-6xl mx-auto w-full flex items-center justify-between py-2">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-600/30">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <h1 class="text-base font-bold text-white tracking-tight">AI CCTV PRESENCE MONITOR</h1>
                <p class="text-[11px] font-mono text-indigo-400">Multi-Room Spatial Workplace Engine</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" onclick="openAddRoomModal()" class="flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-lg shadow-indigo-600/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Ruangan Baru
            </button>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-6xl mx-auto w-full py-8 space-y-8 my-auto">

        <!-- Hero Title -->
        <div class="text-center space-y-2">
            <span class="px-3 py-1 rounded-full text-xs font-mono font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 inline-block">
                PINTU MASUK SISTEM MONITORING
            </span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                Pilih Ruangan Kerja
            </h2>
            <p class="text-sm text-gray-400 max-w-xl mx-auto">
                Silakan pilih ruangan yang ingin Anda pantau untuk melihat siaran CCTV langsung, status meja kerja, dan rekognisi wajah pegawai.
            </p>
        </div>

        <!-- Alert Notifications -->
        @if(session('success'))
            <div class="max-w-2xl mx-auto p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-2xl text-xs flex items-center gap-2 shadow-lg">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ session('success') }}
            </div>
        @endif

        <!-- Rooms Grid Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($rooms as $room)
                @php
                    $zoneCount = count($room->zones);
                    $isActiveSession = (session('active_room_id') == $room->id);
                @endphp
                <div class="glass-panel rounded-3xl p-6 border transition-all duration-300 hover:translate-y-[-4px] hover:border-indigo-500/50 hover:shadow-2xl hover:shadow-indigo-500/10 flex flex-col justify-between relative group {{ $isActiveSession ? 'border-indigo-500/60 bg-indigo-500/5' : 'border-gray-800' }}">
                    
                    @if($isActiveSession)
                        <div class="absolute -top-3 right-6 bg-indigo-600 text-white text-[10px] font-bold px-3 py-0.5 rounded-full uppercase tracking-wider shadow-lg">
                            Sedang Aktif
                        </div>
                    @endif

                    <div class="space-y-4">
                        <!-- Card Header -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition duration-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                CCTV Online
                            </span>
                        </div>

                        <!-- Room Title & Description -->
                        <div>
                            <h3 class="text-lg font-bold text-white group-hover:text-indigo-300 transition">
                                {{ $room->name }}
                            </h3>
                            <p class="text-xs text-gray-400 mt-1 leading-relaxed line-clamp-2">
                                {{ $room->description ?? 'Monitoring zona meja kerja & kehadiran staf.' }}
                            </p>
                        </div>

                        <!-- Room Meta Badges -->
                        <div class="grid grid-cols-2 gap-2 pt-2 text-xs">
                            <div class="bg-gray-900/80 border border-gray-800 p-2.5 rounded-xl">
                                <span class="text-[10px] text-gray-500 block">Meja Terdaftar</span>
                                <span class="font-bold text-white font-mono">{{ $zoneCount }} Meja</span>
                            </div>
                            <div class="bg-gray-900/80 border border-gray-800 p-2.5 rounded-xl">
                                <span class="text-[10px] text-gray-500 block">Sumber CCTV</span>
                                <span class="font-bold text-indigo-400 font-mono text-[11px] truncate block">{{ $room->cctv_source }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="pt-6 mt-4 border-t border-gray-800/80 flex items-center justify-between gap-3">
                        <a href="{{ route('rooms.select', $room->id) }}" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold py-3 px-4 rounded-xl transition shadow-lg shadow-indigo-600/20 text-center flex items-center justify-center gap-2 group-hover:shadow-indigo-500/40">
                            <span>Masuk ke Ruangan</span>
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>

                        @if($rooms->count() > 1)
                            <form action="{{ route('rooms.destroy', $room->id) }}" method="POST" onsubmit="return confirm('Hapus ruangan {{ $room->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-3 text-gray-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition" title="Hapus Ruangan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            @empty
                <div class="col-span-3 text-center py-16 text-gray-500 text-sm">
                    Belum ada ruangan yang dibuat. Klik "Tambah Ruangan Baru" di atas.
                </div>
            @endforelse
        </div>

    </main>

    <!-- Footer -->
    <footer class="max-w-6xl mx-auto w-full text-center text-xs text-gray-500 py-4">
        AI CCTV Spatial Workplace Monitoring Engine &copy; {{ date('Y') }}
    </footer>

    <!-- Modal Tambah Ruangan Baru -->
    <div id="addRoomModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="glass-panel w-full max-w-lg rounded-3xl border border-gray-800 p-6 space-y-4 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 border-b border-gray-800">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Ruangan Kerja Baru
                </h3>
                <button type="button" onclick="closeAddRoomModal()" class="text-gray-400 hover:text-white p-1 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form action="{{ route('rooms.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="text-xs text-gray-400 mb-1.5 block">Nama Ruangan <span class="text-rose-400">*</span></label>
                    <input type="text" 
                           name="name" 
                           required 
                           placeholder="misal: Ruang Server, Ruang Rapat Lt. 2"
                           class="w-full bg-gray-900 border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-xs text-gray-400 mb-1.5 block">Sumber Video CCTV (File .mp4 / RTSP URL) <span class="text-rose-400">*</span></label>
                    <input type="text" 
                           name="cctv_source" 
                           required 
                           value="h.mp4"
                           placeholder="misal: h.mp4 atau rtsp://192.168.1.100:554/stream"
                           class="w-full bg-gray-900 border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500 font-mono">
                </div>

                <div>
                    <label class="text-xs text-gray-400 mb-1.5 block">Deskripsi Ruangan (Opsional)</label>
                    <textarea name="description" 
                              rows="2" 
                              placeholder="Keterangan fungsi ruangan kerja..."
                              class="w-full bg-gray-900 border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-indigo-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-800">
                    <button type="button" onclick="closeAddRoomModal()" class="px-4 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition shadow-lg shadow-indigo-600/20">
                        Simpan Ruangan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddRoomModal() {
            document.getElementById('addRoomModal').classList.remove('hidden');
        }
        function closeAddRoomModal() {
            document.getElementById('addRoomModal').classList.add('hidden');
        }
    </script>
</body>
</html>
