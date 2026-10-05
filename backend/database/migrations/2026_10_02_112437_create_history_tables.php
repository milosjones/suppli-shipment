<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUSES = "'planned', 'in_transit', 'arrived', 'in_storage', 'delivered'";

    private const SOURCES = "'manual', 'api', 'import', 'provider', 'system'";

    public function up(): void
    {
        Schema::create('shipment_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('container_id');
            $table->unsignedBigInteger('cargo_item_id');
            $table->uuid('change_group');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->boolean('is_correction')->default(false);
            $table->text('reason')->nullable();
            $table->string('source', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['shipment_id', 'company_id'])->references(['id', 'company_id'])->on('shipments')->cascadeOnDelete();
            $table->foreign(['container_id', 'company_id'])->references(['id', 'company_id'])->on('containers')->cascadeOnDelete();
            $table->foreign(['cargo_item_id', 'company_id'])->references(['id', 'company_id'])->on('cargo_items')->cascadeOnDelete();
            $table->index(['company_id', 'shipment_id', 'occurred_at']);
            $table->index(['cargo_item_id', 'occurred_at']);
            $table->index('change_group');
        });

        $this->check('shipment_status_changes', 'status_changes_from_check', 'from_status IS NULL OR from_status IN ('.self::STATUSES.')');
        $this->check('shipment_status_changes', 'status_changes_to_check', 'to_status IN ('.self::STATUSES.')');
        $this->check('shipment_status_changes', 'status_changes_changed_check', 'from_status IS DISTINCT FROM to_status');
        $this->check('shipment_status_changes', 'status_changes_correction_check', 'NOT is_correction OR reason IS NOT NULL');
        $this->check('shipment_status_changes', 'status_changes_source_check', 'source IN ('.self::SOURCES.')');

        Schema::create('shipment_date_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('container_id')->nullable();
            $table->unsignedBigInteger('cargo_item_id')->nullable();
            $table->uuid('change_group');
            $table->string('field', 30);
            $table->date('old_date')->nullable();
            $table->date('new_date')->nullable();
            $table->integer('shift_days')->nullable();
            $table->text('reason');
            $table->string('source', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['shipment_id', 'company_id'])->references(['id', 'company_id'])->on('shipments')->cascadeOnDelete();
            $table->foreign(['container_id', 'company_id'])->references(['id', 'company_id'])->on('containers')->cascadeOnDelete();
            $table->foreign(['cargo_item_id', 'company_id'])->references(['id', 'company_id'])->on('cargo_items')->cascadeOnDelete();
            $table->index(['company_id', 'shipment_id', 'created_at']);
            $table->index('cargo_item_id');
            $table->index('change_group');
        });

        $this->check('shipment_date_changes', 'date_changes_field_check', "field IN ('etd', 'eta', 'customer_delivery_date')");
        $this->check('shipment_date_changes', 'date_changes_scope_check', "(field = 'customer_delivery_date') = (cargo_item_id IS NOT NULL)");
        $this->check('shipment_date_changes', 'date_changes_changed_check', 'old_date IS DISTINCT FROM new_date');
        $this->check('shipment_date_changes', 'date_changes_source_check', 'source IN ('.self::SOURCES.')');
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_date_changes');
        Schema::dropIfExists('shipment_status_changes');
    }

    private function check(string $table, string $name, string $expression): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
    }
};
