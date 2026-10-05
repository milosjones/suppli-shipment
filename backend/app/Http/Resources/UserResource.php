<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_label' => $this->role->label(),
            'is_service_account' => $this->is_service_account,
            'is_active' => $this->is_active,
            'company' => $this->whenLoaded('company', fn () => $this->company === null ? null : [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ]),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'tokens' => $this->whenLoaded('tokens', fn () => $this->tokens->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
