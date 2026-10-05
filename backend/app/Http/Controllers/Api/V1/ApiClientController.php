<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreApiTokenRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ApiClientController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()
                ->where('company_id', $this->tenant->id())
                ->where('is_service_account', true)
                ->with(['tokens' => fn ($query) => $query->latest('id')])
                ->orderBy('name')
                ->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $client = User::create([
            'company_id' => $this->tenant->id(),
            'name' => $data['name'],
            'role' => UserRole::CompanyUser,
            'is_service_account' => true,
            'created_by' => $request->user()->id,
        ]);

        return (new UserResource($client->load('tokens')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, User $client): UserResource
    {
        $this->authorizeClient($client);

        $client->update($request->validate(['is_active' => ['required', 'boolean']]));

        return new UserResource($client->load(['tokens' => fn ($query) => $query->latest('id')]));
    }

    public function storeToken(StoreApiTokenRequest $request, User $client): JsonResponse
    {
        $this->authorizeClient($client);
        abort_unless($client->is_active, Response::HTTP_UNPROCESSABLE_ENTITY, 'This API client is disabled.');

        $days = $request->integer('expires_in_days') ?: null;
        $token = $client->createToken(
            (string) $request->validated('name'),
            $request->input('abilities', []),
            $days === null ? null : CarbonImmutable::now()->addDays($days),
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'id' => $token->accessToken->id,
            'name' => $token->accessToken->name,
            'abilities' => $token->accessToken->abilities,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], Response::HTTP_CREATED);
    }

    public function destroyToken(User $client, int $token): Response
    {
        $this->authorizeClient($client);
        abort_unless($client->tokens()->whereKey($token)->delete() > 0, Response::HTTP_NOT_FOUND);

        return response()->noContent();
    }

    private function authorizeClient(User $client): void
    {
        abort_unless($client->company_id === $this->tenant->id() && $client->is_service_account, Response::HTTP_NOT_FOUND);
    }
}
