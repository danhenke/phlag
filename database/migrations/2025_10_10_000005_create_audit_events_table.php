<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();
            $table->foreignUuid('environment_id')
                ->nullable()
                ->constrained('environments')
                ->nullOnDelete();
            $table->foreignUuid('flag_id')
                ->nullable()
                ->constrained('flags')
                ->nullOnDelete();
            $table->string('action');
            $table->string('target_type');
            $table->uuid('target_id')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('actor_identifier')->nullable();
            $table->jsonb('changes')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->timestampsTz();

            $table->index('occurred_at');
            $table->index(['project_id', 'occurred_at']);
            $table->index(['flag_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
