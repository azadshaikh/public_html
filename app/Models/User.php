<?php

namespace App\Models;

use App\Enums\Status;
use App\Observers\UserObserver;
use App\Services\EmailService;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use App\Traits\InteractWithCustomMedia;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Permission\Traits\HasRoles;
use Throwable;

#[ObservedBy([UserObserver::class])]
/**
 * @property int $id
 * @property string|null $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $avatar
 * @property string|null $avatar_image
 * @property Status|null $status
 * @property-read string $full_name
 */
class User extends Authenticatable implements HasMedia, MustVerifyEmailContract
{
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use HasRoles;
    use InteractWithCustomMedia;
    use MustVerifyEmailTrait {
        sendEmailVerificationNotification as protected sendDefaultEmailVerificationNotification;
    }
    use Notifiable;
    use SoftDeletes;

    /**
     * The guard name for the model.
     */
    protected $guard_name = 'web';

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'username',
        'gender',
        'tagline',
        'bio',
        'avatar',
        'status',
        'last_access',
        'metadata',
        'notifications_enabled',
        'notification_preferences',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Appended attributes.
     */
    protected $appends = [
        'status_label',
        'status_badge',
        'full_name',
        'email_verification_status',
    ];

    /**
     * Send the framework's default email verification notification.
     *
     * Exposed for services that need to trigger the non-templated fallback.
     */
    public function sendFallbackEmailVerificationNotification(): void
    {
        $this->sendDefaultEmailVerificationNotification();
    }

    /**
     * Send the email verification notification using the configured email system.
     */
    public function sendEmailVerificationNotification(): void
    {
        try {
            $result = resolve(EmailService::class)->sendVerificationEmail($this);

            if ($result->failed()) {
                Log::warning('Verification email dispatch failed', $result->toArray());
                $this->sendDefaultEmailVerificationNotification();
            }
        } catch (Throwable $throwable) {
            Log::error('Unexpected verification email failure', [
                'user_id' => $this->getKey(),
                'error' => $throwable->getMessage(),
            ]);

            $this->sendDefaultEmailVerificationNotification();
        }
    }

    /**
     * Mark email verification as skipped (optional).
     */
    public function markEmailVerificationAsSkipped(): void
    {
        $metadata = $this->getMetadata('email_verification', []);
        $metadata['status'] = 'skipped';
        $metadata['skipped_at'] = now()->toIso8601String();
        unset($metadata['verified_at']);

        $this->setMetadata('email_verification', $metadata);
        $this->forceFill(['metadata' => $this->metadata])->save();
    }

    /**
     * Mark the user's email as verified and update metadata.
     */
    public function markEmailAsVerified(): bool
    {
        $metadata = $this->getMetadata('email_verification', []);
        $metadata['status'] = 'verified';
        $metadata['verified_at'] = now()->toIso8601String();

        $this->setMetadata('email_verification', $metadata);

        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'metadata' => $this->metadata,
        ])->save();
    }

    /**
     * Relationships
     */

    /**
     * Get the user who created this user.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /**
     * Get the user who last updated this user.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }

    /**
     * Get the user who deleted this user.
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deleted_by');
    }

    /**
     * Get all addresses for this user.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * Get the primary address for this user.
     */
    public function primaryAddress(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable')->where('is_primary', true);
    }

    /**
     * Get all activity logs performed by this user.
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'causer');
    }

    /**
     * Get the social login providers linked to this user.
     */
    public function socialProviders(): HasMany
    {
        return $this->hasMany(UserProvider::class);
    }

    /**
     * Check whether the user has explicitly set a password they know.
     *
     * Users who registered via social login get a random password they don't
     * know.  Once they explicitly set/change their password through the
     * profile form, the `has_set_password` metadata flag is stored.
     *
     * Users without any social providers are assumed to have a usable password
     * (they registered with email/password).
     */
    public function hasUsablePassword(): bool
    {
        // No social providers → registered with email/password → knows their password.
        if (! $this->socialProviders()->exists()) {
            return true;
        }

        // Has social providers but explicitly set a password via profile.
        return (bool) $this->getMetadata('has_set_password', false);
    }

    /**
     * Get the configured super user role ID.
     */
    public static function superUserRoleId(): int
    {
        return (int) config('auth.super_user_role_id', 1);
    }

    /**
     * Check if the user is a super user.
     */
    public function isSuperUser(): bool
    {
        return $this->hasRole(self::superUserRoleId());
    }

    /**
     * Get user options for dropdowns (id => name format).
     */
    public static function getAddedByOptions(): array
    {
        try {
            return self::query()->visibleToCurrentUser()
                ->active()
                ->select('id', 'first_name')
                ->orderBy('first_name')
                ->pluck('first_name', 'id')
                ->toArray();
        } catch (Throwable $throwable) {
            if (App::runningInConsole()) {
                return [];
            }

            throw $throwable;
        }
    }

    /**
     * Get active users formatted for select dropdowns.
     * Used by ViewData attributes for auto-generated view composers.
     */
    public static function getActiveUsersForSelect(): array
    {
        try {
            return self::query()->visibleToCurrentUser()
                ->where('status', 'active')
                ->get()
                ->map(fn ($user): array => [
                    'value' => (string) $user->id,
                    'label' => $user->name,
                ])
                ->sortBy('label')
                ->values()
                ->toArray();
        } catch (Throwable $throwable) {
            if (App::runningInConsole()) {
                return [];
            }

            throw $throwable;
        }
    }

    /**
     * Get admin user emails.
     */
    public static function getAdminEmails(): array
    {
        return self::query()->whereHas('roles', function ($query): void {
            $query->whereIn('name', ['super_user', 'administrator']);
        })
            ->pluck('email')
            ->toArray();
    }

    /**
     * Get user counts by status.
     */
    public function getStatusCounts(): array
    {
        return self::query()->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get birth date from metadata.
     */
    public function getBirthDate(): ?string
    {
        return $this->getMetadata('birth_date');
    }

    /**
     * Set birth date in metadata.
     */
    public function setBirthDate(?string $birthDate): void
    {
        $this->setMetadata('birth_date', $birthDate);
    }

    /**
     * Get website URL from metadata.
     */
    public function getWebsiteUrl(): ?string
    {
        return $this->getMetadata('website_url');
    }

    /**
     * Set website URL in metadata.
     */
    public function setWebsiteUrl(?string $url): void
    {
        $this->setMetadata('website_url', $url);
    }

    /**
     * Get X (Twitter) URL from metadata.
     */
    public function getTwitterUrl(): ?string
    {
        return $this->getMetadata('twitter_url');
    }

    /**
     * Set X (Twitter) URL in metadata.
     */
    public function setTwitterUrl(?string $url): void
    {
        $this->setMetadata('twitter_url', $url);
    }

    /**
     * Get Facebook URL from metadata.
     */
    public function getFacebookUrl(): ?string
    {
        return $this->getMetadata('facebook_url');
    }

    /**
     * Set Facebook URL in metadata.
     */
    public function setFacebookUrl(?string $url): void
    {
        $this->setMetadata('facebook_url', $url);
    }

    /**
     * Get Instagram URL from metadata.
     */
    public function getInstagramUrl(): ?string
    {
        return $this->getMetadata('instagram_url');
    }

    /**
     * Set Instagram URL in metadata.
     */
    public function setInstagramUrl(?string $url): void
    {
        $this->setMetadata('instagram_url', $url);
    }

    /**
     * Get LinkedIn URL from metadata.
     */
    public function getLinkedinUrl(): ?string
    {
        return $this->getMetadata('linkedin_url');
    }

    /**
     * Set LinkedIn URL in metadata.
     */
    public function setLinkedinUrl(?string $url): void
    {
        $this->setMetadata('linkedin_url', $url);
    }

    /**
     * Get all social URLs as an array.
     */
    public function getSocialUrls(): array
    {
        return [
            'website' => $this->getWebsiteUrl(),
            'twitter' => $this->getTwitterUrl(),
            'facebook' => $this->getFacebookUrl(),
            'instagram' => $this->getInstagramUrl(),
            'linkedin' => $this->getLinkedinUrl(),
        ];
    }

    /**
     * Set multiple social URLs at once.
     */
    public function setSocialUrls(array $urls): void
    {
        foreach ($urls as $platform => $url) {
            $method = 'set'.ucfirst((string) $platform).'Url';
            if (method_exists($this, $method)) {
                $this->{$method}($url);
            }
        }
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_access' => 'datetime',
            'metadata' => 'array',
            'notifications_enabled' => 'boolean',
            'notification_preferences' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the human-readable status label.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => Status::labels()[$this->getStatusValue()] ?? 'Unknown'
        );
    }

    /**
     * Get the status badge class for UI.
     */
    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $statusMap = [
                    'active' => 'success',
                    'pending' => 'info',
                    'suspended' => 'warning',
                    'banned' => 'danger',
                ];

                return $statusMap[$this->getStatusValue()] ?? 'secondary';
            }
        );
    }

    /**
     * Get the user's full name (backward compatibility).
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->name ?: trim($this->first_name.' '.$this->last_name)
        );
    }

    /**
     * Get the email verification status for display.
     */
    protected function emailVerificationStatus(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->hasVerifiedEmail()) {
                    return 'verified';
                }

                $status = $this->getMetadata('email_verification.status');

                return $status === 'skipped' ? 'skipped' : 'unverified';
            }
        );
    }

    /**
     * Get the user's avatar image URL with fallback to default placeholder.
     */
    protected function avatarImage(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->avatar ? get_media_url($this->avatar) : (empty(setting('media_default_avtar_image', '')) ? asset('assets/images/profile-placeholder.svg') : get_media_url(setting('media_default_avtar_image')))
        );
    }

    /**
     * Get the guard name for the model.
     */
    protected function getGuardName(): string
    {
        return '';
    }

    /**
     * Scopes
     */

    /**
     * Scope to get only active users.
     */
    #[Scope]
    protected function active($query)
    {
        return $query->where('status', Status::ACTIVE);
    }

    /**
     * Scope to get only suspended users.
     */
    #[Scope]
    protected function suspended($query)
    {
        return $query->where('status', Status::SUSPENDED);
    }

    /**
     * Scope to get only banned users.
     */
    #[Scope]
    protected function banned($query)
    {
        return $query->where('status', Status::BANNED);
    }

    /**
     * Scope to filter out super users from non-super user views.
     * Super users can see all users, but normal users cannot see super users.
     */
    #[Scope]
    protected function visibleToCurrentUser($query)
    {
        $currentUser = Auth::user();

        // If no user is authenticated or current user is a super user, show all
        if (! $currentUser || $currentUser->isSuperUser()) {
            return $query;
        }

        // For non-super users, exclude users who have the super_user role
        return $query->whereDoesntHave('roles', function ($q): void {
            $q->where('roles.id', self::superUserRoleId());
        });
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $user): void {
            if (Auth::check()) {
                $user->created_by = Auth::id();
            }
        });

        static::updating(function (self $user): void {
            if (Auth::check()) {
                $user->updated_by = Auth::id();
            }

            // Protect super user (ID 1) from being banned or suspended
            if ($user->id === 1 && $user->isDirty('status')) {
                $restrictedStatuses = [Status::BANNED->value, Status::SUSPENDED->value];
                throw_if(in_array($user->getStatusValue(), $restrictedStatuses, true), RuntimeException::class, 'Cannot set super user status to banned or suspended.');
            }
        });

        static::deleting(function (self $user): bool {
            // Protect super user (ID 1) from being deleted
            throw_if($user->id === 1, RuntimeException::class, 'Cannot delete the super user account.');

            if (Auth::check()) {
                $user->deleted_by = Auth::id();
                $user->saveQuietly();
            }

            return true;
        });
    }

    private function getStatusValue(): string
    {
        $status = $this->getAttribute('status');

        if ($status instanceof Status) {
            return $status->value;
        }

        if (is_string($status) && $status !== '') {
            return $status;
        }

        return Status::SUSPENDED->value;
    }
}
