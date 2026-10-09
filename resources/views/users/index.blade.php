@extends('layouts.app')

@section('title', 'User Management')
@section('header', 'User Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1 font-size-26 text-dark">Application Users & Access Control</h4>
        <p class="text-secondary mb-0 font-size-14">Manage user accounts and role-based permissions (Admin & Tester).</p>
    </div>
    <a href="{{ route('users.create') }}" class="app-btn-primary">
        <i class="bi bi-person-plus-fill"></i> Add User
    </a>
</div>

<div class="glass-card">
    <div class="table-responsive">
        <table class="table glass-table align-middle">
            <thead>
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
                        <td class="ps-4 font-monospace font-size-12 text-secondary">#{{ $user->id }}</td>
                        <td class="fw-semibold font-size-14 text-dark">{{ $user->name }}</td>
                        <td class="font-size-14 text-dark">{{ $user->email }}</td>
                        <td>
                            @if($user->isAdmin())
                                <span class="glass-badge bg-danger text-white font-size-12"><i class="bi bi-shield-lock-fill me-1"></i> ADMIN</span>
                            @else
                                <span class="glass-badge bg-info text-white font-size-12"><i class="bi bi-person-badge-fill me-1"></i> TESTER</span>
                            @endif
                        </td>
                        <td class="font-size-12 text-secondary">{{ $user->created_at->format('Y-m-d H:i') }}</td>
                        <td class="text-end pe-4">
                            <div class="d-inline-flex gap-2" role="group">
                                <a href="{{ route('users.edit', $user) }}" class="app-btn-secondary app-btn-sm" title="Edit User">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                @if(Auth::id() !== $user->id)
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete user {{ $user->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="app-btn-danger app-btn-sm" title="Delete User">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-secondary font-size-14">
                            No user accounts found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
        <div class="p-3 border-top">
            {{ $users->links() }}
        </div>
    @endif
</div>
@endsection
