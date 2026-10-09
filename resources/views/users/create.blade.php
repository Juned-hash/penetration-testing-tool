@extends('layouts.app')

@section('title', 'Add New User')
@section('header', 'User Management')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="glass-card">
            <div class="glass-card-header">
                <h5 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-person-plus me-2 text-primary"></i> Add New User</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('users.store') }}">
                    @csrf

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label font-size-14 fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control glass-input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Jane Doe" required autofocus>
                        @error('name')
                            <div class="invalid-feedback font-size-12">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Email Address -->
                    <div class="mb-3">
                        <label for="email" class="form-label font-size-14 fw-semibold text-secondary">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control glass-input @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="user@organization.com" required>
                        @error('email')
                            <div class="invalid-feedback font-size-12">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Password and Password Confirmation -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label font-size-14 fw-semibold text-secondary">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control glass-input @error('password') is-invalid @enderror" id="password" name="password" placeholder="Minimum 8 characters" required>
                            @error('password')
                                <div class="invalid-feedback font-size-12">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label font-size-14 fw-semibold text-secondary">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control glass-input" id="password_confirmation" name="password_confirmation" placeholder="Re-enter password" required>
                        </div>
                    </div>

                    <!-- Role Selection -->
                    <div class="mb-4">
                        <label for="role" class="form-label font-size-14 fw-semibold text-secondary">User Role <span class="text-danger">*</span></label>
                        <select class="form-select glass-select" id="role" name="role" required>
                            <option value="tester" {{ old('role', 'tester') === 'tester' ? 'selected' : '' }}>Tester (Assessment & Scanning Access Only)</option>
                            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Administrator (Full System & User Management Access)</option>
                        </select>
                        <div class="form-text font-size-12 text-secondary mt-2">
                            <strong>Admin:</strong> Can manage users, run assessments, and execute system maintenance.<br>
                            <strong>Tester:</strong> Can create and view security assessments, but cannot access user management or system operations.
                        </div>
                        @error('role')
                            <div class="invalid-feedback font-size-12">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="{{ route('users.index') }}" class="app-btn-secondary">Cancel</a>
                        <button type="submit" class="app-btn-primary">
                            <i class="bi bi-check-circle"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
