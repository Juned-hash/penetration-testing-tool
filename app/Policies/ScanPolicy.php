<?php

namespace App\Policies;

use App\Models\Scan;
use App\Models\User;

class ScanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Scan $scan): bool
    {
        return $user->id === $scan->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Scan $scan): bool
    {
        return $user->id === $scan->user_id;
    }

    public function delete(User $user, Scan $scan): bool
    {
        return $user->id === $scan->user_id;
    }
}
