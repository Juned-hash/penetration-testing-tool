@extends('layouts.guest')

@section('content')
<div class="glass-card">
    <div class="card-body p-4">
        <h5 class="card-title fw-bold mb-3 font-size-20 text-dark">Sign In</h5>

        @include('components.flash-messages')

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label font-size-14 fw-semibold text-secondary">Email Address</label>
                <input type="email" class="form-control glass-input @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
                @error('email')
                    <div class="invalid-feedback font-size-12">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label font-size-14 fw-semibold text-secondary">Password</label>
                <input type="password" class="form-control glass-input @error('password') is-invalid @enderror" id="password" name="password" required>
                @error('password')
                    <div class="invalid-feedback font-size-12">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label font-size-14 text-secondary" for="remember">Remember Me</label>
            </div>

            <button type="submit" class="app-btn-primary w-100 py-2 mt-2">
                <i class="bi bi-box-arrow-in-right"></i> Log In
            </button>
        </form>
    </div>
</div>
@endsection
