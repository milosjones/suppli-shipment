<?php

namespace Tests\Feature;

use App\Models\CargoItem;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seed_creates_one_company_with_the_imported_plan(): void
    {
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['Garware Technical Fibres AS'], Company::query()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(
            ['superadmin@suppli.test', 'admin@garware.test', 'logistics@garware.test', null],
            User::query()->pluck('email')->all(),
        );
        $this->assertSame(40, Shipment::query()->count());
        $this->assertSame(178, CargoItem::query()->count());
    }
}
