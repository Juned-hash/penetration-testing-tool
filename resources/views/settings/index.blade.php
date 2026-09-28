@extends('layouts.app')

@section('title', 'Settings')
@section('header', 'Platform Settings')

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="max-width: 700px;">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4" style="max-width: 700px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3">User Profile</h5>
        <div class="mb-3">
            <label class="form-label text-muted small">Name</label>
            <div class="fw-semibold">{{ $user->name }}</div>
        </div>
        <div class="mb-3">
            <label class="form-label text-muted small">Email Address</label>
            <div class="fw-semibold">{{ $user->email }}</div>
        </div>
        <div class="mb-3">
            <label class="form-label text-muted small">Role</label>
            <div>
                <span class="badge {{ $user->role === 'admin' ? 'bg-danger' : 'bg-primary' }} text-uppercase">
                    {{ $user->role }}
                </span>
            </div>
        </div>
        <hr class="my-4">
        <h5 class="fw-bold mb-3">OWASP ZAP Engine Configuration</h5>
        <div class="mb-3">
            <label class="form-label text-muted small">Execution Environment</label>
            <div class="badge bg-light text-dark border p-2">Standard ZAP Automation Framework</div>
        </div>
    </div>
</div>

@if ($user->role === 'admin')
<div class="card border-0 shadow-sm" style="max-width: 700px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3 text-uppercase text-secondary fs-6 tracking-wide">Queue & Storage</h5>
        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded border">
            <div>
                <div class="fw-bold text-dark">Restart Queue Workers</div>
                <div class="text-muted small">Gracefully restart all queue worker processes</div>
            </div>
            <form method="POST" action="{{ route('settings.queue.restart') }}" onsubmit="this.querySelector('button').disabled = true;">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm px-3 fw-semibold">
                    <i class="bi bi-arrow-repeat me-1"></i> Run
                </button>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
