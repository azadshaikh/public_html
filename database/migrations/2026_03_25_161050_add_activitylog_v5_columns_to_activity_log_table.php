<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->json('attribute_changes')->nullable()->after('causer_id');
            $table->dropColumn('batch_uuid');
        });

        DB::table('activity_log')
            ->select(['id', 'properties'])
            ->whereNotNull('properties')
            ->orderBy('id')
            ->chunkById(100, function ($activities): void {
                foreach ($activities as $activity) {
                    $properties = json_decode((string) $activity->properties, true);

                    if (! is_array($properties)) {
                        continue;
                    }

                    $attributeChanges = array_intersect_key($properties, array_flip(['attributes', 'old']));
                    $remainingProperties = array_diff_key($properties, array_flip(['attributes', 'old']));

                    DB::table('activity_log')
                        ->where('id', $activity->id)
                        ->update([
                            'attribute_changes' => $attributeChanges === [] ? null : json_encode($attributeChanges, JSON_THROW_ON_ERROR),
                            'properties' => $remainingProperties === [] ? null : json_encode($remainingProperties, JSON_THROW_ON_ERROR),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('activity_log')
            ->select(['id', 'properties', 'attribute_changes'])
            ->whereNotNull('attribute_changes')
            ->orderBy('id')
            ->chunkById(100, function ($activities): void {
                foreach ($activities as $activity) {
                    $properties = json_decode((string) ($activity->properties ?? 'null'), true);
                    $attributeChanges = json_decode((string) $activity->attribute_changes, true);

                    $mergedProperties = array_merge(
                        is_array($properties) ? $properties : [],
                        is_array($attributeChanges) ? $attributeChanges : [],
                    );

                    DB::table('activity_log')
                        ->where('id', $activity->id)
                        ->update([
                            'properties' => $mergedProperties === [] ? null : json_encode($mergedProperties, JSON_THROW_ON_ERROR),
                        ]);
                }
            });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->uuid('batch_uuid')->nullable()->after('properties');
            $table->dropColumn('attribute_changes');
        });
    }
};
