<?php

namespace Tests;

use App\Enums\CargoStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\Shipments\ChangeContext;
use App\Services\Shipments\ShipmentService;
use App\Services\Shipments\StatusService;
use App\Support\ContainerNumber;
use App\Support\Dates;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $database = (string) $app['config']->get('database.connections.'.$app['config']->get('database.default').'.database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests on database [{$database}]: use a *_test database.");
        }

        return $app;
    }

    protected function user(?Company $company = null, UserRole $role = UserRole::CompanyUser): User
    {
        return User::factory()->for($company ?? Company::factory()->create())->create(['role' => $role]);
    }

    protected function signIn(?User $user = null, array $abilities = ['write']): User
    {
        $user ??= $this->user();
        Sanctum::actingAs($user, $abilities);

        return $user;
    }

    protected function createShipment(Company $company, array $attributes = [], ?array $containers = null, ?User $user = null): Shipment
    {
        $user ??= User::factory()->for($company)->create();

        return app(TenantContext::class)->run($company, fn () => app(ShipmentService::class)->create([
            'shipping_method' => 'sea',
            'shipping_line' => 'TRANSSEA AS',
            'destination_port' => 'ORKANGER',
            'incoterm' => 'CIF',
            'etd' => '2026-06-27',
            'eta' => '2026-08-05',
            ...$attributes,
            'containers' => $containers ?? [
                ['container_no' => 'HLBU8073625', 'items' => [
                    ['package_no' => 'WENA190219', 'tag_number' => '2604101', 'customer_name' => 'Fisk AS Mid', 'proforma_invoice_no' => '862600369'],
                    ['package_no' => 'WENA190958', 'tag_number' => '2604102', 'customer_name' => 'Fisk AS Mid', 'proforma_invoice_no' => '862600369'],
                ]],
                ['container_no' => 'HLBU8077765', 'items' => [
                    ['package_no' => 'WENA191125', 'tag_number' => '2604106', 'customer_name' => 'Fisk ASA North', 'proforma_invoice_no' => '862600380'],
                ]],
            ],
        ], ChangeContext::byUser($user, 'Test shipment')));
    }

    protected function advance(Shipment $shipment, CargoStatus $target, string $on = '2026-08-10'): Shipment
    {
        $company = $shipment->company;
        $user = User::factory()->for($company)->create();

        app(TenantContext::class)->run($company, function () use ($shipment, $target, $on, $user): void {
            foreach (CargoStatus::Planned->pathTo($target) ?? [] as $step) {
                $items = $shipment->cargoQuery()->get();
                $day = $step === CargoStatus::InTransit ? $shipment->etd->toDateString() : $on;
                app(StatusService::class)->change(
                    $shipment->refresh(), $items, $step, ChangeContext::byUser($user, null, Dates::eventTime($day)),
                );
            }
        });

        return $shipment->refresh();
    }

    protected function webhook(Company $company, array $attributes = []): WebhookEndpoint
    {
        return WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => "https://suppli.example.com/hooks/{$company->id}",
            'secret' => 'whsec_test_secret',
            'events' => ['shipment.status_changed', 'shipment.dates_changed'],
            ...$attributes,
        ]);
    }

    protected function validContainerNo(string $owner, string $serial): string
    {
        return $owner.$serial.ContainerNumber::iso6346CheckDigit($owner.$serial);
    }
}
