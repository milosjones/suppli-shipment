<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Support\ContainerNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContainerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'company_id' => fn (array $attributes) => Shipment::withoutGlobalScopes()->find($attributes['shipment_id'])->company_id,
            'container_no' => self::isoNumber(),
            'container_type' => '45UT',
        ];
    }

    public static function isoNumber(): string
    {
        $first10 = fake()->randomElement(['HLBU', 'QIBU', 'MEDU', 'HAMU', 'TRHU']).fake()->unique()->numerify('######');

        return $first10.ContainerNumber::iso6346CheckDigit($first10);
    }
}
