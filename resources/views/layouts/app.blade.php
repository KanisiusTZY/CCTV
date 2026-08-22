<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AI CCTV Workplace Monitor')</title>

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
        .nav-link {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 0.875rem; border-radius: 0.75rem;
            font-size: 0.8125rem; font-weight: 500;
            color: #9ca3af; transition: all 0.2s;
        }
        .nav-link:hover { background: rgba(255,255,255,0.05); color: #ffffff; }
        .nav-link.active {
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.3);
        }
    </style>
    @yield('head')
</head>
<body class="font-sans antialiased min-h-screen flex flex-col justify-between">

    <!-- Top Navbar Header -->
    <header class="glass-panel sticky top-0 z-30 border-b border-gray-800 px-4 sm:px-6 py-3.5">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-4">
            
            <!-- App Brand & Title -->
            <a href="{{ route('rooms.index') }}" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20 shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="font-bold text-base sm:text-lg text-white tracking-tight leading-tight">
                        AI CCTV Workplace Monitor
                    </h1>
                </div>
            </a>

            <!-- Navigation Links Menu (Hanya muncul jika sudah masuk ruangan / bukan landing page) -->
            @if(!request()->routeIs('rooms.*') && request()->path() !== '/')
                <nav class="flex items-center gap-1.5 overflow-x-auto py-1">
                    <a href="{{ route('presence.dashboard') }}" class="nav-link {{ request()->routeIs('presence.dashboard') ? 'active' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        <span>Monitoring Live</span>
                    </a>
                    <a href="{{ route('admin.zones') }}" class="nav-link {{ (request()->routeIs('admin.zones') || request()->routeIs('admin.employees')) ? 'active' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <span>Kelola Meja & Pegawai</span>
                    </a>
                    <a href="{{ route('presence.reports') }}" class="nav-link {{ request()->routeIs('presence.reports') ? 'active' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Laporan HRD</span>
                    </a>
                </nav>
            @endif

        </div>
    </header>

    <!-- Main Content Layout Container -->
    <main class="flex-1 w-full">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-800/80 py-4 px-6 text-center text-xs text-gray-500">
        AI CCTV Workplace Presence Monitoring System &copy; {{ date('Y') }}
    </footer>

    @yield('scripts')
</body>
</html>
