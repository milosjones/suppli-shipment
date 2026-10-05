<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $company = $user?->isSuperAdmin() ? $this->selectedCompany($request) : $user?->company;

        if ($company === null) {
            return response()->json([
                'message' => $user?->isSuperAdmin()
                    ? 'Select a company: send its id in the X-Company-Id header.'
                    : 'Your account is not linked to a company.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $previous = $this->tenant->company();
        $this->tenant->set($company);

        try {
            return $next($request);
        } finally {
            $this->tenant->set($previous);
        }
    }

    private function selectedCompany(Request $request): ?Company
    {
        $id = trim((string) $request->header('X-Company-Id'));

        if ($id === '') {
            return null;
        }

        abort_unless(ctype_digit($id), Response::HTTP_BAD_REQUEST, 'X-Company-Id must be a company id.');

        return Company::query()->find((int) $id) ?? abort(Response::HTTP_NOT_FOUND, 'Company not found.');
    }
}
