<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserForCompanyRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use App\Services\Users\UserAccounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserAccounts $accounts) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['company_id' => ['sometimes', 'integer', 'exists:companies,id']]);

        return UserResource::collection(
            User::query()
                ->with('company')
                ->where('is_service_account', false)
                ->when($request->filled('company_id'), fn (Builder $query) => $query->where('company_id', $request->integer('company_id')))
                ->orderBy('name')
                ->get(),
        );
    }

    public function store(StoreUserForCompanyRequest $request): JsonResponse
    {
        $company = Company::query()->findOrFail($request->integer('company_id'));
        $user = $this->accounts->create($company, $request->safe()->except('company_id'), $request->user());

        return (new UserResource($user->load('company')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        abort_if($user->is_service_account || $user->isSuperAdmin(), Response::HTTP_NOT_FOUND);

        return new UserResource($this->accounts->update($user, $request->validated(), $request->user())->load('company'));
    }
}
