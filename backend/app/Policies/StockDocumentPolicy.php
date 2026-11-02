<?php

namespace App\Policies;

use App\Enums\DocumentType;
use App\Models\StockDocument;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('inventory.view');
    }

    public function create(User $user, DocumentType $type): bool
    {
        return $user->is_active && $user->can($type->permission());
    }

    public function view(User $user, StockDocument $document): Response
    {
        $store = Store::findOrFail($document->store_id);
        if (! $user->canAccessStore($store)) {
            return Response::denyAsNotFound();
        }

        return $this->viewAny($user) ? Response::allow() : Response::deny();
    }

    public function update(User $user, StockDocument $document): Response
    {
        $view = $this->view($user, $document);
        if ($view->denied()) {
            return $view;
        }

        return $user->can($document->type->permission()) ? Response::allow() : Response::deny();
    }

    public function post(User $user, StockDocument $document): Response
    {
        return $this->update($user, $document);
    }
}
