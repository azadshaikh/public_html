<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class AlertContainer extends Component
{
    public Collection $messages;

    public array $preparedMessages;

    public bool $hasValidationErrors;

    public ?string $validationSummary;

    public array $validationMessages;

    public string $containerClasses;

    /**
     * Create a new component instance.
     */
    public function __construct(
        public array $fieldLabels = [],
        public bool $showValidationErrors = true,
        public bool $showFlashMessages = true,
        public string $position = 'top',
        public bool $showIcon = true,
        public bool $dismissible = true,
        public string $containerClass = '',
        public int $autoHide = 0,
        public string $containerId = 'alert-container',
    ) {
        $this->containerClasses = trim(sprintf('alert-container %s %s', $position, $containerClass));

        $this->messages = $this->collectFlashMessages();
        $this->preparedMessages = $this->prepareAllMessages();

        // Initially check if validation errors exist
        $hasErrorsInSession = $showValidationErrors && session()->has('errors');

        if ($hasErrorsInSession) {
            $this->prepareValidationMessages();
            // After filtering, hasValidationErrors reflects if we have errors to show
        } else {
            $this->hasValidationErrors = false;
            $this->validationSummary = null;
            $this->validationMessages = [];
        }
    }

    /**
     * Get alert configuration for given type.
     */
    public function getAlertConfig(string $type): array
    {
        $configs = [
            'success' => [
                'class' => 'alert-success',
                'icon' => 'ri-checkbox-circle-fill',
                'title_default' => 'Success!',
            ],
            'error' => [
                'class' => 'alert-danger',
                'icon' => 'ri-error-warning-fill',
                'title_default' => 'Error!',
            ],
            'danger' => [
                'class' => 'alert-danger',
                'icon' => 'ri-error-warning-fill',
                'title_default' => 'Error!',
            ],
            'warning' => [
                'class' => 'alert-warning',
                'icon' => 'ri-error-warning-fill',
                'title_default' => 'Warning!',
            ],
            'info' => [
                'class' => 'alert-info',
                'icon' => 'ri-information-fill',
                'title_default' => 'Information',
            ],
        ];

        return $configs[$type] ?? $configs['info'];
    }

    /**
     * Prepare message data for rendering.
     */
    public function prepareMessageData(string $type, mixed $message): array
    {
        $config = $this->getAlertConfig($type);
        $isArray = is_array($message);
        $messageData = $isArray ? $message : ['message' => $message];

        return [
            'config' => $config,
            'title' => $messageData['title'] ?? $config['title_default'],
            'content' => $messageData['message'] ?? '',
            'html' => $messageData['html'] ?? '',
            'icon' => $messageData['icon'] ?? ($this->showIcon ? $config['icon'] : ''),
            'list' => $messageData['list'] ?? [],
            'data' => $messageData['data'] ?? [],
            'actions' => $messageData['actions'] ?? [],
            'alertId' => 'alert-'.$type.'-'.uniqid(),
        ];
    }

    /**
     * Check if component should render.
     */
    public function shouldRender(): bool
    {
        if ($this->messages->isNotEmpty()) {
            return true;
        }

        return $this->hasValidationErrors;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.alert-container');
    }

    /**
     * Prepare all messages for rendering.
     */
    protected function prepareAllMessages(): array
    {
        $prepared = [];

        foreach ($this->messages as $type => $message) {
            $prepared[$type] = $this->prepareMessageData($type, $message);
        }

        return $prepared;
    }

    /**
     * Collect all flash messages from session.
     */
    protected function collectFlashMessages(): Collection
    {
        if (! $this->showFlashMessages) {
            return collect();
        }

        $flashTypes = ['success', 'error', 'danger', 'warning', 'info'];

        return collect($flashTypes)
            ->mapWithKeys(function ($type): array {
                $message = session($type);

                return $message ? [$type => $message] : [];
            })
            ->filter();
    }

    /**
     * Prepare validation error messages with friendly field labels.
     */
    protected function prepareValidationMessages(): void
    {
        $errors = session('errors');

        if (! $errors || ! $errors->any()) {
            $this->validationSummary = null;
            $this->validationMessages = [];

            return;
        }

        $errorFields = array_keys($errors->toArray());

        // Only include errors for fields that are defined in fieldLabels
        // If fieldLabels is empty, show all errors (backward compatible)
        $fieldsToShow = $this->fieldLabels === []
            ? $errorFields
            : array_intersect($errorFields, array_keys($this->fieldLabels));

        // If no matching fields after filtering, don't show validation errors
        if ($fieldsToShow === []) {
            $this->hasValidationErrors = false;
            $this->validationSummary = null;
            $this->validationMessages = [];

            return;
        }

        // We have errors to show
        $this->hasValidationErrors = true;

        $friendlyFieldNames = collect($fieldsToShow)
            ->map(fn (string $field) => $this->fieldLabels[$field] ?? $this->formatFieldName($field))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $fieldCount = count($friendlyFieldNames);

        $this->validationSummary = match (true) {
            $fieldCount === 1 => sprintf('Please check the %s field.', $friendlyFieldNames[0]),
            $fieldCount > 1 => 'Please check the following fields: '.implode(', ', $friendlyFieldNames).'.',
            default => 'Please review the highlighted fields and try again.',
        };

        $this->validationMessages = [];

        foreach ($errors->getMessages() as $field => $fieldMessages) {
            // Only show errors for fields in our fieldLabels
            if ($this->fieldLabels !== [] && ! array_key_exists($field, $this->fieldLabels)) {
                continue;
            }

            $label = $this->fieldLabels[$field] ?? $this->formatFieldName($field);

            foreach ($fieldMessages as $message) {
                $this->validationMessages[] = [
                    'label' => $label,
                    'message' => $message,
                ];
            }
        }
    }

    /**
     * Format field name to human-readable label.
     */
    protected function formatFieldName(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }
}
