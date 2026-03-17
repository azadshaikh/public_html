<?php

namespace Modules\Platform\Models\Presenters;

trait ServerPresenter
{
    protected function getStatusBadgeAttribute(): string
    {
        $status = config('platform.server_statuses.'.$this->status);

        if ($status) {
            return '<span class="badge bg-'.$status['color'].'-subtle text-'.$status['color'].'">'.$status['label'].'</span>';
        }

        return '<span class="badge bg-primary-subtle text-primary">Status:'.ucwords($this->status).'</span>';
    }

    protected function getMonitoringBadgeAttribute(): string
    {
        return $this->monitor
            ? '<span class="badge bg-success-subtle text-success">Enabled</span>'
            : '<span class="badge bg-danger-subtle text-danger">Disabled</span>';
    }

    protected function getServerInfoAttribute(): string
    {
        $info = [];

        if ($this->ip) {
            $info[] = 'IP: '.$this->ip;
        }

        if ($this->port) {
            $info[] = 'Port: '.$this->port;
        }

        if ($this->server_os) {
            $info[] = 'OS: '.$this->server_os;
        }

        return implode(' | ', $info);
    }

    protected function getResourceInfoAttribute(): string
    {
        $info = [];

        if ($this->server_cpu) {
            $info[] = 'CPU: '.$this->server_cpu;
        }

        if ($this->server_ccore) {
            $info[] = 'Cores: '.$this->server_ccore;
        }

        if ($this->server_ram) {
            $info[] = sprintf('RAM: %sMB', $this->server_ram);
        }

        if ($this->server_storage) {
            $info[] = sprintf('Storage: %sGB', $this->server_storage);
        }

        return implode(' | ', $info);
    }
}
