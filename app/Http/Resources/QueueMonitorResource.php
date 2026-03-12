<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Definitions\QueueMonitorDefinition;
use App\Enums\MonitorStatus;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;

class QueueMonitorResource extends ScaffoldResource
{
    // ================================================================
    // SCAFFOLD WIRING
    // ================================================================

    protected function definition(): ScaffoldDefinition
    {
        return new QueueMonitorDefinition;
    }

    // ================================================================
    // CUSTOM FIELD TRANSFORMATIONS
    // ================================================================

    protected function customFields(): array
    {
        $statusLabels = MonitorStatus::toNamedArray();

        $statusClasses = [
            MonitorStatus::RUNNING => 'bg-primary',
            MonitorStatus::SUCCEEDED => 'bg-success',
            MonitorStatus::FAILED => 'bg-danger',
            MonitorStatus::STALE => 'bg-warning',
            MonitorStatus::QUEUED => 'bg-secondary',
        ];

        return [
            'name' => ($label = $this->resource->metadata['_label'] ?? null)
                ? ($this->resource->getBasename() ?? '—').' · '.$label
                : ($this->resource->getBasename() ?? '—'),
            'duration' => $this->resource->getElapsedInterval()->format('%H:%I:%S'),
            'wait' => $this->resource->queued_at !== null && $this->resource->started_at !== null
                ? $this->resource->queued_at->diffAsCarbonInterval($this->resource->started_at)->format('%H:%I:%S')
                : '—',
            'started_at' => $this->resource->started_at?->diffForHumans() ?? '—',
            'status_label' => $statusLabels[$this->resource->status] ?? 'Unknown',
            'status_class' => $statusClasses[$this->resource->status] ?? 'bg-secondary',
            'exception_message' => $this->resource->status !== MonitorStatus::SUCCEEDED
                ? $this->resource->exception_message
                : null,
        ];
    }

    // ================================================================
    // ROW ACTIONS — conditional retry + finished-only delete
    // ================================================================

    protected function getActions(): array
    {
        $actions = parent::getActions();

        // Delete is only relevant for finished jobs
        if (isset($actions['delete']) && ! $this->resource->isFinished()) {
            unset($actions['delete']);
        }

        // Retry is conditional and not in the Definition (per-row only)
        if (config('queue-monitor.ui.allow_retry') && $this->resource->canBeRetried()) {
            $actions['retry'] = [
                'url' => route('app.masters.queue-monitor.retry', $this->resource->id),
                'label' => 'Retry',
                'icon' => 'ri-refresh-line',
                'method' => 'PATCH',
                'variant' => 'primary',
            ];
        }

        return $actions;
    }
}
