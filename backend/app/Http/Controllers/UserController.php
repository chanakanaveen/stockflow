<?php

namespace App\Http\Controllers;

use App\Exceptions\InventoryException;
use App\Http\Requests\ManageUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'search' => ['sometimes', 'string', 'max:100']]);
        $query = User::query()->with(['roles', 'permissions'])->orderBy('id');
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search));
        }

        return UserResource::collection($query->paginate($request->integer('per_page', 25)))->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function roles(): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return response()->json(['data' => ['System Admin', 'Inventory Manager', 'Store Keeper', 'Auditor'], 'meta' => ['request_id' => request()->attributes->get('request_id')]]);
    }

    public function store(ManageUserRequest $request, AuditLogger $audit): JsonResponse
    {
        return (new UserResource($this->save($request, $audit)))->response()->setStatusCode(201);
    }

    public function update(ManageUserRequest $request, User $user, AuditLogger $audit): UserResource
    {
        return new UserResource($this->save($request, $audit, $user));
    }

    private function save(ManageUserRequest $request, AuditLogger $audit, ?User $user = null): User
    {
        /** @var User $actor */ $actor = $request->user();

        return DB::transaction(function () use ($request, $audit, $user, $actor) {
            // Serialize role/activation changes before checking the last active administrator.
            Role::query()->where('guard_name', 'web')->where('name', 'System Admin')->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $target = $user ? User::query()->lockForUpdate()->findOrFail($user->id) : new User;
            if ($target->exists && $target->is_active && $target->hasRole('System Admin') && (! $data['is_active'] || $data['role'] !== 'System Admin')) {
                $remaining = User::query()->where('is_active', true)->whereKeyNot($target->id)->role('System Admin')->lockForUpdate()->get();
                if ($remaining->isEmpty()) {
                    throw new InventoryException('LAST_ADMIN', 'Keep at least one active System Admin.');
                }
            }
            $target->fill(array_intersect_key($data, array_flip(['name', 'email', 'password'])));
            $target->is_active = $data['is_active'];
            $target->save();
            $target->syncRoles([$data['role']]);
            $target->stores()->sync($data['store_ids']);
            $audit->record($user ? 'user.updated' : 'user.created', $target, $actor, ['role' => $data['role'], 'is_active' => $data['is_active'], 'store_ids' => $data['store_ids']]);
            if ($user && (isset($data['password']) || ! $data['is_active'])) {
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }

            return $target->refresh();
        }, 3);
    }
}
