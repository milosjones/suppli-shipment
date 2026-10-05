<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->char('file_hash', 64);
            $table->string('status', 20)->default('processing');
            $table->boolean('dry_run')->default(false);
            $table->integer('total_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            $table->integer('skipped_rows')->default(0);
            $table->integer('rejected_rows')->default(0);
            $table->integer('blank_rows')->default(0);
            $table->integer('warning_count')->default(0);
            $table->integer('shipments_created')->default(0);
            $table->integer('containers_created')->default(0);
            $table->jsonb('normalisations')->nullable();
            $table->jsonb('issues')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at', 3)->nullable();
            $table->timestampTz('finished_at', 3)->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'file_hash']);
        });

        DB::statement("ALTER TABLE imports ADD CONSTRAINT imports_status_check CHECK (status IN ('processing', 'completed', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
