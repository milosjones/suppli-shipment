<?php

use App\Http\Controllers\Api\V1\Admin\CompanyController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\ApiClientController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CargoItemController;
use App\Http\Controllers\Api\V1\CargoItemReferenceController;
use App\Http\Controllers\Api\V1\ContainerController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ShipmentController;
use App\Http\Controllers\Api\V1\ShipmentDateController;
use App\Http\Controllers\Api\V1\ShipmentHistoryController;
use App\Http\Controllers\Api\V1\ShipmentStatusController;
use App\Http\Controllers\Api\V1\TrackingSyncController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WebhookDeliveryController;
use App\Http\Controllers\Api\V1\WebhookEndpointController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::put('auth/password', [AuthController::class, 'password']);

        Route::middleware('can:manage-companies')->prefix('admin')->group(function (): void {
            Route::get('companies', [CompanyController::class, 'index']);
            Route::post('companies', [CompanyController::class, 'store']);
            Route::patch('companies/{company}', [CompanyController::class, 'update']);
            Route::get('users', [AdminUserController::class, 'index']);
            Route::post('users', [AdminUserController::class, 'store']);
            Route::patch('users/{user}', [AdminUserController::class, 'update']);
        });

        Route::middleware('tenant')->group(function (): void {
            Route::get('meta', MetaController::class);

            Route::get('shipments', [ShipmentController::class, 'index']);
            Route::get('shipments/{shipment}', [ShipmentController::class, 'show']);
            Route::get('shipments/{shipment}/history', ShipmentHistoryController::class);
            Route::get('lookup', LookupController::class);
            Route::get('reports/upcoming-deliveries', [ReportController::class, 'upcomingDeliveries']);
            Route::get('reports/status-overview', [ReportController::class, 'statusOverview']);
            Route::get('reports/delayed', [ReportController::class, 'delayed']);
            Route::get('imports', [ImportController::class, 'index']);
            Route::get('imports/{import}', [ImportController::class, 'show']);

            Route::middleware('abilities:write')->group(function (): void {
                Route::post('shipments', [ShipmentController::class, 'store']);
                Route::patch('shipments/{shipment}', [ShipmentController::class, 'update']);
                Route::delete('shipments/{shipment}', [ShipmentController::class, 'destroy']);
                Route::post('shipments/{shipment}/status', ShipmentStatusController::class);
                Route::post('shipments/{shipment}/dates', ShipmentDateController::class);
                Route::post('shipments/{shipment}/tracking-sync', TrackingSyncController::class);
                Route::post('shipments/{shipment}/containers', [ContainerController::class, 'store']);
                Route::patch('containers/{container}', [ContainerController::class, 'update']);
                Route::delete('containers/{container}', [ContainerController::class, 'destroy']);
                Route::post('containers/{container}/items', [CargoItemController::class, 'store']);
                Route::post('imports', [ImportController::class, 'store']);
                Route::post('imports/{import}/commit', [ImportController::class, 'commit']);
            });

            Route::patch('cargo-items/references', CargoItemReferenceController::class)->middleware('abilities:references:write');

            Route::middleware('can:manage-users')->group(function (): void {
                Route::get('users', [UserController::class, 'index']);
                Route::post('users', [UserController::class, 'store']);
                Route::patch('users/{user}', [UserController::class, 'update']);
                Route::get('api-clients', [ApiClientController::class, 'index']);
                Route::post('api-clients', [ApiClientController::class, 'store']);
                Route::patch('api-clients/{client}', [ApiClientController::class, 'update']);
                Route::post('api-clients/{client}/tokens', [ApiClientController::class, 'storeToken']);
                Route::delete('api-clients/{client}/tokens/{token}', [ApiClientController::class, 'destroyToken'])->whereNumber('token');
                Route::get('webhooks', [WebhookEndpointController::class, 'index']);
                Route::post('webhooks', [WebhookEndpointController::class, 'store']);
                Route::patch('webhooks/{webhook}', [WebhookEndpointController::class, 'update']);
                Route::delete('webhooks/{webhook}', [WebhookEndpointController::class, 'destroy']);
                Route::post('webhooks/{webhook}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret']);
                Route::post('webhooks/{webhook}/test', [WebhookEndpointController::class, 'test']);
                Route::get('webhooks/{webhook}/deliveries', [WebhookEndpointController::class, 'deliveries']);
                Route::post('webhook-deliveries/{delivery}/retry', [WebhookDeliveryController::class, 'retry']);
            });
        });
    });
});
