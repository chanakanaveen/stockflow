<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Http\Requests\PostStockRequest;
use App\Http\Requests\StockDocumentRequest;
use App\Http\Resources\StockDocumentResource;
use App\Models\StockDocument;
use App\Models\User;
use App\Services\Inventory\DocumentWriter;
use App\Services\Inventory\PostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StockDocumentController extends Controller
{
    private function type(Request $request): DocumentType
    {
        return DocumentType::from($request->route('kind'));
    }

    private function requireType(Request $request, StockDocument $document): void
    {
        abort_unless($document->type === $this->type($request), 404);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', StockDocument::class);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'store_id' => ['sometimes', 'integer'], 'status' => ['sometimes', 'in:draft,posted,cancelled']]);
        /** @var User $actor */ $actor = $request->user();
        $query = StockDocument::query()->accessibleTo($actor)->where('type', $this->type($request)->value)->with('lines')->orderByDesc('id');
        if ($request->has('store_id')) {
            $query->where('store_id', $request->integer('store_id'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return StockDocumentResource::collection($query->paginate($request->integer('per_page', 25)))->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function show(Request $request, StockDocument $document): StockDocumentResource
    {
        $this->requireType($request, $document);
        Gate::authorize('view', $document);

        return new StockDocumentResource($document->load('lines'));
    }

    public function store(StockDocumentRequest $request, DocumentWriter $writer): JsonResponse
    {
        /** @var User $actor */ $actor = $request->user();

        return (new StockDocumentResource($writer->create($actor, $this->type($request), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(StockDocumentRequest $request, StockDocument $document, DocumentWriter $writer): StockDocumentResource
    {
        $this->requireType($request, $document);
        /** @var User $actor */ $actor = $request->user();

        return new StockDocumentResource($writer->update($actor, $document, $request->validated()));
    }

    public function cancel(Request $request, StockDocument $document, DocumentWriter $writer): StockDocumentResource
    {
        $this->requireType($request, $document);
        /** @var User $actor */ $actor = $request->user();

        return new StockDocumentResource($writer->cancel($actor, $document));
    }

    public function post(PostStockRequest $request, StockDocument $document, PostingService $posting): JsonResponse
    {
        $this->requireType($request, $document);
        /** @var User $actor */ $actor = $request->user();
        $body = $posting->post($actor, $document, $request->idempotencyKey(), $request->except('_token'));

        return response()->json([...$body, 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }
}
