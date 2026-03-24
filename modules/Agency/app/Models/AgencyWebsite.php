<?php

declare(strict_types=1);

namespace Modules\Agency\Models;

use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Agency\Enums\WebsiteStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Customers\Models\Customer;
use Modules\Subscriptions\Models\Subscription;

/**
 * Local cache of website records sourced from the Platform API.
 *
 * This table is populated by webhook events from Platform (console) and
 * by the PlatformApiClient when listing/creating websites. It enables
 * the Agency module to display website data without calling the Platform
 * API on every page load.
 *
 * Agency owns the business data (type, plan, expiry, customer).
 * Platform owns the infrastructure data (server, provisioning, version).
 *
 * @property int $id
 * @property string $site_id Platform-assigned identifier
 * @property string $domain
 * @property string|null $name
 * @property string $type
 * @property WebsiteStatus $status
 * @property int $owner_id
 * @property string|null $owner_email
 * @property string|null $owner_name
 * @property string|null $customer_ref
 * @property array|null $customer_data
 * @property bool $is_www
 * @property string|null $plan
 * @property string|null $plan_ref
 * @property array|null $plan_data
 * @property string|null $server_name
 * @property string|null $astero_version
 * @property string|null $admin_slug
 * @property Carbon|null $expired_on
 * @property Carbon|null $provisioned_at
 * @property array|null $metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class AgencyWebsite extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    protected $table = 'agency_websites';

    protected $fillable = [
        'site_id',
        'domain',
        'name',
        'type',
        'status',
        'owner_id',
        'owner_email',
        'owner_name',
        'customer_ref',
        'customer_data',
        'is_www',
        'plan',
        'plan_ref',
        'plan_data',
        'server_name',
        'astero_version',
        'admin_slug',
        'expired_on',
        'provisioned_at',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Attributes appended to the model's array/JSON form.
     *
     * @var list<string>
     */
    protected $appends = [
        'status_label',
        'status_badge',
        'status_class',
        'type_label',
        'type_class',
        'domain_url',
    ];

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    /**
     * The local user who owns this website.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // ──────────────────────────────────────────────────────────
    // Cross-Module Helpers (query via metadata.site_id)
    // ──────────────────────────────────────────────────────────

    /**
     * Get the Customer record associated with this website's owner.
     *
     * Chain: agency_websites.owner_id → users.id → customers_customers.user_id
     */
    public function findCustomer(): ?object
    {
        if (! module_enabled('Customers')) {
            return null;
        }

        return Customer::query()
            ->where('user_id', $this->owner_id)
            ->first();
    }

    /**
     * Get the active subscription linked to this website via metadata.site_id.
     */
    public function findSubscription(): ?object
    {
        if (! module_enabled('Subscriptions') || ! $this->site_id) {
            return null;
        }

        return Subscription::query()
            ->whereJsonContains('metadata->site_id', $this->site_id)
            ->latest()
            ->first();
    }

    /**
     * Get all invoices linked to this website via metadata.site_id.
     */
    public function findInvoices(): Collection
    {
        if (! module_enabled('Billing') || ! $this->site_id) {
            return new Collection;
        }

        return Invoice::query()
            ->whereJsonContains('metadata->site_id', $this->site_id)
            ->latest()
            ->get();
    }

    /**
     * Get all payments for invoices linked to this website.
     */
    public function findPayments(): Collection
    {
        if (! module_enabled('Billing') || ! $this->site_id) {
            return new Collection;
        }

        $invoiceIds = Invoice::query()
            ->whereJsonContains('metadata->site_id', $this->site_id)
            ->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return new Collection;
        }

        return Payment::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->latest()
            ->get();
    }

    // ──────────────────────────────────────────────────────────
    // Sync Methods
    // ──────────────────────────────────────────────────────────

    /**
     * Create or update a local record from Platform API response data.
     *
     * @param  array<string, mixed>  $apiData  Payload from WebsiteApiResource
     * @param  int  $ownerId  Local user ID
     */
    public static function syncFromApi(array $apiData, int $ownerId): self
    {
        // Handle plan field - can be string (legacy) or array (current API format)
        $plan = $apiData['plan'] ?? null;
        $planRef = null;
        $planData = null;
        if (is_array($plan)) {
            $planRef = $plan['ref'] ?? null;
            $planData = $plan;
            $plan = $plan['name'] ?? $plan['ref'] ?? null;
        }

        // Handle customer field
        $customer = $apiData['customer'] ?? null;
        $customerRef = null;
        $customerData = null;
        if (is_array($customer) && $customer !== []) {
            $customerRef = $customer['ref'] ?? null;
            $customerData = $customer;
        }

        $website = static::query()->updateOrCreate(['site_id' => $apiData['site_id']], [
            'domain' => $apiData['domain'],
            'name' => $apiData['name'] ?? null,
            'type' => $apiData['type'] ?? 'paid',
            'status' => $apiData['status'],
            'owner_id' => $ownerId,
            'owner_email' => $apiData['owner_email'] ?? null,
            'owner_name' => $apiData['owner_name'] ?? null,
            'customer_ref' => $customerRef,
            'customer_data' => $customerData,
            'is_www' => $apiData['is_www'] ?? false,
            'plan' => $plan,
            'plan_ref' => $planRef,
            'plan_data' => $planData,
            'server_name' => $apiData['server_name'] ?? null,
            'astero_version' => $apiData['astero_version'] ?? null,
            'admin_slug' => $apiData['admin_slug'] ?? null,
            'expired_on' => $apiData['expired_on'] ?? null,
            'provisioned_at' => $apiData['provisioned_at'] ?? null,
        ]);

        // Persist dns_mode if provided (injected by onboarding flow)
        if (! empty($apiData['dns_mode'])) {
            $website->setMetadata('dns_mode', $apiData['dns_mode']);
            $website->save();
        }

        return $website;
    }

    /**
     * Update local record from a webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function syncFromWebhook(array $payload): ?self
    {
        $siteId = $payload['site_id'] ?? null;
        if (! $siteId) {
            return null;
        }

        $website = static::query()->where('site_id', $siteId)->first();

        if (! $website) {
            return null;
        }

        $updateData = array_filter([
            'domain' => $payload['domain'] ?? null,
            'name' => $payload['name'] ?? null,
            'status' => $payload['status'] ?? null,
            'server_name' => $payload['server_name'] ?? null,
            'astero_version' => $payload['astero_version'] ?? null,
            'admin_slug' => $payload['admin_slug'] ?? null,
            'provisioned_at' => $payload['provisioned_at'] ?? null,
        ], fn ($value): bool => $value !== null);

        if ($updateData !== []) {
            $website->update($updateData);
        }

        return $website;
    }

    protected function casts(): array
    {
        return [
            'status' => WebsiteStatus::class,
            'is_www' => 'boolean',
            'expired_on' => 'datetime',
            'provisioned_at' => 'datetime',
            'metadata' => 'array',
            'customer_data' => 'array',
            'plan_data' => 'array',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'deleted_by' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────────

    protected function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    protected function getStatusBadgeAttribute(): string
    {
        return $this->status->badgeClass();
    }

    protected function getStatusClassAttribute(): string
    {
        return $this->status->badgeClass();
    }

    protected function getTypeLabelAttribute(): string
    {
        return ucfirst($this->type ?? 'paid');
    }

    protected function getTypeClassAttribute(): string
    {
        return match ($this->type) {
            'trial' => 'bg-warning-subtle text-warning',
            'internal' => 'bg-info-subtle text-info',
            'free' => 'bg-secondary-subtle text-secondary',
            'special' => 'bg-primary-subtle text-primary',
            default => 'bg-success-subtle text-success',
        };
    }

    protected function getDomainUrlAttribute(): string
    {
        $prefix = $this->is_www ? 'https://www.' : 'https://';

        return $prefix.$this->domain;
    }

    protected function getAdminUrlAttribute(): ?string
    {
        if (! $this->admin_slug) {
            return null;
        }

        return 'https://'.$this->domain.'/'.$this->admin_slug.'/login';
    }

    /**
     * Structured customer info (merged ref + data).
     *
     * @return array<string, mixed>
     */
    protected function getCustomerInfoAttribute(): array
    {
        $data = $this->customer_data ?? [];
        if ($this->customer_ref) {
            $data['ref'] = $this->customer_ref;
        }

        return $data;
    }

    /**
     * Structured plan info (merged ref + data).
     *
     * @return array<string, mixed>
     */
    protected function getPlanInfoAttribute(): array
    {
        $data = $this->plan_data ?? [];
        if ($this->plan_ref) {
            $data['ref'] = $this->plan_ref;
        }

        if ($this->plan && ! isset($data['name'])) {
            $data['name'] = $this->plan;
        }

        return $data;
    }

    /**
     * Whether the website has expired.
     */
    protected function getIsExpiredAttribute(): bool
    {
        return $this->expired_on !== null && $this->expired_on->isPast();
    }

    /**
     * Customer display name for DataGrid.
     */
    protected function getCustomerNameAttribute(): string
    {
        return $this->customer_data['name']
            ?? $this->owner_name
            ?? $this->owner_email
            ?? 'N/A';
    }
}
