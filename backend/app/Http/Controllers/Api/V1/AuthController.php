<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->with('company')->where('email', mb_strtolower(trim((string) $request->input('email'))))->first();

        if ($user === null || $user->is_service_account || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        if (! $user->isEnabled()) {
            throw ValidationException::withMessages(['email' => 'This account or its company has been deactivated.']);
        }

        $expiresAt = CarbonImmutable::now()->addHours(config('shipments.login_token_ttl_hours'));
        $token = $user->createToken('web-ui', ['write'], $expiresAt);
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $this->profile($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->profile($request->user()->load('company'))]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function password(ChangePasswordRequest $request): Response
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->noContent();
    }

    private function profile(User $user): array
    {
        return [
            ...(new UserResource($user))->resolve(),
            'permissions' => [
                'manage_companies' => $user->isSuperAdmin(),
                'manage_users' => $user->canManageUsers(),
                'select_company' => $user->isSuperAdmin(),
            ],
        ];
    }
}
