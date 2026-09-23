@extends('layouts.guest')

@section('content')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <h5 class="card-title fw-bold mb-3">Sign In</h5>

        @include('components.flash-messages')

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label small" for="remember">Remember Me</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                Log In
            </button>
        </form>
    </div>
    <!-- <div class="card-footer bg-light text-center py-3 border-0 rounded-bottom">
        <span class="small text-muted">Don't have an account?</span>
        <a href="{{ route('register') }}" class="small fw-semibold ms-1 text-decoration-none">Create Account</a>
    </div> -->
</div>
@endsection
