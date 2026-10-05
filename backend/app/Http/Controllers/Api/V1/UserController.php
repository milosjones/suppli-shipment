<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Users\UserAccounts;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly UserAccounts $accounts,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()
                ->where('company_id', $this->tenant->id())
                ->where('is_service_account', false)
                ->orderBy('name')
                ->get(),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->accounts->create($this->tenant->require(), $request->validated(), $request->user());

        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        abort_unless($user->company_id === $this->tenant->id() && ! $user->is_service_account, Response::HTTP_NOT_FOUND);

        return new UserResource($this->accounts->update($user, $request->validated(), $request->user()));
    }
}
