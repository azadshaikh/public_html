<?php

namespace Modules\Platform\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Carbon\Carbon;
use Modules\Platform\Definitions\TldDefinition;

/**
 * @property int $id
 * @property bool|int|string|null $status
 * @property bool|int|string|null $is_suggested
 * @property float|int|string|null $price
 * @property float|int|string|null $sale_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TldResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new TldDefinition;
    }

    protected function customFields(): array
    {
        $active = (bool) $this->status;
        $suggested = (bool) $this->is_suggested;

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'price' => $this->price !== null ? number_format((float) $this->price, 2) : '-',
            'sale_price' => $this->sale_price !== null ? number_format((float) $this->sale_price, 2) : '-',

            'status' => $active,
            'status_label' => $active ? 'Active' : 'Inactive',
            'status_class' => $active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger',

            'is_suggested' => $suggested,
            'is_suggested_label' => $suggested ? 'Yes' : 'No',
            'is_suggested_class' => $suggested ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary',

            'created_at' => app_date_time_format($this->created_at, 'date'),
            'updated_at' => app_date_time_format($this->updated_at, 'date'),
        ];
    }
}
