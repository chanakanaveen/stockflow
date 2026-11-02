<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveTransferRequest;
use App\Http\Requests\StockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Inventory\TransferPostingService;
use App\Services\Inventory\TransferWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StockTransferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', StockTransfer::class);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'in:draft,submitted,approved_posted,rejected,cancelled'], 'from_store_id' => ['sometimes', 'integer'], 'to_store_id' => ['sometimes', 'integer'],
            'approval_inbox' => ['sometimes', 'boolean'], 'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])]]);
        /** @var User $actor */ $actor = $request->user();
        $query = StockTransfer::query()->accessibleTo($actor)->with('lines')->orderByDesc('id');
        if ($request->boolean('approval_inbox')) {
            Gate::authorize('inbox', StockTransfer::class);
            $query->awaitingApprovalBy($actor);
        }
        foreach (['status', 'from_store_id', 'to_store_id'] as $filter) {
            if ($request->has($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from', 'Y-m-d', 'UTC')->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<', $request->date('date_to', 'Y-m-d', 'UTC')->addDay()->startOfDay());
        }

        return StockTransferResource::collection($query->paginate($request->integer('per_page', 25)))->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function show(StockTransfer $transfer): StockTransferResource
    {
        Gate::authorize('view', $transfer);

        return new StockTransferResource($transfer->load('lines'));
    }

    public function store(StockTransferRequest $request, TransferWriter $writer): JsonResponse
    {
        /** @var User $actor */ $actor = $request->user();

        return (new StockTransferResource($writer->create($actor, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(StockTransferRequest $request, StockTransfer $transfer, TransferWriter $writer): StockTransferResource
    {
        /** @var User $actor */ $actor = $request->user();

        return new StockTransferResource($writer->update($actor, $transfer, $request->validated()));
    }

    public function submit(Request $request, StockTransfer $transfer, TransferWriter $writer): StockTransferResource
    {
        /** @var User $actor */ $actor = $request->user();

        return new StockTransferResource($writer->submit($actor, $transfer));
    }

    public function cancel(Request $request, StockTransfer $transfer, TransferWriter $writer): StockTransferResource
    {
        /** @var User $actor */ $actor = $request->user();

        return new StockTransferResource($writer->cancel($actor, $transfer));
    }

    public function reject(Request $request, StockTransfer $transfer, TransferWriter $writer): StockTransferResource
    {
        Gate::authorize('reject', $transfer);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        /** @var User $actor */ $actor = $request->user();

        return new StockTransferResource($writer->reject($actor, $transfer, $data['rejection_reason']));
    }

    public function approve(ApproveTransferRequest $request, StockTransfer $transfer, TransferPostingService $posting): JsonResponse
    {
        /** @var User $actor */ $actor = $request->user();
        $result = $posting->approve($actor, $transfer, $request->idempotencyKey(), $request->except('_token'));

        return response()->json([...$result, 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }
}
