<?php

namespace App\Console\Commands;

use App\Enums\CargoStatus;
use App\Models\Company;
use App\Models\Shipment;
use App\Support\Tenancy\TenantContext;
use App\Tracking\TrackingSyncService;
use Illuminate\Console\Command;

class SyncTracking extends Command
{
    protected $signature = 'tracking:sync
        {--company= : Only this company id}
        {--shipment= : Only this shipment reference}';

    protected $description = 'Pull status and date updates from the tracking providers (schedule this, e.g. hourly)';

    public function handle(TrackingSyncService $sync, TenantContext $tenant): int
    {
        $companies = Company::query()
            ->where('is_active', true)
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey((int) $id))
            ->get();

        foreach ($companies as $company) {
            $tenant->run($company, function () use ($sync, $company): void {
                $shipments = Shipment::query()
                    ->where('status', '!=', CargoStatus::Delivered)
                    ->when($this->option('shipment'), fn ($query, $reference) => $query->where('reference', $reference))
                    ->get();

                foreach ($shipments as $shipment) {
                    $result = $sync->sync($shipment);

                    if ($result->applied !== [] || $result->skipped !== []) {
                        $this->line(sprintf(
                            '%s %s: %d applied, %d skipped',
                            $company->name,
                            $shipment->reference,
                            count($result->applied),
                            count($result->skipped),
                        ));
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}
