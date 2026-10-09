<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Security Assessment Platform') }}</title>

    <!-- Google Fonts: Roboto -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 py-4">
    <div class="container" style="max-width: 440px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center text-white rounded-circle mb-3 shadow" style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--blue), #2B7DD4);">
                <i class="bi bi-shield-lock-fill font-size-26"></i>
            </div>
            <h4 class="fw-bold mb-1 font-size-26 text-dark">Security Assessment</h4>
            <p class="text-secondary font-size-14">Authorized Application Testing Platform</p>
        </div>

        @yield('content')
    </div>
</body>
</html>
