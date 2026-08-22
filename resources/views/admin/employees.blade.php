@extends('layouts.app')

@section('title', 'Kelola Pegawai & Registrasi Wajah - AI CCTV Monitor')

@section('content')
<div class="space-y-6">

    <!-- Top Header -->
    <div class="glass-panel p-5 rounded-2xl border border-gray-800 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-purple-500/20">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-white">Registrasi & Database Wajah Pegawai</h2>
                <p class="text-xs text-gray-400">Daftarkan foto wajah karyawan agar teridentifikasi otomatis oleh model AI (InsightFace)</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <form action="{{ route('admin.employees.reload') }}" method="POST">
                @csrf
                <button type="submit" class="flex items-center gap-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-semibold px-4 py-2.5 rounded-xl border border-gray-700 transition">
                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Reload AI Face Database
                </button>
            </form>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-xl text-xs flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 bg-rose-500/10 border border-rose-500/30 text-rose-400 rounded-xl text-xs space-y-1">
            @foreach($errors->all() as $error)
                <p>• {{ $error }}</p>
            @endforeach
        </div>
    @endif

    <!-- Main Workspace (Form Registration + Employee Table) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left Form Registration (4 Cols) -->
        <div class="lg:col-span-4 space-y-4">
            <div class="glass-panel p-5 rounded-2xl border border-gray-800">
                <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    Form Pendaftaran Pegawai
                </h3>

                <form action="{{ route('admin.employees.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-xs text-gray-400 mb-1.5 block">Nama Lengkap Pegawai <span class="text-rose-400">*</span></label>
                        <input type="text" 
                               name="name" 
                               required 
                               placeholder="misal: Bili, Gea, Andi..."
                               class="w-full bg-gray-900 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="text-xs text-gray-400 mb-1.5 block">Posisi / Jabatan</label>
                        <input type="text" 
                               name="position" 
                               placeholder="misal: Frontend Developer, HR..."
                               class="w-full bg-gray-900 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="text-xs text-gray-400 mb-1.5 block">Assign ke Meja Kerja</label>
                        <select name="assigned_zone_id" class="w-full bg-gray-900 border border-gray-700 text-white text-xs rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500">
                            <option value="">-- Fleksibel / Belum Ditugaskan --</option>
                            @foreach($zones as $z)
                                <option value="{{ $z->zone_id }}">{{ $z->zone_name }} ({{ $z->zone_id }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Photo Upload with Preview -->
                    <div>
                        <label class="text-xs text-gray-400 mb-1.5 block">Upload Foto Wajah Jelas <span class="text-rose-400">*</span></label>
                        <div class="border-2 border-dashed border-gray-700 rounded-xl p-4 text-center hover:border-indigo-500 transition cursor-pointer relative bg-gray-900/50" onclick="document.getElementById('photoInput').click()">
                            <input type="file" 
                                   id="photoInput" 
                                   name="photo" 
                                   required 
                                   accept="image/*" 
                                   onchange="previewPhoto(event)"
                                   class="hidden">
                            
                            <div id="uploadPlaceholder">
                                <svg class="w-8 h-8 text-gray-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <p class="text-xs text-gray-400 font-medium">Klik untuk memilih foto wajah</p>
                                <p class="text-[10px] text-gray-600 mt-0.5">Format JPG / PNG / JPEG (Maks. 5MB)</p>
                            </div>

                            <div id="photoPreviewContainer" class="hidden">
                                <img id="photoPreview" class="w-24 h-24 object-cover rounded-xl mx-auto border-2 border-indigo-500 mb-2 shadow-lg">
                                <p class="text-[11px] text-indigo-400 font-semibold" id="previewFilename"></p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-semibold py-3 rounded-xl transition shadow-lg shadow-indigo-600/20 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Daftarkan Pegawai & Wajah
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Employee List (8 Cols) -->
        <div class="lg:col-span-8 space-y-4">
            <div class="glass-panel rounded-2xl border border-gray-800 overflow-hidden shadow-2xl">
                <div class="px-6 py-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="font-bold text-sm text-white">Daftar Pegawai Terdaftar (<span class="text-indigo-400">{{ count($employees) }}</span>)</h3>
                    <div class="text-xs text-gray-500">Auto Face Recognition Enabled</div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-300">
                        <thead class="bg-gray-900/80 text-gray-400 font-semibold uppercase tracking-wider border-b border-gray-800">
                            <tr>
                                <th class="px-5 py-3.5">Foto</th>
                                <th class="px-5 py-3.5">Nama Pegawai</th>
                                <th class="px-5 py-3.5">Posisi</th>
                                <th class="px-5 py-3.5">Meja Kerja Ditugaskan</th>
                                <th class="px-5 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/60">
                            @forelse($employees as $emp)
                                <tr class="hover:bg-gray-800/40 transition">
                                    <td class="px-5 py-3">
                                        @if($emp->photo_filename)
                                            <img src="{{ asset('uploads/employees/' . $emp->photo_filename) }}" 
                                                 alt="{{ $emp->name }}" 
                                                 class="w-10 h-10 rounded-xl object-cover border border-gray-700 shadow"
                                                 onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($emp->name) }}&background=6366f1&color=fff'">
                                        @else
                                            <div class="w-10 h-10 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center font-bold text-sm border border-indigo-500/30">
                                                {{ strtoupper(substr($emp->name, 0, 2)) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 font-semibold text-white">
                                        {{ $emp->name }}
                                        <span class="block text-[10px] font-mono text-gray-500">File: {{ $emp->photo_filename }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-400">
                                        {{ $emp->position ?? '-' }}
                                    </td>
                                    <td class="px-5 py-3">
                                        @if($emp->zone)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                                                {{ $emp->zone->zone_name }} ({{ $emp->assigned_zone_id }})
                                            </span>
                                        @else
                                            <span class="text-gray-600 text-xs">Fleksibel</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <form action="{{ route('admin.employees.destroy', $emp->id) }}" method="POST" onsubmit="return confirm('Hapus pegawai {{ $emp->name }} beserta database wajahnya?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 text-gray-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition" title="Hapus Pegawai">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 text-xs">
                                        Belum ada pegawai terdaftar. Gunakan formulir di sebelah kiri untuk mendaftarkan wajah pegawai baru.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
    function previewPhoto(event) {
        const input = event.target;
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photoPreview').src = e.target.result;
                document.getElementById('previewFilename').innerText = input.files[0].name;
                document.getElementById('uploadPlaceholder').classList.add('hidden');
                document.getElementById('photoPreviewContainer').classList.remove('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endsection
