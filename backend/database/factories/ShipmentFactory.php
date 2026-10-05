<?php

namespace Database\Factories;

use App\Enums\CargoStatus;
use App\Enums\ShippingMethod;
use App\Models\CargoItem;
use App\Models\Company;
use App\Models\Container;
use App\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        $etd = CarbonImmutable::instance(fake()->dateTimeBetween('-60 days', '+30 days'))->startOfDay();

        return [
            'company_id' => Company::factory(),
            'reference' => fake()->unique()->bothify('SHP-T####'),
            'shipping_method' => ShippingMethod::Sea,
            'shipping_line' => 'TRANSSEA AS',
            'origin_port' => 'NHAVA SHEVA',
            'destination_port' => fake()->randomElement(['BERGEN', 'MÅLØY', 'ORKANGER', 'SALTEN']),
            'incoterm' => 'CIF',
            'etd' => $etd,
            'eta' => $etd->addDays(40),
            'status' => CargoStatus::Planned,
        ];
    }

    public function withCargo(int $containers = 1, int $itemsPerContainer = 2, CargoStatus $status = CargoStatus::Planned): static
    {
        return $this->state(['status' => $status])->afterCreating(function (Shipment $shipment) use ($containers, $itemsPerContainer, $status): void {
            Container::factory()
                ->count($containers)
                ->for($shipment)
                ->has(CargoItem::factory()->count($itemsPerContainer)->state(['status' => $status]), 'cargoItems')
                ->create();
        });
    }
}
