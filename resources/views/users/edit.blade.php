@extends('layouts.app')

@section('title', 'Edit User')
@section('header', 'User Management')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="glass-card">
            <div class="glass-card-header">
                <h5 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i> Edit User: {{ $user->name }}</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('users.update', $user) }}">
                    @csrf
                    @method('PUT')

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label font-size-14 fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control glass-input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback font-size-12">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Email Address -->
                    <div class="mb-3">
                        <label for="email" class="form-label font-size-14 fw-semibold text-secondary">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control glass-input @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                        @error('email')
                            <div class="invalid-feedback font-size-12">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Password and Password Confirmation (Optional on Edit) -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label font-size-14 fw-semibold text-secondary">New Password (Optional)</label>
                            <input type="password" class="form-control glass-input @error('password') is-invalid @enderror" id="password" name="password" placeholder="Leave blank to keep existing">
                            @error('password')
                                <div class="invalid-feedback font-size-12">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label font-size-14 fw-semibold text-secondary">Confirm New Password</label>
                            <input type="password" class="form-control glass-input" id="password_confirmation" name="password_confirmation" placeholder="Re-enter new password">
                        </div>
                    </div>

                    <!-- Role Selection -->
                    <div class="mb-4">
                        <label for="role" class="form-label font-size-14 fw-semibold text-secondary">User Role <span class="text-danger">*</span></label>
                        <select class="form-select glass-select" id="role" name="role" required>
                            <option value="tester" {{ old('role', $user->role) === 'tester' ? 'selected' : '' }}>Tester (Assessment & Scanning Access Only)</option>
                            <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Administrator (Full System & User Management Access)</option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback font-size-12">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="{{ route('users.index') }}" class="app-btn-secondary">Cancel</a>
                        <button type="submit" class="app-btn-primary">
                            <i class="bi bi-save"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
