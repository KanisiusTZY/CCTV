<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AI CCTV Workplace Monitor')</title>

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
                        brand: { 50: '#f0fdf4', 500: '#22c55e', 600: '#16a34a', 900: '#14532d' },
                        darkbg: '#090d16',
                        darkcard: '#111827',
                        darkborder: '#1f2937'
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
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
        .sidebar-link {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.625rem 1rem; border-radius: 0.75rem;
            font-size: 0.875rem; font-weight: 500;
            color: #9ca3af; transition: all 0.2s;
        }
        .sidebar-link:hover { background: rgba(255,255,255,0.05); color: #e5e7eb; }
        .sidebar-link.active {
            background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.1));
            color: #a5b4fc; border: 1px solid rgba(99,102,241,0.2);
        }
        .pulse-red { animation: pulseRed 2s infinite; }
        @keyframes pulseRed {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); }
        }
    </style>
    @yield('head')
</head>
<body class="font-sans antialiased min-h-screen flex">

    <!-- Sidebar -->
    <aside id="sidebar" class="hidden lg:flex flex-col w-64 min-h-screen glass-panel border-r border-gray-800 py-6 px-4 fixed z-40">
        <!-- Brand -->
        <div class="flex items-center gap-3 px-2 mb-8">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <h1 class="font-bold text-base text-white tracking-tight">AI CCTV Monitor</h1>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest">Workplace Presence</p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 space-y-1.5">
            <p class="text-[10px] font-semibold text-gray-600 uppercase tracking-widest px-3 mb-2">Monitoring</p>
            <a href="{{ route('presence.dashboard') }}" class="sidebar-link {{ request()->routeIs('presence.dashboard') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                Live Monitoring
            </a>
            <a href="{{ route('presence.reports') }}" class="sidebar-link {{ request()->routeIs('presence.reports') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Laporan HRD
            </a>

            <p class="text-[10px] font-semibold text-gray-600 uppercase tracking-widest px-3 mb-2 mt-6">Administrasi</p>
            <a href="{{ route('admin.zones') }}" class="sidebar-link {{ request()->routeIs('admin.zones') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>
                Kelola Zona Meja
            </a>
            <a href="{{ route('admin.employees') }}" class="sidebar-link {{ request()->routeIs('admin.employees') ? 'active' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Kelola Pegawai
            </a>
        </nav>

        <!-- Footer -->
        <div class="border-t border-gray-800 pt-4 mt-4">
            <div class="flex items-center gap-2 px-3">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs text-gray-500">Engine Online</span>
                <span class="text-xs text-gray-600 ml-auto font-mono" id="sidebarClock"></span>
            </div>
        </div>
    </aside>

    <!-- Mobile Header -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-50 glass-panel border-b border-gray-800 px-4 py-3 flex items-center justify-between">
        <button onclick="document.getElementById('sidebar').classList.toggle('hidden'); document.getElementById('sidebar').classList.toggle('flex');" class="text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>
        <h1 class="font-bold text-sm text-white">AI CCTV Monitor</h1>
        <div></div>
    </div>

    <!-- Main Content -->
    <main class="flex-1 lg:ml-64 min-h-screen">
        <div class="p-4 lg:p-6 lg:pt-6 mt-14 lg:mt-0">
            @yield('content')
        </div>
    </main>

    <script>
        function updateSidebarClock() {
            const el = document.getElementById('sidebarClock');
            if (el) el.innerText = new Date().toLocaleTimeString('id-ID');
        }
        setInterval(updateSidebarClock, 1000);
        updateSidebarClock();
    </script>
    @yield('scripts')
</body>
</html>
