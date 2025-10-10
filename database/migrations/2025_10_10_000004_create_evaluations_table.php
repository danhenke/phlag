<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->foreignUuid('environment_id')
                ->constrained('environments')
                ->cascadeOnDelete();
            $table->foreignUuid('flag_id')
                ->constrained('flags')
                ->cascadeOnDelete();
            $table->string('flag_key');
            $table->string('variant')->nullable();
            $table->string('evaluation_reason')->nullable();
            $table->string('user_identifier')->nullable();
            $table->jsonb('request_context')->nullable();
            $table->jsonb('evaluation_payload')->nullable();
            $table->timestampTz('evaluated_at')->useCurrent();
            $table->timestampsTz();

            $table->index(['project_id', 'environment_id', 'flag_id'], 'evaluations_resource_lookup');
            $table->index(['flag_key', 'evaluated_at']);
            $table->index('user_identifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
