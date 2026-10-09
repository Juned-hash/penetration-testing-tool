@extends('layouts.app')

@section('title', 'Settings')
@section('header', 'Platform Settings')

@section('content')
<div class="row g-4">
    <!-- User Profile & Shared Data Operations (Left Side) -->
    <div class="col-12 col-lg-6">
        <div class="glass-card mb-4">
            <div class="glass-card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-person-badge me-2 text-primary"></i>User Profile</h5>
                <span class="glass-badge bg-primary text-white font-size-12">{{ strtoupper($user->role) }}</span>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label text-secondary font-size-12 fw-semibold text-uppercase">Account Name</label>
                    <div class="fw-bold font-size-16 text-dark">{{ $user->name }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary font-size-12 fw-semibold text-uppercase">Email Address</label>
                    <div class="fw-bold font-size-16 text-dark">{{ $user->email }}</div>
                </div>
                <div>
                    <label class="form-label text-secondary font-size-12 fw-semibold text-uppercase">Access Privilege</label>
                    <div>
                        <span class="badge {{ $user->role === 'admin' ? 'bg-danger' : 'bg-info' }} font-size-12 text-uppercase px-3 py-2">
                            <i class="bi bi-shield-check me-1"></i>{{ $user->role }} Access
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card mb-4">
            <div class="glass-card-header">
                <h5 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-cpu me-2 text-primary"></i>OWASP ZAP Engine Configuration</h5>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label text-secondary font-size-12 fw-semibold text-uppercase">Execution Environment</label>
                    <div class="glass-panel d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam font-size-20 text-primary"></i>
                        <span class="fw-semibold font-size-14 text-dark">Standard ZAP Automation Framework (Docker Containerized)</span>
                    </div>
                </div>
                <div>
                    <label class="form-label text-secondary font-size-12 fw-semibold text-uppercase">Queue Workers Scale</label>
                    <div class="d-flex gap-2">
                        <div class="glass-panel flex-fill py-2 px-3 text-center">
                            <div class="font-size-12 text-secondary">Assessment Workers</div>
                            <div class="font-size-20 fw-bold text-dark">{{ env('ASSESSMENT_WORKERS', 3) }}</div>
                        </div>
                        <div class="glass-panel flex-fill py-2 px-3 text-center">
                            <div class="font-size-12 text-secondary">Report Workers</div>
                            <div class="font-size-20 fw-bold text-dark">{{ env('REPORT_WORKERS', 1) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Seeders (Accessible to both Tester & Admin) -->
        <div class="glass-card">
            <div class="glass-card-header">
                <h5 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-diagram-3 me-2 text-primary"></i>Data Operations</h5>
            </div>
            <div class="card-body p-4">
                <div class="glass-panel d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark font-size-16">Run Database Seeders</div>
                        <div class="text-secondary font-size-12">Populate default system data and admin roles</div>
                    </div>
                    <form method="POST" action="{{ route('settings.db.seed') }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button type="submit" class="app-btn-primary app-btn-sm">
                            <i class="bi bi-diagram-3"></i> Seed DB
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Maintenance Tools (Right Side) -->
    @if ($user->role === 'admin')
    <div class="col-12 col-lg-6">
        <div class="glass-card h-100">
            <div class="glass-card-header d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0 font-size-18 text-dark"><i class="bi bi-tools me-2 text-danger"></i>System Operations & Maintenance</h5>
                <span class="glass-badge bg-danger text-white font-size-12">ADMIN ONLY</span>
            </div>
            <div class="card-body p-4 d-flex flex-column gap-3">

                <!-- Restart Queue Workers -->
                <div class="glass-panel d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark font-size-16">Restart & Scale Queue Workers</div>
                        <div class="text-secondary font-size-12">Gracefully signals workers and runs Docker compose scaling</div>
                    </div>
                    <form method="POST" action="{{ route('settings.queue.restart') }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button type="submit" class="app-btn-danger app-btn-sm">
                            <i class="bi bi-arrow-repeat"></i> Run
                        </button>
                    </form>
                </div>

                <!-- Database Migration -->
                <div class="glass-panel d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark font-size-16">Run Database Migrations</div>
                        <div class="text-secondary font-size-12">Execute pending schema migrations (force)</div>
                    </div>
                    <form method="POST" action="{{ route('settings.migrate') }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button type="submit" class="app-btn-primary app-btn-sm">
                            <i class="bi bi-database-gear"></i> Migrate
                        </button>
                    </form>
                </div>

                <!-- Clear Config Cache -->
                <div class="glass-panel d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark font-size-16">Clear Config Cache</div>
                        <div class="text-secondary font-size-12">Flush configuration cache files</div>
                    </div>
                    <form method="POST" action="{{ route('settings.config.clear') }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button type="submit" class="app-btn-secondary app-btn-sm">
                            <i class="bi bi-trash3"></i> Clear Config
                        </button>
                    </form>
                </div>

                <!-- Clear Application Cache -->
                <div class="glass-panel d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark font-size-16">Clear Application Cache</div>
                        <div class="text-secondary font-size-12">Purge stored system data cache</div>
                    </div>
                    <form method="POST" action="{{ route('settings.cache.clear') }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button type="submit" class="app-btn-secondary app-btn-sm">
                            <i class="bi bi-x-circle"></i> Clear Cache
                        </button>
                    </form>
                </div>

                <!-- Clear Route Cache -->
                <div class="glass-panel d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark font-size-16">Clear Route Cache</div>
                        <div class="text-secondary font-size-12">Flush cached application routes</div>
                    </div>
                    <form method="POST" action="{{ route('settings.route.clear') }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button type="submit" class="app-btn-secondary app-btn-sm">
                            <i class="bi bi-signpost-split"></i> Clear Routes
                        </button>
                    </form>
                </div>

                <!-- Optimize Clear -->
                <div class="glass-panel d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold text-dark font-size-16">Clear Optimization Caches</div>
                        <div class="text-secondary font-size-12">Purge all framework optimization caches (optimize:clear)</div>
                    </div>
                    <form method="POST" action="{{ route('settings.optimize.clear') }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button type="submit" class="app-btn-warning app-btn-sm">
                            <i class="bi bi-lightning-charge"></i> Optimize Clear
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
    @endif
</div>
@endsection
