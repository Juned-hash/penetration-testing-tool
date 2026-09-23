<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Security Assessment Platform') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="container" style="max-width: 420px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-dark text-white rounded-circle mb-2" style="width: 56px; height: 56px;">
                <i class="bi bi-shield-lock-fill fs-3"></i>
            </div>
            <h4 class="fw-bold mb-1">Security Assessment</h4>
            <p class="text-muted small">Authorized Application Testing Platform</p>
        </div>

        @yield('content')
    </div>
</body>
</html>
