<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('categories.view');
    }

    public function view(User $user, Category $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can('categories.create');
    }

    public function update(User $user, Category $model): bool
    {
        return $user->is_active && $user->can('categories.update');
    }
}
