<?php

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
        Schema::create('revisions_data', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->comment('Stores the id of the revisions table.')->constrained('revisions');
            $table->string('field_key', 125)->nullable()->comment('Stores the key of the table.');
            $table->longtext('old_value')->nullable()->comment('Stores the old value of the field.');
            $table->longtext('new_value')->nullable()->comment('Stores the new value of the field.');
            $table->integer('created_by')->default(1);
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revisions_data');
    }
};
