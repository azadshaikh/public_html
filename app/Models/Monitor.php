<?php

namespace App\Models;

use App\Enums\MonitorStatus;
use App\Models\Contracts\MonitorContract;
use Carbon\CarbonInterval;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * @property int $id
 * @property string $job_uuid
 * @property string $job_id
 * @property string|null $name
 * @property string|null $queue
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property string|null $started_at_exact
 * @property Carbon|null $finished_at
 * @property string|null $finished_at_exact
 * @property int $status
 * @property int $attempt
 * @property int|null $progress
 * @property string|null $exception
 * @property string|null $exception_class
 * @property string|null $exception_message
 * @property string|null $data
 * @property bool $retried
 * @property array<string, mixed>|null $metadata
 * @property-read int|null $succeeded
 * @property-read int|null $failed
 * @property-read float|null $avg_duration
 * @property-read int|null $failure_count
 * @property-read int|null $cnt
 *
 * @method static Builder<Monitor>|Monitor whereJob()
 * @method static Builder<Monitor>|Monitor ordered()
 * @method static Builder<Monitor>|Monitor lastHour()
 * @method static Builder<Monitor>|Monitor today()
 * @method static Builder<Monitor>|Monitor failed()
 * @method static Builder<Monitor>|Monitor succeeded()
 *
 * @mixin Model
 * @mixin Builder<Monitor>
 */
class Monitor extends Model implements MonitorContract
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'failed' => 'bool',
        'retried' => 'bool',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'status' => 'int',
        'metadata' => 'array',
    ];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('queue-monitor.table'));

        if ($connection = config('queue-monitor.connection')) {
            $this->setConnection($connection);
        }
    }

    /*
     *--------------------------------------------------------------------------
     * Scopes
     *--------------------------------------------------------------------------
     */
    /**
     * @param  Builder<Monitor>  $query
     * @param  string|int  $jobId
     */
    public function scopeWhereJob(Builder $query, $jobId): void
    {
        $query->where('job_id', $jobId);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query
            ->latest('started_at')
            ->orderBy('started_at_exact', 'desc');
    }

    public function scopeLastHour(Builder $query): void
    {
        $query->where('started_at', '>', Date::now()->subHours(1));
    }

    public function scopeToday(Builder $query): void
    {
        $query->whereRaw('DATE(started_at) = ?', [Date::now()->subHours(1)->format('Y-m-d')]);
    }

    public function scopeFailed(Builder $query): void
    {
        $query->where('status', MonitorStatus::FAILED);
    }

    public function scopeSucceeded(Builder $query): void
    {
        $query->where('status', MonitorStatus::SUCCEEDED);
    }

    /*
     *--------------------------------------------------------------------------
     * Methods
     *--------------------------------------------------------------------------
     */

    public function getStartedAtExact(): ?Carbon
    {
        if ($this->started_at_exact === null) {
            return null;
        }

        return Date::parse($this->started_at_exact);
    }

    public function getFinishedAtExact(): ?Carbon
    {
        if ($this->finished_at_exact === null) {
            return null;
        }

        return Date::parse($this->finished_at_exact);
    }

    /**
     * Get the estimated remaining seconds. This requires a job progress to be set.
     */
    public function getRemainingSeconds(?Carbon $now = null): float
    {
        return $this->getRemainingInterval($now)->totalSeconds;
    }

    public function getRemainingInterval(?Carbon $now = null): CarbonInterval
    {
        if (! $now instanceof Carbon) {
            $now = Date::now();
        }

        if (! $this->progress || $this->started_at === null || $this->isFinished()) {
            return CarbonInterval::seconds(0);
        }

        if (0 === ($timeDiff = $now->getTimestamp() - $this->started_at->getTimestamp())) {
            return CarbonInterval::seconds(0);
        }

        return CarbonInterval::seconds(
            (100 - $this->progress) / ($this->progress / $timeDiff)
        )->cascade();
    }

    /**
     * Get the currently elapsed seconds.
     */
    public function getElapsedSeconds(?Carbon $end = null): float
    {
        return $this->getElapsedInterval($end)->seconds;
    }

    public function getElapsedInterval(?Carbon $end = null): CarbonInterval
    {
        if (! $end instanceof Carbon) {
            $end = $this->getFinishedAtExact() ?? $this->finished_at ?? Date::now();
        }

        $startedAt = $this->getStartedAtExact() ?? $this->started_at;

        if ($startedAt === null) {
            return CarbonInterval::seconds(0);
        }

        return $startedAt->diffAsCarbonInterval($end);
    }

    /**
     * Get any optional data that has been added to the monitor model within the job.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return json_decode((string) $this->data, true) ?? [];
    }

    /**
     * Recreate the exception.
     *
     * @param  bool  $rescue  Wrap the exception recreation to catch exceptions
     */
    public function getException(bool $rescue = true): ?Throwable
    {
        if ($this->exception_class === null) {
            return null;
        }

        if (! $rescue) {
            return new $this->exception_class($this->exception_message);
        }

        try {
            return new $this->exception_class($this->exception_message);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get the base class name of the job.
     */
    public function getBasename(): ?string
    {
        if ($this->name === null) {
            return null;
        }

        return Arr::last(explode('\\', $this->name));
    }

    /**
     * Check if the job is finished.
     */
    public function isFinished(): bool
    {
        if ($this->hasFailed()) {
            return true;
        }

        return $this->finished_at !== null;
    }

    /**
     * Check if the job has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === MonitorStatus::FAILED;
    }

    /**
     * Check if the job has succeeded.
     */
    public function hasSucceeded(): bool
    {
        if (! $this->isFinished()) {
            return false;
        }

        return ! $this->hasFailed();
    }

    public function retry(): void
    {
        $this->retried = true;
        $this->save();

        $response = Artisan::call('queue:retry', ['id' => $this->job_uuid]);

        if ($response !== 0) {
            throw new Exception(Artisan::output());
        }
    }

    public function canBeRetried(): bool
    {
        return ! $this->retried
            && $this->status === MonitorStatus::FAILED
            && $this->job_uuid;
    }
}
