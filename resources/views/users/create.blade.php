@extends('layouts.app')

@section('title', 'Add New User')
@section('header', 'User Management')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0"><i class="bi bi-person-plus me-2 text-primary"></i> Add New User</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('users.store') }}">
                    @csrf

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Jane Doe" required autofocus>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Email Address -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="user@organization.com" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Password and Password Confirmation -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="Minimum 8 characters" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Re-enter password" required>
                        </div>
                    </div>

                    <!-- Role Selection -->
                    <div class="mb-4">
                        <label for="role" class="form-label fw-semibold">User Role <span class="text-danger">*</span></label>
                        <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                            <option value="tester" {{ old('role', 'tester') === 'tester' ? 'selected' : '' }}>Tester (Assessment & Scanning Access Only)</option>
                            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Administrator (Full System & User Management Access)</option>
                        </select>
                        <div class="form-text">
                            <strong>Admin:</strong> Can manage users and run assessments.<br>
                            <strong>Tester:</strong> Can create and view security assessments, but cannot access user management.
                        </div>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="bi bi-check-circle me-1"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
