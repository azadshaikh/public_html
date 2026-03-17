<?php

namespace Modules\Platform\Http\Controllers;

use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Platform\Definitions\TldDefinition;
use Modules\Platform\Models\Tld;
use Modules\Platform\Services\TldService;

class TldController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly TldService $tldService
    ) {}

    public static function middleware(): array
    {
        return (new TldDefinition)->getMiddleware();
    }

    protected function service(): TldService
    {
        return $this->tldService;
    }

    protected function inertiaPage(): string
    {
        return 'platform/tlds';
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Tld $tld */
        $tld = $model;

        return [
            'initialValues' => [
                'tld' => (string) ($tld->tld ?? ''),
                'whois_server' => (string) ($tld->whois_server ?? ''),
                'pattern' => (string) ($tld->pattern ?? ''),
                'price' => $tld->price !== null ? (string) $tld->price : '',
                'sale_price' => $tld->sale_price !== null ? (string) $tld->sale_price : '',
                'affiliate_link' => (string) ($tld->affiliate_link ?? ''),
                'status' => $tld->exists ? (bool) $tld->status : true,
                'is_main' => (bool) ($tld->is_main ?? false),
                'is_suggested' => (bool) ($tld->is_suggested ?? false),
                'tld_order' => $tld->tld_order !== null ? (string) $tld->tld_order : '0',
            ],
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Tld $tld */
        $tld = $model;

        return [
            'id' => $tld->getKey(),
            'tld' => $tld->tld,
        ];
    }

    protected function transformModelForShow(Model $model): array
    {
        /** @var Tld $tld */
        $tld = $model;

        return [
            'id' => $tld->getKey(),
            'tld' => $tld->tld,
            'whois_server' => $tld->whois_server,
            'pattern' => $tld->pattern,
            'price' => $tld->price !== null ? number_format((float) $tld->price, 2) : null,
            'sale_price' => $tld->sale_price !== null ? number_format((float) $tld->sale_price, 2) : null,
            'affiliate_link' => $tld->affiliate_link,
            'status' => (bool) $tld->status,
            'status_label' => $tld->status ? 'Active' : 'Inactive',
            'is_main' => (bool) $tld->is_main,
            'is_suggested' => (bool) $tld->is_suggested,
            'tld_order' => $tld->tld_order,
            'created_at' => app_date_time_format($tld->created_at, 'datetime'),
            'updated_at' => app_date_time_format($tld->updated_at, 'datetime'),
        ];
    }

    protected function getShowViewData(Model $model): array
    {
        /** @var Tld $tld */
        $tld = $model;

        $activities = ActivityLog::query()
            ->forModel(Tld::class, $tld->id)
            ->with('causer')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return [
            'activities' => $activities->map(fn ($activity): array => [
                'id' => $activity->getKey(),
                'description' => (string) ($activity->description ?? $activity->message ?? 'Activity recorded'),
                'created_at' => app_date_time_format($activity->created_at, 'datetime'),
                'causer_name' => $activity->causer?->name ?? $activity->causer?->first_name ?? null,
            ])->values()->all(),
        ];
    }
}
