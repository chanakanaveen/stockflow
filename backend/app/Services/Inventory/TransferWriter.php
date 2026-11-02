<?php

namespace App\Services\Inventory;

use App\Enums\TransferStatus;
use App\Exceptions\InventoryException;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransferWriter
{
    public function __construct(private NumberAllocator $numbers, private StockValidator $stock, private AuditLogger $audit) {}

    /** @param array<string,mixed> $data */
    public function create(User $actor, array $data): StockTransfer
    {
        Gate::forUser($actor)->authorize('create', StockTransfer::class);

        return DB::transaction(function () use ($actor, $data) {
            $this->validate($actor, (int) $data['from_store_id'], (int) $data['to_store_id'], $data['lines']);
            $transfer = StockTransfer::create(['number' => $this->numbers->nextTransfer(), 'from_store_id' => $data['from_store_id'],
                'to_store_id' => $data['to_store_id'], 'status' => TransferStatus::Draft, 'created_by' => $actor->id]);
            $this->replaceLines($transfer, $data['lines']);
            $this->audit->record('transfer.draft_created', $transfer, $actor);

            return $transfer->refresh()->load('lines');
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function update(User $actor, StockTransfer $transfer, array $data): StockTransfer
    {
        return DB::transaction(function () use ($actor, $transfer, $data) {
            $locked = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $this->requireStatus($locked, TransferStatus::Draft);
            $lines = $data['lines'] ?? $this->lines($locked);
            $this->validate($actor, (int) ($data['from_store_id'] ?? $locked->from_store_id), (int) ($data['to_store_id'] ?? $locked->to_store_id), $lines);
            $locked->fill(array_intersect_key($data, array_flip(['from_store_id', 'to_store_id'])))->save();
            if (array_key_exists('lines', $data)) {
                $this->replaceLines($locked, $lines);
            }
            $this->audit->record('transfer.draft_updated', $locked, $actor);

            return $locked->refresh()->load('lines');
        }, 3);
    }

    public function submit(User $actor, StockTransfer $transfer): StockTransfer
    {
        return $this->transition($actor, $transfer, 'submit', TransferStatus::Draft, function (StockTransfer $locked) use ($actor): void {
            $this->validate($actor, $locked->from_store_id, $locked->to_store_id, $this->lines($locked));
            $locked->update(['status' => TransferStatus::Submitted, 'submitted_by' => $actor->id, 'submitted_at' => now('UTC')]);
        });
    }

    public function cancel(User $actor, StockTransfer $transfer): StockTransfer
    {
        return $this->transition($actor, $transfer, 'cancel', TransferStatus::Draft, function (StockTransfer $locked): void {
            $locked->update(['status' => TransferStatus::Cancelled]);
        });
    }

    public function reject(User $actor, StockTransfer $transfer, string $reason): StockTransfer
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['rejection_reason' => ['Provide a reason of at most 1000 characters.']]);
        }

        return $this->transition($actor, $transfer, 'reject', TransferStatus::Submitted, function (StockTransfer $locked) use ($actor, $reason): void {
            $locked->update(['status' => TransferStatus::Rejected, 'rejected_by' => $actor->id, 'rejected_at' => now('UTC'), 'rejection_reason' => $reason]);
        });
    }

    private function transition(User $actor, StockTransfer $transfer, string $action, TransferStatus $required, \Closure $change): StockTransfer
    {
        return DB::transaction(function () use ($actor, $transfer, $action, $required, $change) {
            $locked = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            Gate::forUser($actor)->authorize($action, $locked);
            $this->requireStatus($locked, $required);
            $change($locked);
            $this->audit->record('transfer.'.$action, $locked, $actor, ['status' => $locked->status->value]);

            return $locked->refresh()->load('lines');
        }, 3);
    }

    public function requireStatus(StockTransfer $transfer, TransferStatus $status): void
    {
        if ($transfer->status !== $status) {
            throw new InventoryException('INVALID_STATE', 'This action is not allowed in the current transfer state.');
        }
    }

    /** @return list<array{product_id:int,quantity:string}> */
    public function lines(StockTransfer $transfer): array
    {
        return $transfer->lines->map(fn ($line) => ['product_id' => $line->product_id, 'quantity' => $line->quantity])->all();
    }

    /** @param list<array{product_id:int,quantity:string}> $lines */
    public function validate(User $actor, int $from, int $to, array $lines): void
    {
        if ($from === $to) {
            throw ValidationException::withMessages(['to_store_id' => ['Source and destination must differ.']]);
        }
        $this->stock->validate($actor, [$from, $to], $lines);
    }

    /** @param list<array{product_id:int,quantity:string}> $lines */
    private function replaceLines(StockTransfer $transfer, array $lines): void
    {
        $transfer->lines()->delete();
        usort($lines, fn ($a, $b) => $a['product_id'] <=> $b['product_id']);
        foreach ($lines as $line) {
            $transfer->lines()->create(['product_id' => $line['product_id'], 'quantity' => Quantity::normalize($line['quantity'])]);
        }
    }
}
