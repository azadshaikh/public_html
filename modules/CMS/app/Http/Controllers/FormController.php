<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\CMS\Definitions\FormDefinition;
use Modules\CMS\Models\Form;
use Modules\CMS\Services\FormService;

class FormController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly FormService $formService
    ) {}

    public static function middleware(): array
    {
        return (new FormDefinition)->getMiddleware();
    }

    protected function service(): FormService
    {
        return $this->formService;
    }

    protected function inertiaPage(): string
    {
        return 'cms/forms';
    }

    protected function getAfterStoreRedirectUrl(Model $model): string
    {
        return route('cms.form.edit', $model);
    }

    public function redirectToEdit(int|string $id): RedirectResponse
    {
        return redirect()->route('cms.form.edit', $id);
    }

    protected function getFormViewData(Model $model): array
    {
        return [
            'initialValues' => $this->buildInitialValues($model),
            'statusOptions' => $this->formService->getStatusOptions(),
            'templateOptions' => $this->formService->getTemplateOptions(),
            'formTypeOptions' => $this->formService->getFormTypeOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Form $model */
        return [
            'id' => $model->getKey(),
            'title' => (string) ($model->title ?? ''),
            'slug' => (string) ($model->slug ?? ''),
            'shortcode' => (string) $model->getRawOriginal('shortcode', ''),
            'submissions_count' => (int) ($model->submissions_count ?? 0),
            'views_count' => (int) ($model->views_count ?? 0),
            'conversion_rate_display' => $model->conversion_rate !== null
                ? rtrim(rtrim(number_format((float) $model->conversion_rate, 1), '0'), '.').'%'
                : '--',
            'published_at_formatted' => $model->published_at
                ? app_date_time_format($model->published_at, 'datetime')
                : null,
            'updated_at_formatted' => $model->updated_at
                ? app_date_time_format($model->updated_at, 'datetime')
                : null,
            'updated_at_human' => $model->updated_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInitialValues(Model $model): array
    {
        /** @var Form $model */
        $confirmationType = $model->confirmations['type'] ?? 'message';

        return [
            'title' => (string) ($model->title ?? ''),
            'slug' => (string) ($model->slug ?? ''),
            'shortcode' => (string) $model->getRawOriginal('shortcode', ''),
            'template' => (string) ($model->template ?? 'default'),
            'form_type' => (string) ($model->form_type ?? 'standard'),
            'html' => (string) ($model->html ?? ''),
            'css' => (string) ($model->css ?? ''),
            'store_in_database' => (bool) ($model->store_in_database ?? true),
            'confirmation_type' => (string) $confirmationType,
            'confirmation_message' => (string) ($model->confirmations['message'] ?? ''),
            'redirect_url' => (string) ($model->confirmations['redirect'] ?? ''),
            'status' => (string) ($model->status ?? 'draft'),
            'is_active' => (bool) ($model->is_active ?? true),
            'published_at' => $model->published_at
                ? $model->published_at->setTimezone(app_localization_timezone())->format('Y-m-d\TH:i')
                : '',
        ];
    }
}
