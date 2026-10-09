@extends('layouts.app')

@section('title', 'New Security Assessment')
@section('header', 'Configure New Security Assessment')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="glass-card">
            <div class="glass-card-header">
                <h5 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-shield-plus me-2 text-primary"></i>Assessment Target, Scope & Authentication Setup</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('scans.store') }}">
                    @csrf

                    <!-- Section 1: Assessment Information -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-primary mb-3 font-size-16"><i class="bi bi-1-circle me-1"></i> Assessment Information</h6>
                        <div class="mb-3">
                            <label for="name" class="form-label font-size-14 fw-semibold text-secondary">Application Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control glass-input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Customer Web Portal" required>
                            @error('name')
                                <div class="invalid-feedback font-size-12">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="target_url" class="form-label font-size-14 fw-semibold text-secondary">Target Website URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control glass-input @error('target_url') is-invalid @enderror" id="target_url" name="target_url" value="{{ old('target_url') }}" placeholder="https://staging.example.com" required>
                                <div class="form-text font-size-12 text-secondary">Must be a valid HTTP/HTTPS URL within your authorized scope.</div>
                                @error('target_url')
                                    <div class="invalid-feedback font-size-12">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="environment" class="form-label font-size-14 fw-semibold text-secondary">Environment <span class="text-danger">*</span></label>
                                <select class="form-select glass-select @error('environment') is-invalid @enderror" id="environment" name="environment" required>
                                    <option value="development" {{ old('environment') == 'development' ? 'selected' : '' }}>Development</option>
                                    <option value="staging" {{ old('environment', 'staging') == 'staging' ? 'selected' : '' }}>Staging</option>
                                    <option value="production" {{ old('environment') == 'production' ? 'selected' : '' }}>Production</option>
                                </select>
                                @error('environment')
                                    <div class="invalid-feedback font-size-12">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Section 2: Scope Configuration -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-primary mb-3 font-size-16"><i class="bi bi-2-circle me-1"></i> Target Scope & Exclusions</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="included_paths" class="form-label font-size-14 fw-semibold text-secondary">Included Scope (Paths / URL Patterns)</label>
                                <textarea class="form-control glass-textarea font-monospace" id="included_paths" name="included_paths" rows="4" placeholder="/*&#10;/api/*&#10;/dashboard/*">{{ old('included_paths', "/*") }}</textarea>
                                <div class="form-text font-size-12 text-secondary">Enter one pattern per line. Defaults to <code>/*</code>.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="excluded_paths" class="form-label font-size-14 fw-semibold text-secondary">Excluded Paths</label>
                                <textarea class="form-control glass-textarea font-monospace" id="excluded_paths" name="excluded_paths" rows="4" placeholder="/logout&#10;/payment/*&#10;/admin/backup/*">{{ old('excluded_paths') }}</textarea>
                                <div class="form-text font-size-12 text-secondary">Paths to exclude from crawling and active security testing.</div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Section 3: Authentication Configuration -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-primary mb-2 font-size-16"><i class="bi bi-3-circle me-1"></i> Authentication Settings</h6>
                        
                        <div class="glass-panel mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill text-primary font-size-16"></i>
                                <span class="font-size-14 text-dark"><strong>Dedicated Test Account Guidance:</strong> Always configure credentials for dedicated security testing accounts. Never use personal, administrative, or production master accounts.</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="auth_mode" class="form-label font-size-14 fw-semibold text-secondary">Authentication Mode</label>
                            <select class="form-select glass-select" id="auth_mode" name="auth_mode" onchange="toggleAuthFields(this.value)">
                                <option value="none" {{ old('auth_mode', 'none') == 'none' ? 'selected' : '' }}>No Authentication (Unauthenticated Assessment)</option>
                                <option value="form" {{ old('auth_mode') == 'form' ? 'selected' : '' }}>Form-Based Authentication</option>
                                <option value="browser" {{ old('auth_mode') == 'browser' ? 'selected' : '' }}>Browser-Based Authentication (SPA / JavaScript Login)</option>
                                <option value="token" {{ old('auth_mode') == 'token' ? 'selected' : '' }}>Token / API Header Authentication</option>
                            </select>
                        </div>

                        <!-- Form Auth Fields -->
                        <div id="form-auth-fields" class="glass-panel mb-3 d-none">
                            <h6 class="fw-bold mb-3 font-size-16 text-dark">Form-Based Login Details</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Login Page URL <span class="text-danger">*</span></label>
                                    <input type="url" class="form-control glass-input" name="login_url" value="{{ old('login_url') }}" placeholder="https://staging.example.com/login">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Authenticated Target URL</label>
                                    <input type="url" class="form-control glass-input" name="authenticated_url" value="{{ old('authenticated_url') }}" placeholder="https://staging.example.com/dashboard">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Username Field Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control glass-input" name="username_field" value="{{ old('username_field', 'email') }}" placeholder="email">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Password Field Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control glass-input" name="password_field" value="{{ old('password_field', 'password') }}" placeholder="password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Test Account Username / Email <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control glass-input" name="username" value="{{ old('username') }}" placeholder="security-test@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Test Account Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control glass-input" name="password" placeholder="••••••••">
                                    <div class="form-text font-size-12 text-secondary">Encrypted via Laravel symmetric encryption. Secrets are hidden in output.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Submit Button CSS Selector</label>
                                    <input type="text" class="form-control glass-input" name="login_button_selector" value="{{ old('login_button_selector') }}" placeholder="button[type='submit']">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Logged-in Indicator</label>
                                    <input type="text" class="form-control glass-input" name="logged_in_indicator" value="{{ old('logged_in_indicator') }}" placeholder="Log Out">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Logged-out Indicator</label>
                                    <input type="text" class="form-control glass-input" name="logged_out_indicator" value="{{ old('logged_out_indicator') }}" placeholder="Sign In">
                                </div>
                            </div>
                        </div>

                        <!-- Token Auth Fields -->
                        <div id="token-auth-fields" class="glass-panel mb-3 d-none">
                            <h6 class="fw-bold mb-3 font-size-16 text-dark">API Header / Token Configuration</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Header Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control glass-input" name="token_name" value="{{ old('token_name', 'Authorization') }}" placeholder="Authorization">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label font-size-14 fw-semibold text-secondary">Token / Secret Value <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control glass-input" name="token_value" value="{{ old('token_value') }}" placeholder="Bearer eyJhbGci...">
                                    <div class="form-text font-size-12 text-secondary">Encrypted via Laravel symmetric encryption. Secrets are hidden in output.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="{{ route('scans.index') }}" class="app-btn-secondary">Cancel</a>
                        <button type="submit" class="app-btn-primary">
                            Save Assessment Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleAuthFields(mode) {
        document.getElementById('form-auth-fields').classList.add('d-none');
        document.getElementById('token-auth-fields').classList.add('d-none');

        if (mode === 'form' || mode === 'browser') {
            document.getElementById('form-auth-fields').classList.remove('d-none');
        } else if (mode === 'token') {
            document.getElementById('token-auth-fields').classList.remove('d-none');
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        toggleAuthFields(document.getElementById('auth_mode').value);
    });
</script>
@endsection
