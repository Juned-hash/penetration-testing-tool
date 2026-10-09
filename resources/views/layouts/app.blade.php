<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') - {{ config('app.name', 'Security Assessment Platform') }}</title>

    <!-- Google Fonts: Roboto -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="min-vh-100">
    <div class="d-flex w-100 min-vh-100">
        <!-- Sidebar Navigation -->
        <div id="sidebar" class="d-flex flex-column flex-shrink-0 p-0 text-dark">
            <div class="brand-title d-flex align-items-center gap-2">
                <img src="{{ asset('images/logo.webp') }}" alt="Logo" class="img-fluid" style="max-height: 40px;">
            </div>
            <ul class="nav nav-pills flex-column mb-auto mt-3">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('scans.index') }}" class="nav-link {{ request()->routeIs('scans.*') ? 'active' : '' }}">
                        <i class="bi bi-search"></i>
                        <span>Assessments</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-pdf"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                </li>
                @if (Auth::user()?->isAdmin())
                <li>
                    <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="bi bi-people"></i>
                        <span>User Management</span>
                    </a>
                </li>
                @endif
            </ul>
            <div class="p-3 user-profile-box mt-auto">
                <div class="d-flex align-items-center gap-2">
                    <div class="text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--blue), #2B7DD4);">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="small text-truncate me-auto">
                        <div class="fw-bold d-flex align-items-center gap-1 text-dark font-size-14">
                            {{ Auth::user()->name }}
                            <span class="glass-badge {{ Auth::user()->isAdmin() ? 'bg-danger text-white' : 'bg-info text-white' }} font-size-12">{{ strtoupper(Auth::user()->role) }}</span>
                        </div>
                        <div class="text-secondary font-size-12">{{ Auth::user()->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="app-btn-secondary app-btn-sm" title="Logout">
                            <i class="bi bi-box-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div id="content" class="d-flex flex-column flex-grow-1 min-vh-100 w-100" style="min-width: 0;">
            <!-- Top Navbar -->
            <nav class="navbar top-navbar navbar-expand px-4 py-3 w-100">
                <div class="container-fluid p-0">
                    <span class="navbar-brand fw-bold text-dark font-size-20">@yield('header', 'Dashboard')</span>
                    <div class="ms-auto d-flex align-items-center gap-3">
                        <a href="{{ route('scans.create') }}" class="app-btn-primary">
                            <i class="bi bi-plus-circle"></i> New Assessment
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Page Body -->
            <main class="flex-fill p-4 w-100">
                @include('components.flash-messages')
                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="bg-white py-3 px-4 border-top text-center text-muted font-size-12 w-100">
                Authorized Web Application Security Assessment Platform powered by OWASP ZAP framework
            </footer>
        </div>
    </div>
</body>
</html>
