@extends('layouts.app')

@section('title', 'Pilih Ruangan - AI CCTV Workplace Monitor')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">

    <!-- Header Page Bar -->
    <div class="glass-panel p-4 sm:p-5 rounded-2xl border border-gray-800 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                <span>Daftar Ruangan Kerja</span>
            </h2>
            <p class="text-xs text-gray-400">Pilih ruangan yang ingin dipantau untuk membuka siaran CCTV langsung & status meja kerja.</p>
        </div>

        <button type="button" onclick="openAddRoomModal()" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-lg shadow-indigo-600/20">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Ruangan Baru
        </button>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-xl text-xs flex items-center justify-between">
            <span>{{ session('success') }}</span>
            <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
        </div>
    @endif

    <!-- Rooms Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($rooms as $room)
            @php
                $zoneCount = count($room->zones);
                $isActiveSession = (session('active_room_id', 1) == $room->id);
            @endphp
            <div class="glass-panel rounded-2xl p-5 border transition-all duration-300 hover:translate-y-[-2px] flex flex-col justify-between relative {{ $isActiveSession ? 'border-indigo-500/50 bg-indigo-500/5 shadow-lg shadow-indigo-500/10' : 'border-gray-800' }}">
                
                @if($isActiveSession)
                    <div class="absolute -top-2.5 right-4 bg-indigo-600 text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider shadow">
                        Sedang Aktif
                    </div>
                @endif

                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Online
                        </span>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-white">
                            {{ $room->name }}
                        </h3>
                        <p class="text-xs text-gray-400 mt-1 leading-relaxed line-clamp-2">
                            {{ $room->description ?? 'Monitoring zona meja kerja & kehadiran pegawai.' }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                        <div class="bg-gray-900/80 border border-gray-800 p-2 rounded-lg">
                            <span class="text-[10px] text-gray-500 block">Meja Terdaftar</span>
                            <span class="font-bold text-white font-mono">{{ $zoneCount }} Meja</span>
                        </div>
                        <div class="bg-gray-900/80 border border-gray-800 p-2 rounded-lg">
                            <span class="text-[10px] text-gray-500 block">Sumber CCTV</span>
                            <span class="font-bold text-indigo-400 font-mono text-[11px] truncate block">{{ $room->cctv_source }}</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="pt-5 mt-4 border-t border-gray-800 flex items-center justify-between gap-3">
                    <a href="{{ route('rooms.select', $room->id) }}" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold py-2.5 px-3 rounded-xl transition shadow-lg shadow-indigo-600/20 text-center flex items-center justify-center gap-1.5">
                        <span>Masuk ke Ruangan</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>

                    @if($rooms->count() > 1)
                        <form action="{{ route('rooms.destroy', $room->id) }}" method="POST" onsubmit="return confirm('Hapus ruangan {{ $room->name }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-2.5 text-gray-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition" title="Hapus Ruangan">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    @endif
                </div>

            </div>
        @empty
            <div class="col-span-3 text-center py-12 text-gray-500 text-sm">
                Belum ada ruangan yang dibuat. Klik "Tambah Ruangan Baru" di atas.
            </div>
        @endforelse
    </div>

</div>

<!-- Modal Tambah Ruangan Baru -->
<div id="addRoomModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass-panel w-full max-w-lg rounded-2xl border border-gray-800 p-6 space-y-4 shadow-2xl relative">
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
                       placeholder="misal: h.mp4 atau rtsp://..."
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
@endsection

@section('scripts')
<script>
    function openAddRoomModal() {
        document.getElementById('addRoomModal').classList.remove('hidden');
    }
    function closeAddRoomModal() {
        document.getElementById('addRoomModal').classList.add('hidden');
    }
</script>
@endsection
