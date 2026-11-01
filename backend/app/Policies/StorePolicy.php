<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('stores.view');
    }

    public function view(User $user, Store $store): Response
    {
        if (! $user->canAccessStore($store)) {
            return Response::denyAsNotFound();
        }

        return $this->viewAny($user) ? Response::allow() : Response::deny();
    }
}
