<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('transfers.view');
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can('transfers.create');
    }

    public function inbox(User $user): bool
    {
        return $this->viewAny($user) && $user->can('transfers.approve');
    }

    public function view(User $user, StockTransfer $transfer): Response
    {
        return $this->action($user, $transfer, 'view');
    }

    public function update(User $user, StockTransfer $transfer): Response
    {
        return $this->action($user, $transfer, 'update');
    }

    public function submit(User $user, StockTransfer $transfer): Response
    {
        return $this->action($user, $transfer, 'submit');
    }

    public function cancel(User $user, StockTransfer $transfer): Response
    {
        return $this->action($user, $transfer, 'cancel');
    }

    public function reject(User $user, StockTransfer $transfer): Response
    {
        return $this->action($user, $transfer, 'reject');
    }

    public function approve(User $user, StockTransfer $transfer): Response
    {
        $access = $this->action($user, $transfer, 'approve');
        if ($access->denied()) {
            return $access;
        }

        return $user->id === $transfer->created_by || $user->id === $transfer->submitted_by
            ? Response::deny('The creator and submitter cannot approve this transfer.') : Response::allow();
    }

    private function action(User $user, StockTransfer $transfer, string $action): Response
    {
        foreach ([$transfer->from_store_id, $transfer->to_store_id] as $storeId) {
            if (! $user->canAccessStore(Store::findOrFail($storeId))) {
                return Response::denyAsNotFound();
            }
        }

        return $user->is_active && $user->can('transfers.'.$action) ? Response::allow() : Response::deny();
    }
}
