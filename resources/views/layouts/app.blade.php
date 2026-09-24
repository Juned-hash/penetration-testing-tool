<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') - {{ config('app.name', 'Security Assessment Platform') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar Navigation -->
        <div id="sidebar" class="d-flex flex-column flex-shrink-0 p-0 text-white">
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
            <div class="p-3 border-top border-secondary">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 36px; height: 36px;">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="small text-truncate me-auto">
                        <div class="fw-bold d-flex align-items-center gap-1">
                            {{ Auth::user()->name }}
                            <span class="badge bg-{{ Auth::user()->isAdmin() ? 'danger' : 'info' }}" style="font-size: 0.65rem;">{{ strtoupper(Auth::user()->role) }}</span>
                        </div>
                        <div class="text-muted" style="font-size: 0.75rem;">{{ Auth::user()->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-light" title="Logout">
                            <i class="bi bi-box-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div id="content" class="d-flex flex-column min-vh-100">
            <!-- Top Navbar -->
            <nav class="navbar top-navbar navbar-expand px-4 py-2">
                <div class="container-fluid p-0">
                    <span class="navbar-brand fw-bold text-dark fs-5">@yield('header', 'Dashboard')</span>
                    <div class="ms-auto d-flex align-items-center gap-3">
                        <a href="{{ route('scans.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> New Assessment
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Page Body -->
            <main class="flex-fill p-4">
                @include('components.flash-messages')
                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="bg-white py-3 px-4 border-top text-center text-muted small">
                Authorized Web Application Security Assessment Platform powered by OWASP ZAP framework
            </footer>
        </div>
    </div>
</body>
</html>
