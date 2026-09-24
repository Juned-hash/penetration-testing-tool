@extends('layouts.app')

@section('title', 'User Management')
@section('header', 'User Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Application Users & Access Control</h4>
        <p class="text-muted mb-0">Manage user accounts and role-based permissions (Admin & Tester).</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-primary fw-bold">
        <i class="bi bi-person-plus-fill me-1"></i> Add User
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th scope="col" class="ps-4">ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Created At</th>
                        <th scope="col" class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="ps-4 font-monospace small">#{{ $user->id }}</td>
                            <td class="fw-semibold">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @if($user->isAdmin())
                                    <span class="badge bg-danger"><i class="bi bi-shield-lock-fill me-1"></i> ADMIN</span>
                                @else
                                    <span class="badge bg-info text-dark"><i class="bi bi-person-badge-fill me-1"></i> TESTER</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $user->created_at->format('Y-m-d H:i') }}</td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-secondary" title="Edit User">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    @if(Auth::id() !== $user->id)
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete user {{ $user->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="Delete User">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                No user accounts found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($users->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $users->links() }}
        </div>
    @endif
</div>
@endsection
