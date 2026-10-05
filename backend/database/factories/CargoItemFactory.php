<?php

namespace Database\Factories;

use App\Enums\CargoStatus;
use App\Enums\ProductCategory;
use App\Models\Container;
use Illuminate\Database\Eloquent\Factories\Factory;

class CargoItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'container_id' => Container::factory(),
            'company_id' => fn (array $attributes) => Container::withoutGlobalScopes()->find($attributes['container_id'])->company_id,
            'package_no' => fake()->unique()->bothify('WENA######'),
            'proforma_invoice_no' => fake()->numerify('8626#####'),
            'exporter_reference' => fake()->numerify('4426#####'),
            'tag_number' => 'GNP-'.fake()->unique()->numerify('26#####'),
            'product_code' => fake()->numerify('254030#####'),
            'product_description' => 'NP-160STWx1.2+20+15/SACEFob/1.5mm-19.5HM',
            'product_category' => ProductCategory::NetPen,
            'net_type' => 'Net Cage',
            'customer_name' => fake()->randomElement(['Fisk AS West', 'Fisk AS Mid', 'Fisk ASA North']),
            'customer_po' => fake()->bothify('RN26###'),
            'quantity' => 1,
            'unit' => 'PC',
            'net_weight_kg' => 3000,
            'gross_weight_kg' => 3100,
            'status' => CargoStatus::Planned,
        ];
    }

    public function deadFishCollector(): static
    {
        return $this->state([
            'product_description' => 'DFColle-1.6mDia/N6132-16.5/V4PlatN/DblRi',
            'product_category' => ProductCategory::DeadFishCollector,
            'net_type' => 'DFC',
            'net_weight_kg' => 33,
            'gross_weight_kg' => 35,
        ]);
    }
}
