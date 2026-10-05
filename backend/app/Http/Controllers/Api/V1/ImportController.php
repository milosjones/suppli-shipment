<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportResource;
use App\Models\Import;
use App\Services\Import\DeliveryPlanImporter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ImportResource::collection(Import::query()->with('user')->latest('id')->paginate(20));
    }

    public function show(Import $import): ImportResource
    {
        return (new ImportResource($import->load('user')))->withReport();
    }

    public function store(StoreImportRequest $request, DeliveryPlanImporter $importer, TenantContext $tenant): JsonResponse
    {
        $file = $request->file('file');

        $import = $importer->import(
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $tenant->require(),
            $request->user(),
            $request->boolean('dry_run'),
        );

        $status = $import->status === ImportStatus::Failed ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_CREATED;

        return (new ImportResource($import->load('user')))->withReport()->response()->setStatusCode($status);
    }

    public function commit(Request $request, Import $import, DeliveryPlanImporter $importer): JsonResponse
    {
        abort_unless($import->dry_run && $import->status === ImportStatus::Completed, Response::HTTP_UNPROCESSABLE_ENTITY, 'Only a completed dry run can be imported.');
        abort_unless(Storage::disk(config('shipments.imports.disk'))->exists($import->file_path), Response::HTTP_UNPROCESSABLE_ENTITY, 'The checked file is no longer stored. Upload it again.');

        $run = $importer->importChecked($import, $request->user());
        $status = $run->status === ImportStatus::Failed ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_CREATED;

        return (new ImportResource($run->load('user')))->withReport()->response()->setStatusCode($status);
    }
}
