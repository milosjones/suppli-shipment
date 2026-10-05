<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class CompanyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CompanyResource::collection(
            Company::query()->withCount(['users', 'shipments'])->orderBy('name')->get(),
        );
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = DB::transaction(function () use ($request): Company {
            $company = Company::create(['name' => trim((string) $request->validated('name'))]);

            if ($request->filled('admin')) {
                $company->users()->create([
                    'name' => $request->input('admin.name'),
                    'email' => $request->input('admin.email'),
                    'password' => $request->input('admin.password'),
                    'role' => UserRole::CompanyAdmin,
                    'created_by' => $request->user()->id,
                ]);
            }

            return $company;
        });

        return (new CompanyResource($company->loadCount(['users', 'shipments'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        $company->fill($request->validated());

        if ($company->isDirty('name')) {
            $company->name = trim($company->name);
        }

        $company->save();

        if ($company->wasChanged('is_active') && ! $company->is_active) {
            PersonalAccessToken::query()
                ->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', $company->users()->select('id'))
                ->delete();
        }

        return new CompanyResource($company->loadCount(['users', 'shipments']));
    }
}
