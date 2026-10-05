<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUSES = "'planned', 'in_transit', 'arrived', 'in_storage', 'delivered'";

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('reference', 20);
            $table->string('external_reference', 100)->nullable();
            $table->string('source_key')->nullable();
            $table->string('shipping_method', 10);
            $table->string('shipping_line', 100)->nullable();
            $table->string('origin_port', 100)->nullable();
            $table->string('destination_port', 100);
            $table->string('incoterm', 10)->nullable();
            $table->date('etd')->nullable();
            $table->date('etd_original')->nullable();
            $table->integer('etd_delay_days')->default(0);
            $table->date('eta')->nullable();
            $table->date('eta_original')->nullable();
            $table->integer('eta_delay_days')->default(0);
            $table->string('status', 20)->default('planned');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['company_id', 'reference']);
            $table->unique(['company_id', 'external_reference']);
            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'etd']);
            $table->index(['company_id', 'eta']);
            $table->index(['company_id', 'destination_port']);
            $table->index(['company_id', 'updated_at']);
            $table->index(['company_id', 'source_key']);
        });

        $this->check('shipments', 'shipments_status_check', 'status IN ('.self::STATUSES.')');
        $this->check('shipments', 'shipments_method_check', "shipping_method IN ('sea', 'air', 'road')");
        $this->check('shipments', 'shipments_dates_check', 'etd IS NULL OR eta IS NULL OR etd <= eta');

        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
            $table->unsignedBigInteger('shipment_id');
            $table->string('container_no', 20);
            $table->string('seal_no', 50)->nullable();
            $table->string('container_type', 10)->nullable();
            $table->timestampsTz();

            $table->foreign(['shipment_id', 'company_id'])->references(['id', 'company_id'])->on('shipments')->cascadeOnDelete();
            $table->unique(['shipment_id', 'container_no']);
            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'container_no']);
        });

        $this->trigram('containers', 'container_no');

        Schema::create('cargo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
            $table->unsignedBigInteger('container_id');
            $table->string('package_no', 50);

            $table->string('proforma_invoice_no', 50)->nullable();
            $table->string('exporter_reference', 50)->nullable();
            $table->string('tag_number', 50)->nullable();
            $table->string('tag_key', 50)->nullable();
            $table->string('product_code', 50)->nullable();
            $table->string('product_description')->nullable();
            $table->string('product_category', 30)->default('other');
            $table->string('net_type', 50)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_reference', 100)->nullable();
            $table->string('customer_po', 100)->nullable();
            $table->string('storage_facility', 100)->nullable();
            $table->decimal('quantity', 12, 3)->nullable();
            $table->string('unit', 10)->nullable();
            $table->decimal('net_weight_kg', 12, 3)->nullable();
            $table->decimal('gross_weight_kg', 12, 3)->nullable();

            $table->string('status', 20)->default('planned');
            $table->string('raw_status', 50)->nullable();
            $table->date('customer_delivery_date')->nullable();
            $table->date('customer_delivery_date_original')->nullable();
            $table->integer('delivery_delay_days')->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->foreign(['container_id', 'company_id'])->references(['id', 'company_id'])->on('containers')->cascadeOnDelete();
            $table->unique(['company_id', 'package_no']);
            $table->unique(['id', 'company_id']);
            $table->index('container_id');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_delivery_date']);
            $table->index(['company_id', 'proforma_invoice_no']);
            $table->index(['company_id', 'exporter_reference']);
            $table->index(['company_id', 'product_code']);
        });

        $this->check('cargo_items', 'cargo_items_status_check', 'status IN ('.self::STATUSES.')');
        $this->check('cargo_items', 'cargo_items_category_check', "product_category IN ('net_pen', 'dead_fish_collector', 'other')");
        $this->check('cargo_items', 'cargo_items_amounts_check', 'quantity >= 0 AND net_weight_kg >= 0 AND gross_weight_kg >= 0');

        foreach (['customer_name', 'tag_key', 'product_description', 'proforma_invoice_no'] as $column) {
            $this->trigram('cargo_items', $column);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_items');
        Schema::dropIfExists('containers');
        Schema::dropIfExists('shipments');
    }

    private function check(string $table, string $name, string $expression): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
    }

    private function trigram(string $table, string $column): void
    {
        DB::statement("CREATE INDEX {$table}_{$column}_trgm ON {$table} USING gin ({$column} gin_trgm_ops)");
    }
};
