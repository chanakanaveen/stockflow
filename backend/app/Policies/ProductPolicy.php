<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('products.view');
    }

    public function view(User $user, Product $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can('products.create');
    }

    public function update(User $user, Product $model): bool
    {
        return $user->is_active && $user->can('products.update');
    }
}
