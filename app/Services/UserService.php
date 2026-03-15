<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\UserDefinition;
use App\Enums\Status;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class UserService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new UserDefinition;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        // Apply visibility scope to hide super users from statistics for non-super users
        return [
            'total' => User::visibleToCurrentUser()->whereIn('status', [
                Status::ACTIVE,
                Status::PENDING,
                Status::SUSPENDED,
                Status::BANNED,
            ])->count(),
            'active' => User::visibleToCurrentUser()->where('status', Status::ACTIVE)->count(),
            'pending' => User::visibleToCurrentUser()->where('status', Status::PENDING)->count(),
            'suspended' => User::visibleToCurrentUser()->where('status', Status::SUSPENDED)->count(),
            'banned' => User::visibleToCurrentUser()->where('status', Status::BANNED)->count(),
            'trash' => User::visibleToCurrentUser()->onlyTrashed()->count(),
        ];
    }

    /**
     * Get paginated users with UserResource transformation.
     *
     * Returns an array matching the standard Laravel paginator format
     * (data, links, current_page, etc.) expected by the DataGrid component.
     */
    public function getPaginatedUsers(Request $request): array
    {
        $query = $this->buildListQuery($request);
        $paginator = $query->paginate($this->getPerPage($request))->onEachSide(1);

        // Convert paginator to array (standard format with links[] as page links)
        $paginatedArray = $paginator->toArray();

        // Replace raw model data with resource-transformed data
        $paginatedArray['data'] = UserResource::collection($paginator->items())
            ->resolve(request());

        return $paginatedArray;
    }

    // ================================================================
    // CUSTOM OPTIONS (for forms)
    // ================================================================

    public function getStatusOptions(): array
    {
        return [
            ['value' => Status::ACTIVE->value, 'label' => 'Active'],
            ['value' => Status::PENDING->value, 'label' => 'Pending Approval'],
            ['value' => Status::SUSPENDED->value, 'label' => 'Suspended'],
            ['value' => Status::BANNED->value, 'label' => 'Banned'],
        ];
    }

    public function getGenderOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Select Gender'],
            ['value' => 'male', 'label' => 'Male'],
            ['value' => 'female', 'label' => 'Female'],
            ['value' => 'other', 'label' => 'Other'],
        ];
    }

    public function getRoleOptions(): array
    {
        if (! auth()->check()) {
            return Role::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn ($role): array => [
                    'value' => $role->id,
                    'label' => ucfirst((string) $role->name),
                ])->all();
        }

        // Use visibility-aware cache key to handle super user vs non-super user
        $isSuperUser = auth()->user()->isSuperUser();
        $cacheKey = 'role_options_'.($isSuperUser ? 'super' : 'normal');

        return Cache::remember($cacheKey, 3600, fn () => Role::visibleToCurrentUser()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($role): array => [
                'value' => $role->id,
                'label' => ucfirst((string) $role->name),
            ])->toArray());
    }

    public function getRoleFilterOptions(): array
    {
        $roles = Role::visibleToCurrentUser()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $options = [];
        foreach ($roles as $role) {
            $options[$role->id] = $role->name;
        }

        return $options;
    }

    // ================================================================
    // REGISTRATION SETTINGS SUMMARY (for index view)
    // ================================================================

    public function getRegistrationSettingsSummary(array $statistics): array
    {
        $enabled = filter_var(setting('registration_enable_registration', true), FILTER_VALIDATE_BOOLEAN);
        $autoApprove = filter_var(setting('registration_auto_approve', true), FILTER_VALIDATE_BOOLEAN);
        $requireVerification = filter_var(setting('registration_require_email_verification', true), FILTER_VALIDATE_BOOLEAN);

        $defaultRoleId = (int) setting('registration_default_role', 0);
        $defaultRole = $defaultRoleId !== 0
            ? Role::query()->select(['display_name', 'name'])->find($defaultRoleId)
            : null;
        $defaultRoleName = null;
        if ($defaultRole instanceof Role) {
            $displayName = $defaultRole->getAttribute('display_name');
            $name = $defaultRole->getAttribute('name');
            $defaultRoleName = is_string($displayName) && $displayName !== '' ? $displayName : (is_string($name) ? $name : null);
        }

        return [
            'enabled' => $enabled,
            'auto_approve' => $autoApprove,
            'require_verification' => $requireVerification,
            'default_role_id' => $defaultRoleId,
            'default_role_name' => $defaultRoleName,
            'settings_url' => route('app.settings.index', ['section' => 'registration-settings-section']),
            'pending_count' => $statistics['pending'] ?? 0,
            'pending_route' => route('app.users.index', ['status' => 'pending']),
        ];
    }

    // ================================================================
    // CUSTOM CRUD OPERATIONS (with address & role handling)
    // ================================================================

    public function createUser(array $data): User
    {
        return $this->create($data);
    }

    /**
     * Override create to handle address and role data.
     */
    public function create(array $data): User
    {
        // Normalize field names (support both camelCase and snake_case)
        if (isset($data['firstName']) && ! isset($data['first_name'])) {
            $data['first_name'] = $data['firstName'];
            unset($data['firstName']);
        }

        if (isset($data['lastName']) && ! isset($data['last_name'])) {
            $data['last_name'] = $data['lastName'];
            unset($data['lastName']);
        }

        if (isset($data['name']) && ! isset($data['first_name'])) {
            [$data['first_name'], $data['last_name']] = $this->splitName($data['name']);
        }

        // Build name field if not provided
        if (empty($data['name'])) {
            $data['name'] = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
            if (empty($data['name'])) {
                $data['name'] = $data['username'] ?? $data['email'] ?? '';
            }
        }

        // Set default status if not provided
        if (! isset($data['status'])) {
            $data['status'] = Status::ACTIVE;
        }

        // Separate address data and role data from user data
        $addressData = $this->extractAddressData($data);
        $roleData = $this->extractRoleData($data);
        $userData = $this->removeAddressData($data);
        $userData = $this->removeRoleData($userData);

        // Create the user using scaffoldable create
        $preparedData = $this->prepareCreateData($userData);
        $user = User::query()->create($preparedData);

        // Assign roles if provided
        if ($roleData !== []) {
            $roleIds = array_map(intval(...), $roleData);
            $roles = \Spatie\Permission\Models\Role::query()->whereIn('id', $roleIds)->get();
            $user->syncRoles($roles);
        }

        // Create address if address data is provided
        if ($addressData !== []) {
            $this->createOrUpdateAddress($user, $addressData);
        }

        return $user;
    }

    /**
     * Override update to handle address and role data.
     *
     * @throws InvalidArgumentException If trying to set super user status to banned/suspended
     */
    public function update(Model $model, array $data): User
    {
        if (! $model instanceof User) {
            $model = User::query()->findOrFail((int) $model->getKey());
        }

        // Protect super user (ID 1) from being banned or suspended
        if ((int) $model->getKey() === 1 && isset($data['status'])) {
            $restrictedStatuses = [Status::BANNED->value, Status::SUSPENDED->value, Status::BANNED, Status::SUSPENDED];
            throw_if(in_array($data['status'], $restrictedStatuses, false), InvalidArgumentException::class, 'Cannot set super user status to banned or suspended.');
        }

        if (isset($data['name']) && ! isset($data['first_name'])) {
            [$data['first_name'], $data['last_name']] = $this->splitName($data['name']);
        }

        // Separate address data and role data from user data
        $addressData = $this->extractAddressData($data);
        $roleData = $this->extractRoleData($data);
        $userData = $this->removeAddressData($data);
        $userData = $this->removeRoleData($userData);

        // Update the user using scaffoldable update
        $preparedData = $this->prepareUpdateData($userData);
        $model->update($preparedData);

        // Assign roles if provided
        if ($roleData !== []) {
            $roleIds = array_map(intval(...), $roleData);
            $roles = \Spatie\Permission\Models\Role::query()->whereIn('id', $roleIds)->get();
            $model->syncRoles($roles);
        }

        // Create or update address
        $this->createOrUpdateAddress($model, $addressData);

        $model->refresh();

        return $model;
    }

    // ================================================================
    // USER-SPECIFIC ACTIONS
    // ================================================================
    /**
     * Suspend a user.
     *
     * @throws InvalidArgumentException If trying to suspend the super user (ID 1)
     */
    public function suspendUser(User $user): void
    {
        throw_if($user->id === 1, InvalidArgumentException::class, 'Cannot suspend the super user account.');

        $user->update(['status' => Status::SUSPENDED]);
    }

    /**
     * Ban a user.
     *
     * @throws InvalidArgumentException If trying to ban the super user (ID 1)
     */
    public function banUser(User $user): void
    {
        throw_if($user->id === 1, InvalidArgumentException::class, 'Cannot ban the super user account.');

        $user->update(['status' => Status::BANNED]);
    }

    /**
     * Unban a user (set to active).
     */
    public function unbanUser(User $user): void
    {
        $user->update(['status' => Status::ACTIVE]);
    }

    /**
     * Approve a pending user.
     */
    public function approveUser(User $user): void
    {
        $user->update(['status' => Status::ACTIVE]);
    }

    /**
     * Handle custom bulk actions for users.
     */
    public function handleCustomBulkAction(string $action, array $ids, ?Request $request = null): array
    {
        $errors = [];

        // Ensure IDs are integers for proper comparison
        $ids = array_map(intval(...), $ids);

        // Protect super user (ID 1) from suspend/ban actions
        if (in_array($action, ['suspend', 'ban']) && in_array(1, $ids, true)) {
            $errors[] = 'ID 1: Cannot modify the super user account';
            $ids = array_values(array_filter($ids, fn ($id): bool => $id !== 1));
        }

        // Determine new status based on action
        $newStatus = match ($action) {
            'suspend' => Status::SUSPENDED,
            'ban' => Status::BANNED,
            'unban' => Status::ACTIVE,
            default => throw new InvalidArgumentException('Unknown action: '.$action),
        };

        try {
            // Use batch update to prevent N+1 queries
            $successCount = User::query()->whereIn('id', $ids)
                ->update(['status' => $newStatus, 'updated_by' => auth()->id()]);

            // If some IDs don't exist, track them as errors
            if ($successCount < count($ids)) {
                $updatedIds = User::query()->whereIn('id', $ids)->pluck('id')->toArray();
                $missingIds = array_diff($ids, $updatedIds);
                foreach ($missingIds as $missingId) {
                    $errors[] = sprintf('ID %d: User not found', $missingId);
                }
            }
        } catch (Exception) {
            // If batch update fails, fall back to individual updates
            $successCount = 0;
            foreach ($ids as $id) {
                try {
                    $user = User::query()->findOrFail($id);
                    $user->update(['status' => $newStatus]);
                    $successCount++;
                } catch (Exception $userException) {
                    $errors[] = sprintf('ID %d: ', $id).$userException->getMessage();
                }
            }
        }

        $totalCount = count($ids);
        $actionLabel = match ($action) {
            'suspend' => 'suspended',
            'ban' => 'banned',
            'unban' => 'unbanned',
            default => 'processed',
        };

        return [
            'success_count' => $successCount,
            'total_count' => $totalCount,
            'errors' => $errors,
            'message' => sprintf('%s of %d users %s successfully.', $successCount, $totalCount, $actionLabel),
        ];
    }

    /**
     * @throws AuthorizationException
     */
    protected function authorizeCustomBulkAction(string $action): void
    {
        $requiredPermission = match ($action) {
            'suspend', 'ban', 'unban' => 'edit_'.$this->getScaffoldDefinition()->getPermissionPrefix(),
            default => null,
        };

        if ($requiredPermission === null) {
            throw_unless(
                auth()->check(),
                AuthorizationException::class,
                'You must be authenticated to perform this action.',
            );

            return;
        }

        throw_if(
            ! auth()->check() || ! auth()->user()->can($requiredPermission),
            AuthorizationException::class,
            sprintf("You do not have permission to perform the '%s' action.", $action),
        );
    }

    /**
     * Register a new user account.
     */
    public function register(array $data): User
    {
        // Hash the password
        $data['password'] = bcrypt($data['password']);

        // Determine auto-approval status from settings
        $autoApprove = setting('registration_auto_approve', true);
        $data['status'] = $autoApprove ? Status::ACTIVE : Status::PENDING;

        // Assign default role from settings when available
        $defaultRoleId = (int) setting('registration_default_role', 5);
        $defaultRole = Role::query()
            ->where('status', Status::ACTIVE)
            ->where('id', $defaultRoleId)
            ->first();

        if (! $defaultRole) {
            $defaultRole = Role::query()
                ->where('status', Status::ACTIVE)
                ->where('name', 'user')
                ->first();
        }

        if ($defaultRole) {
            $data['roles'] = [$defaultRole->id];
        }

        // Split name into first_name and last_name if needed
        if (isset($data['name']) && ! isset($data['first_name'])) {
            [$data['first_name'], $data['last_name']] = $this->splitName($data['name']);
        }

        return $this->create($data);
    }

    /**
     * Split a full name into first and last name.
     */
    public function splitName(?string $fullName): array
    {
        if (in_array($fullName, [null, '', '0'], true)) {
            return [null, null];
        }

        $parts = explode(' ', trim($fullName), 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? null;

        return [$firstName, $lastName];
    }

    /**
     * Find a user by email address.
     */
    public function findUserByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    /**
     * Update a user's password.
     */
    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => bcrypt($password)]);
        $user->setMetadata('has_set_password', true);
    }

    protected function getResourceClass(): ?string
    {
        return UserResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
            'roles:id,name',
            'primaryAddress:id,addressable_id,phone',
        ];
    }

    // ================================================================
    // ⚠️ CRITICAL: OVERRIDE FOR STATUS & TRASH TAB SUPPORT
    // ================================================================

    protected function buildListQuery(Request $request): Builder
    {
        $query = User::query();

        // Apply visibility scope to hide super users from non-super users
        $query->visibleToCurrentUser();

        // ⚠️ CRITICAL: Check BOTH query param AND route parameter
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        // Handle different status tabs
        if ($status === 'trash') {
            $query->onlyTrashed();
        } elseif ($status === 'active') {
            $query->where('status', Status::ACTIVE)->whereNull('deleted_at');
        } elseif ($status === 'pending') {
            $query->where('status', Status::PENDING)->whereNull('deleted_at');
        } elseif ($status === 'suspended') {
            $query->where('status', Status::SUSPENDED)->whereNull('deleted_at');
        } elseif ($status === 'banned') {
            $query->where('status', Status::BANNED)->whereNull('deleted_at');
        } else {
            // 'all' - all non-trashed users
            $query->whereIn('status', [
                Status::ACTIVE,
                Status::PENDING,
                Status::SUSPENDED,
                Status::BANNED,
            ])->whereNull('deleted_at');
        }

        // ⚠️ CRITICAL: Merge route status into request for filters
        if (! $request->has('status') && $request->route('status')) {
            $request->merge(['status' => $status]);
        }

        // Apply standard scaffold methods
        $this->applyEagerLoading($query);
        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);
        $this->customizeListQuery($query, $request);

        return $query;
    }

    // ================================================================
    // SEARCHABLE FIELDS
    // ================================================================

    protected function getSearchableFields(): array
    {
        return ['first_name', 'last_name', 'email', 'username'];
    }

    protected function getSearchableRelations(): array
    {
        return [
            'roles' => ['name'],
        ];
    }

    // ================================================================
    // CUSTOM FILTER HANDLING
    // ================================================================

    protected function applyFilters(Builder $query, Request $request): void
    {
        $currentStatus = $request->input('status') ?? $request->route('status') ?? 'all';

        // Status filter (only for non-trash tabs and 'all' tab)
        if ($currentStatus === 'all' && $request->filled('filter_status')) {
            $query->where('status', $request->input('filter_status'));
        }

        // Role filter
        if ($request->filled('role_id') && $request->string('role_id')->isNotEmpty()) {
            $query->whereHas('roles', function ($q) use ($request): void {
                $q->where('id', $request->integer('role_id'));
            });
        }

        // Gender filter
        if ($request->filled('gender') && $request->string('gender')->isNotEmpty()) {
            $query->where('gender', $request->string('gender'));
        }

        // Email verification filter
        if ($request->filled('email_verified')) {
            $verificationFilter = $request->string('email_verified')->value();

            if ($verificationFilter === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($verificationFilter === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        // Country filter
        if ($request->filled('country') && $request->string('country')->isNotEmpty()) {
            $query->whereHas('addresses', function ($q) use ($request): void {
                $q->where('country', $request->string('country'));
            });
        }

        // Date range filter — supports comma-separated format from DataGrid (e.g. "2024-01-01,2024-12-31")
        // as well as legacy separate created_at_from / created_at_to params
        if ($createdAt = $request->input('created_at')) {
            $dates = explode(',', $createdAt, 2);

            if (! empty($dates[0])) {
                $query->whereDate('created_at', '>=', $dates[0]);
            }

            if (! empty($dates[1])) {
                $query->whereDate('created_at', '<=', $dates[1]);
            }
        } else {
            if ($from = $request->input('created_at_from')) {
                $query->whereDate('created_at', '>=', $from);
            }

            if ($to = $request->input('created_at_to')) {
                $query->whereDate('created_at', '<=', $to);
            }
        }
    }

    // ================================================================
    // DATA PREPARATION
    // ================================================================

    protected function prepareCreateData(array $data): array
    {
        // Prepare metadata for social URLs and birth date
        $metadata = [];
        if (! empty($data['birth_date'])) {
            $metadata['birth_date'] = $data['birth_date'];
        }

        if (! empty($data['website_url'])) {
            $metadata['website_url'] = $data['website_url'];
        }

        if (! empty($data['twitter_url'])) {
            $metadata['twitter_url'] = $data['twitter_url'];
        }

        if (! empty($data['facebook_url'])) {
            $metadata['facebook_url'] = $data['facebook_url'];
        }

        if (! empty($data['instagram_url'])) {
            $metadata['instagram_url'] = $data['instagram_url'];
        }

        if (! empty($data['linkedin_url'])) {
            $metadata['linkedin_url'] = $data['linkedin_url'];
        }

        return [
            'name' => $data['name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'username' => $data['username'] ?? null,
            'gender' => $data['gender'] ?? null,
            'tagline' => $data['tagline'] ?? null,
            'bio' => $data['bio'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'status' => $data['status'] ?? Status::ACTIVE,
            'metadata' => $metadata,
            'email_verified_at' => isset($data['email_verified']) && $data['email_verified'] ? now() : null,
        ];
    }

    protected function prepareUpdateData(array $data): array
    {
        // Prepare metadata for social URLs and birth date
        $metadata = [];
        if (isset($data['birth_date'])) {
            $metadata['birth_date'] = $data['birth_date'];
        }

        if (isset($data['website_url'])) {
            $metadata['website_url'] = $data['website_url'];
        }

        if (isset($data['twitter_url'])) {
            $metadata['twitter_url'] = $data['twitter_url'];
        }

        if (isset($data['facebook_url'])) {
            $metadata['facebook_url'] = $data['facebook_url'];
        }

        if (isset($data['instagram_url'])) {
            $metadata['instagram_url'] = $data['instagram_url'];
        }

        if (isset($data['linkedin_url'])) {
            $metadata['linkedin_url'] = $data['linkedin_url'];
        }

        $updateData = [
            'name' => $data['name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'username' => $data['username'] ?? null,
            'gender' => $data['gender'] ?? null,
            'tagline' => $data['tagline'] ?? null,
            'bio' => $data['bio'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'status' => $data['status'] ?? Status::ACTIVE,
            'metadata' => $metadata,
        ];

        // Only include password if provided
        if (! empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        return $updateData;
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    /**
     * Extract address-related data from the input array.
     */
    private function extractAddressData(array $data): array
    {
        $addressFields = [
            'address1', 'address2', 'country', 'country_code', 'state', 'state_code',
            'city', 'city_code', 'zip', 'phone',
        ];

        $addressData = [];
        foreach ($addressFields as $field) {
            if (isset($data[$field]) && ! empty($data[$field])) {
                $addressData[$field] = $data[$field];
            }
        }

        return $addressData;
    }

    /**
     * Remove address-related data from the input array.
     */
    private function removeAddressData(array $data): array
    {
        $addressFields = [
            'address1', 'address2', 'country', 'country_code', 'state', 'state_code',
            'city', 'city_code', 'zip', 'phone',
        ];

        return array_diff_key($data, array_flip($addressFields));
    }

    /**
     * Extract role-related data from the input array.
     */
    private function extractRoleData(array $data): array
    {
        if (isset($data['roles']) && is_array($data['roles'])) {
            return array_filter($data['roles']);
        }

        return [];
    }

    /**
     * Remove role-related data from the input array.
     */
    private function removeRoleData(array $data): array
    {
        return array_diff_key($data, ['roles' => '']);
    }

    /**
     * Create or update address for a user.
     */
    private function createOrUpdateAddress(User $user, array $addressData): void
    {
        if ($addressData === []) {
            return;
        }

        // Prepare address data
        $displayName = (string) ($user->name ?? '');
        if ($displayName === '') {
            $displayName = trim((($user->first_name ?? '')).' '.(($user->last_name ?? '')));
        }

        $addressAttributes = [
            'name' => $displayName,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'address1' => $addressData['address1'] ?? null,
            'address2' => $addressData['address2'] ?? null,
            'country' => $addressData['country'] ?? null,
            'country_code' => $addressData['country_code'] ?? null,
            'state' => $addressData['state'] ?? null,
            'state_code' => $addressData['state_code'] ?? null,
            'city' => $addressData['city'] ?? null,
            'city_code' => $addressData['city_code'] ?? null,
            'zip' => $addressData['zip'] ?? null,
            'phone' => $addressData['phone'] ?? null,
            'type' => 'home',
            'is_primary' => true,
            'is_verified' => false,
        ];

        // Find existing primary address or create new one
        $existingAddress = $user->primaryAddress;

        if ($existingAddress) {
            $existingAddress->update($addressAttributes);
        } else {
            $user->addresses()->create($addressAttributes);
        }
    }
}
