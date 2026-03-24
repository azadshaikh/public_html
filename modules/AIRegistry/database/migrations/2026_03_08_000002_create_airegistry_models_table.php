<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airegistry_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('airegistry_providers')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('context_window')->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->decimal('input_cost_per_1m', 10, 4)->nullable();
            $table->decimal('output_cost_per_1m', 10, 4)->nullable();
            $table->json('input_modalities')->nullable();
            $table->json('output_modalities')->nullable();
            $table->string('tokenizer')->nullable();
            $table->boolean('is_moderated')->nullable();
            $table->json('supported_parameters')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('categories')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider_id', 'slug']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airegistry_models');
    }
};
