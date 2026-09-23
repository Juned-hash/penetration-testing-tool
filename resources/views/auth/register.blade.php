@extends('layouts.guest')

@section('content')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <h5 class="card-title fw-bold mb-3">Create Account</h5>

        @include('components.flash-messages')

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required autofocus>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
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

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                Register
            </button>
        </form>
    </div>
    <div class="card-footer bg-light text-center py-3 border-0 rounded-bottom">
        <span class="small text-muted">Already registered?</span>
        <a href="{{ route('login') }}" class="small fw-semibold ms-1 text-decoration-none">Sign In</a>
    </div>
</div>
@endsection
