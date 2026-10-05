<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('url', 2048);
            $table->text('secret');
            $table->jsonb('events');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['company_id', 'url']);
            $table->unique(['id', 'company_id']);
        });

        $this->check('webhook_endpoints', 'webhook_endpoints_events_check', "jsonb_typeof(events) = 'array' AND jsonb_array_length(events) > 0");

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
            $table->unsignedBigInteger('webhook_endpoint_id');
            $table->uuid('event_id');
            $table->uuid('change_group')->nullable();
            $table->string('event_type', 40);
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->smallInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->smallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestampsTz();

            $table->foreign(['webhook_endpoint_id', 'company_id'])->references(['id', 'company_id'])->on('webhook_endpoints')->cascadeOnDelete();
            $table->unique(['webhook_endpoint_id', 'event_id']);
            $table->index(['webhook_endpoint_id', 'created_at']);
            $table->index(['status', 'next_attempt_at']);
            $table->index('change_group');
        });

        DB::statement('ALTER TABLE webhook_deliveries ADD CONSTRAINT webhook_deliveries_shipment_foreign FOREIGN KEY (shipment_id, company_id) REFERENCES shipments (id, company_id) ON DELETE SET NULL (shipment_id)');
        $this->check('webhook_deliveries', 'webhook_deliveries_event_type_check', "event_type IN ('shipment.status_changed', 'shipment.dates_changed', 'webhook.test')");
        $this->check('webhook_deliveries', 'webhook_deliveries_status_check', "status IN ('pending', 'succeeded', 'failed')");
        $this->check('webhook_deliveries', 'webhook_deliveries_attempts_check', 'attempts >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }

    private function check(string $table, string $name, string $expression): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
    }
};
