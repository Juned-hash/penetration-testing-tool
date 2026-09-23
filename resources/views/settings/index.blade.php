@extends('layouts.app')

@section('title', 'Settings')
@section('header', 'Platform Settings')

@section('content')
<div class="card border-0 shadow-sm" style="max-width: 700px;">
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
        <hr class="my-4">
        <h5 class="fw-bold mb-3">OWASP ZAP Engine Configuration</h5>
        <div class="mb-3">
            <label class="form-label text-muted small">Execution Environment</label>
            <div class="badge bg-light text-dark border p-2">Standard ZAP Automation Framework (ZAP_BINARY_PATH)</div>
        </div>
    </div>
</div>
@endsection
