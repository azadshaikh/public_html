<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Agency\Enums\WebsiteStatus;
use Modules\Agency\Exceptions\PlatformApiException;
use Modules\Agency\Http\Requests\DomainStepRequest;
use Modules\Agency\Http\Requests\OnboardingLandingRequest;
use Modules\Agency\Models\AgencyWebsite;
use Modules\Agency\Services\PlatformApiClient;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Services\CouponService;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerService;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderService;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanPrice;
use Modules\Subscriptions\Services\SubscriptionService;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\ApiErrorException as StripeApiErrorException;
use Stripe\Stripe as StripeClient;
use Throwable;

/**
 * Handles the customer onboarding flow:
 *
 * 1. Create Account (Guest) -> /start
 * 2. Select Plan -> /portal/onboarding/plans
 * 3. Website Details -> /portal/onboarding/website
 * 4. Payment/Checkout -> /portal/onboarding/checkout
 * 5. Provision Website -> after payment success
 */
class OnboardingController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly CustomerService $customerService,
        private readonly PlatformApiClient $platformApiClient,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    /**
     * Website builder landing + registration page (/get-started).
     */
    public function getStarted(): InertiaResponse
    {
        return Inertia::render('agency/auth/register', [
            'status' => session('status'),
            'canLogin' => Route::has('login'),
            'socialProviders' => [
                'google' => config('services.social_auth.enabled', false) && config('services.google.enabled', false),
                'github' => config('services.social_auth.enabled', false) && config('services.github.enabled', false),
            ],
        ]);
    }

    /**
     * Process /get-started registration (email + password only).
     */
    public function storeGetStarted(OnboardingLandingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Derive a name from the email prefix (e.g. john.doe@example.com → "John Doe")
        $emailPrefix = explode('@', (string) $validated['email'])[0];
        $name = ucwords(str_replace(['.', '_', '-'], ' ', $emailPrefix));

        $userPayload = [
            'name' => $name,
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        try {
            return DB::transaction(function () use ($request, $userPayload): RedirectResponse {
                $result = $this->authService->register($userPayload, $request);
                $user = $result['user'];

                $this->assignCustomerRole($user);

                $customerData = [
                    'user_id' => $user->id,
                    'user_action' => 'associate',
                    'type' => 'person',
                    'contact_first_name' => $user->first_name,
                    'contact_last_name' => $user->last_name ?? '',
                    'email' => $user->email,
                    'metadata' => [
                        'onboarding' => [
                            'source' => 'agency_landing',
                            'started_at' => now()->toDateTimeString(),
                            'current_step' => 'plan_selection',
                        ],
                    ],
                ];

                $this->customerService->setCustomer($customerData);

                if (! $result['auto_approved']) {
                    return to_route('login')
                        ->with('status', __('auth.registration_pending_approval'));
                }

                Auth::login($user);
                $request->session()->regenerate();

                // Store intended URL so the user lands on domain selection (first onboarding step) after verifying email.
                session()->put('url.intended', route('agency.onboarding.domain'));

                return to_route('verification.notice');
            });
        } catch (Throwable $throwable) {
            Log::error('Agency /get-started registration failed', [
                'exception' => $throwable->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip(),
            ]);

            return back()
                ->withInput($request->except('password'))
                ->withErrors(['email' => __('auth.registration_failed')]);
        }
    }

    /**
     * Custom sign-in page (/sign-in) — same two-column design.
     * The form inside posts to the existing login.store route.
     */
    public function signIn(): InertiaResponse
    {
        return Inertia::render('agency/auth/sign-in', [
            'status' => session('status'),
            'canResetPassword' => Route::has('password.request'),
            'canRegister' => Route::has('register') && (bool) setting('registration_enable_registration', true),
            'socialProviders' => [
                'google' => config('services.social_auth.enabled', false) && config('services.google.enabled', false),
                'github' => config('services.social_auth.enabled', false) && config('services.github.enabled', false),
            ],
        ]);
    }

    /**
     * Entry point for creating a new website (resets session for fresh flow).
     */
    public function createWebsite(): RedirectResponse
    {
        $user = auth()->user();
        $customer = $this->resolveCustomer($user);

        if (! $customer instanceof Customer) {
            return to_route('agency.onboarding.plans');
        }

        // Reset website-creation fields so the flow starts fresh
        $metadata = $customer->metadata ?? [];
        Arr::forget($metadata, [
            'onboarding.domain',
            'onboarding.domain_type',
            'onboarding.domain_selected_at',
            'onboarding.selected_plan_id',
            'onboarding.selected_plan_price_id',
            'onboarding.plan_selected_at',
            'onboarding.website',
            'onboarding.website_submitted_at',
            'onboarding.completed_at',
        ]);
        $metadata['onboarding']['current_step'] = 'domain_step';
        $customer->update(['metadata' => $metadata]);

        return to_route('agency.onboarding.domain')
            ->header('X-Up-Cache', 'false');
    }

    /**
     * Step 1: Show domain selection.
     */
    public function domainStep(): InertiaResponse|RedirectResponse
    {
        $user = auth()->user();
        $customer = $this->resolveCustomer($user);
        $onboarding = $customer?->getMetadata('onboarding', []);

        if (! empty($onboarding['completed_at']) && ($onboarding['current_step'] ?? '') === 'completed') {
            return to_route('agency.websites.index')
                ->with('info', 'Your website is live. Use "Create New Site" to add another.');
        }

        return Inertia::render('agency/onboarding/domain', [
            'savedDomain' => $onboarding['domain'] ?? null,
            'savedDomainType' => $onboarding['domain_type'] ?? 'subdomain',
            'savedDnsMode' => $onboarding['dns_mode'] ?? 'managed',
            'freeSubdomain' => config('agency.free_subdomain', ''),
        ]);
    }

    /**
     * Step 1: Store domain selection.
     */
    public function storeDomain(DomainStepRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $customer = $this->resolveCustomer($user);

        if (! $customer instanceof Customer) {
            return to_route('agency.onboarding.domain')
                ->withErrors(['custom_domain' => 'Customer profile not found.']);
        }

        $fullDomain = $request->resolvedDomain();

        // Check domain not already registered in our system
        $alreadyRegistered = AgencyWebsite::query()
            ->where('domain', $fullDomain)
            ->whereNull('deleted_at')
            ->exists();

        if ($alreadyRegistered) {
            $field = $request->input('domain_type') === 'subdomain' ? 'subdomain' : 'custom_domain';

            return back()
                ->withInput()
                ->withErrors([$field => 'This domain is already registered. Please choose a different one.']);
        }

        // Soft availability check against Platform
        try {
            if (! $this->platformApiClient->checkDomainAvailable($fullDomain)) {
                $field = $request->input('domain_type') === 'subdomain' ? 'subdomain' : 'custom_domain';

                return back()
                    ->withInput()
                    ->withErrors([$field => 'This domain is already in use. Please choose a different one.']);
            }
        } catch (PlatformApiException) {
            // Platform unreachable — proceed; processPayment() will hard-gate before Stripe.
        }

        $metadata = $customer->metadata ?? [];
        $metadata['onboarding'] = array_merge($metadata['onboarding'] ?? [], [
            'domain' => $fullDomain,
            'domain_type' => $request->input('domain_type'),
            'dns_mode' => $request->input('domain_type') === 'subdomain'
                ? 'subdomain'
                : ($request->input('dns_mode') ?? 'managed'),
            'domain_selected_at' => now()->toDateTimeString(),
            'current_step' => 'plan_selection',
        ]);

        $customer->update(['metadata' => $metadata]);

        return to_route('agency.onboarding.plans');
    }

    /**
     * Step 2: Show plan selection.
     */
    public function selectPlan(): InertiaResponse|RedirectResponse
    {
        $user = auth()->user();
        $customer = $this->resolveCustomer($user);
        $onboarding = $customer?->getMetadata('onboarding', []);

        // If a previous onboarding is fully completed and the user hasn't reset
        // the flow via createWebsite() (which resets current_step), redirect to websites.
        if (! empty($onboarding['completed_at']) && ($onboarding['current_step'] ?? '') === 'completed') {
            return to_route('agency.websites.index')
                ->with('info', 'Your website is live. Use "Create New Site" to add another.');
        }

        // Ensure domain was selected first
        if (empty($onboarding['domain'])) {
            return to_route('agency.onboarding.domain')
                ->with('error', 'Please choose a domain first.');
        }

        $plans = [];
        if (module_enabled('subscriptions') && class_exists(Plan::class)) {
            $plans = Plan::query()
                ->where('is_active', true)
                ->with(['prices' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get();
        }

        return Inertia::render('agency/onboarding/plans', [
            'plans' => $plans->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'is_popular' => (bool) $plan->is_popular,
                'trial_days' => (int) ($plan->trial_days ?? 0),
                'features' => $plan->features?->sortBy('sort_order')->map(fn ($feature): array => [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'description' => $feature->description,
                    'formatted_value' => $feature->formatted_value,
                ])->values()->all() ?? [],
                'prices' => $plan->prices->map(fn (PlanPrice $price): array => [
                    'id' => $price->id,
                    'billing_cycle' => $price->billing_cycle,
                    'billing_cycle_label' => $price->billing_cycle_label,
                    'formatted_price' => $price->formatted_price,
                ])->values()->all(),
            ])->values()->all(),
            'selectedPlanId' => $onboarding['selected_plan_id'] ?? null,
            'selectedPlanPriceId' => $onboarding['selected_plan_price_id'] ?? null,
        ]);
    }

    /**
     * Step 2: Store selected plan.
     */
    public function storePlan(): RedirectResponse
    {
        $validated = request()->validate([
            'plan_id' => ['required', 'integer', 'exists:subscriptions_plans,id'],
            'plan_price_id' => ['required', 'integer', 'exists:subscriptions_plan_prices,id'],
        ]);

        $user = auth()->user();
        $customer = $this->resolveCustomer($user);

        if (! $customer instanceof Customer) {
            return to_route('agency.onboarding.plans')
                ->withErrors(['plan_id' => 'Customer profile not found.']);
        }

        $onboarding = $customer->getMetadata('onboarding', []);
        $domain = $onboarding['domain'] ?? '';

        // Website name defaults to the domain chosen in step 1.
        $metadata = $customer->metadata ?? [];
        $metadata['onboarding'] = array_merge($onboarding, [
            'selected_plan_id' => (int) $validated['plan_id'],
            'selected_plan_price_id' => (int) $validated['plan_price_id'],
            'plan_selected_at' => now()->toDateTimeString(),
            'current_step' => 'checkout',
            'website' => [
                'name' => $domain,
                'domain' => $domain,
            ],
            'website_submitted_at' => now()->toDateTimeString(),
        ]);

        $customer->update(['metadata' => $metadata]);

        return to_route('agency.onboarding.checkout');
    }

    /**
     * Step 3: Show checkout/payment page.
     */
    public function checkout(): InertiaResponse|RedirectResponse
    {
        $user = auth()->user();
        $customer = $this->resolveCustomer($user);
        $onboarding = $customer?->getMetadata('onboarding', []);

        if (! empty($onboarding['completed_at']) && ($onboarding['current_step'] ?? '') === 'completed') {
            return to_route('agency.websites.index');
        }

        if (empty($onboarding['selected_plan_id'])) {
            return to_route('agency.onboarding.plans')
                ->with('error', 'Please select a plan first.');
        }

        $selectedPlan = null;
        $selectedPrice = null;
        if (module_enabled('subscriptions') && class_exists(Plan::class) && ! empty($onboarding['selected_plan_id'])) {
            $selectedPlan = Plan::query()->find($onboarding['selected_plan_id']);
            $selectedPrice = PlanPrice::query()->find($onboarding['selected_plan_price_id'] ?? null);
        }

        $isTrial = ($selectedPlan instanceof Plan) && ($selectedPlan->trial_days ?? 0) > 0;

        $stripeConfigured = ! $isTrial
            && filled(config('cashier.key'))
            && filled(config('cashier.secret'));

        return Inertia::render('agency/onboarding/checkout', [
            'selectedPlan' => $selectedPlan ? [
                'id' => $selectedPlan->id,
                'name' => $selectedPlan->name,
                'trial_days' => (int) ($selectedPlan->trial_days ?? 0),
            ] : null,
            'selectedPrice' => $selectedPrice ? [
                'id' => $selectedPrice->id,
                'billing_cycle_label' => $selectedPrice->billing_cycle_label,
                'formatted_price' => $selectedPrice->formatted_price,
                'currency' => $selectedPrice->currency,
            ] : null,
            'websiteDetails' => $onboarding['website'] ?? [],
            'stripeConfigured' => $stripeConfigured,
            'termsUrl' => setting('terms_url') ?: url('/terms'),
            'privacyUrl' => setting('privacy_url') ?: url('/privacy'),
        ]);
    }

    /**
     * Step 4: Process payment and start provisioning.
     */
    public function processPayment(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $customer = $this->resolveCustomer($user);
        $onboarding = $customer?->getMetadata('onboarding', []);

        if (! $customer || empty($onboarding['selected_plan_id']) || empty($onboarding['website'])) {
            return to_route('agency.onboarding.plans')
                ->with('error', 'Please complete all onboarding steps.');
        }

        $websiteDetails = $onboarding['website'];

        $selectedPlan = (module_enabled('subscriptions') && class_exists(Plan::class))
            ? Plan::query()->find($onboarding['selected_plan_id'])
            : null;

        $isTrial = ($selectedPlan instanceof Plan) && ($selectedPlan->trial_days ?? 0) > 0;

        // For paid plans: create a Stripe Checkout Session and redirect to Stripe's
        // hosted payment page. No card details ever touch our server.
        if (! $isTrial) {
            if (blank(config('cashier.key')) || blank(config('cashier.secret'))) {
                return back()->withErrors([
                    'payment' => 'Online payment collection is not yet enabled. Please contact support.',
                ]);
            }

            $selectedPrice = $selectedPlan instanceof Plan
                ? PlanPrice::query()->find($onboarding['selected_plan_price_id'] ?? null)
                : null;

            if (! $selectedPrice instanceof PlanPrice) {
                return back()->withErrors([
                    'payment' => 'No pricing option found for the selected plan. Please go back and re-select your plan.',
                ]);
            }

            $websiteDomain = $websiteDetails['domain'] ?? '';
            $websiteName = $websiteDetails['name'] ?? '';

            // Hard domain gate: verify the domain is still free on Platform immediately
            // before creating the Stripe session. We must never charge for something
            // we cannot provision.
            try {
                if (! $this->platformApiClient->checkDomainAvailable($websiteDomain)) {
                    return back()->withErrors([
                        'payment' => sprintf('The domain "%s" is already in use. Please go back and choose a different domain.', $websiteDomain),
                    ]);
                }
            } catch (PlatformApiException $domainCheckException) {
                Log::error('processPayment: domain availability check failed', [
                    'domain' => $websiteDomain,
                    'error' => $domainCheckException->getMessage(),
                ]);

                return back()->withErrors([
                    'payment' => 'Could not verify domain availability. Please try again in a moment.',
                ]);
            }

            // --- Coupon Validation ---
            $couponCode = strtoupper(trim((string) $request->input('coupon_code', '')));
            $discountAmount = 0.0;
            $validatedCouponId = null;
            $subtotal = (float) $selectedPrice->price;

            if ($couponCode !== '' && module_enabled('billing') && class_exists(CouponService::class)) {
                /** @var CouponService $couponServiceInstance */
                $couponServiceInstance = resolve(CouponService::class);
                $couponResult = $couponServiceInstance->validate(
                    $couponCode,
                    $customer->id,
                    $subtotal,
                    (int) ($selectedPlan->id ?? 0) ?: null,
                );

                if ($couponResult->valid) {
                    $discountAmount = $couponResult->discount;
                    $validatedCouponId = $couponResult->coupon->id;
                } else {
                    return back()->withErrors(['coupon_code' => $couponResult->error]);
                }
            }

            $finalAmount = max(0.0, $subtotal - $discountAmount);

            // --- Create a pending Order before redirecting to Stripe ---
            /** @var OrderService|null $orderService */
            $orderService = (module_enabled('orders') && class_exists(OrderService::class))
                ? resolve(OrderService::class)
                : null;

            $pendingOrder = null;
            if ($orderService !== null) {
                $planLabel = ($selectedPlan->name ?? 'Plan').' — '.$selectedPrice->billing_cycle_label;
                $pendingOrder = $orderService->createFromCheckout([
                    'customer_id' => $customer->id,
                    'type' => Order::TYPE_SUBSCRIPTION_SIGNUP,
                    'currency' => strtolower((string) $selectedPrice->currency),
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => 0,
                    'total' => $finalAmount,
                    'coupon_id' => $validatedCouponId,
                    'coupon_code' => $couponCode !== '' ? $couponCode : null,
                    'items' => [[
                        'plan_id' => $selectedPlan->id ?? null,
                        'name' => $planLabel,
                        'description' => $websiteDomain,
                        'quantity' => 1,
                        'unit_price' => $subtotal,
                        'total' => $subtotal,
                    ]],
                ]);
            }

            // --- 100% discount: skip Stripe, provision directly ---
            if ($finalAmount <= 0) {
                // Provision directly — same flow as the trial path, without Stripe.
                // NOTE: markPaid + coupon redemption are deferred into the DB::transaction below so
                // that a provisioning API failure leaves the order unpaid and the coupon unredeemed.
                try {
                    $apiResponse = $this->platformApiClient->createWebsite([
                        'domain' => $websiteDetails['domain'],
                        'name' => $websiteDetails['name'],
                        'dns_mode' => $onboarding['dns_mode'] ?? 'subdomain',
                        'customer' => [
                            'email' => $user->email,
                            'name' => $user->name,
                        ],
                        'meta' => [
                            'plan_id' => $selectedPlan instanceof Plan ? (string) $selectedPlan->id : null,
                            'plan_name' => $selectedPlan instanceof Plan ? $selectedPlan->name : null,
                            'plan_type' => 'paid',
                            'trial_days' => 0,
                            'price_id' => $selectedPrice?->id ? (string) $selectedPrice->id : null,
                            'billing_cycle' => $selectedPrice?->billing_cycle ?? null,
                            'price' => $selectedPrice?->price ?? null,
                            'currency' => $selectedPrice?->currency ?? null,
                        ],
                    ]);

                    $websiteData = $apiResponse['data'];
                    $websiteData['dns_mode'] = $onboarding['dns_mode'] ?? 'subdomain';
                    $provisioningWebsiteId = DB::transaction(function () use ($customer, $user, $websiteData, $selectedPlan, $selectedPrice, $pendingOrder, $orderService, $couponCode, $validatedCouponId, $discountAmount): ?int {
                        $provisioningWebsite = null;

                        if (! empty($websiteData['site_id'])) {
                            $provisioningWebsite = AgencyWebsite::syncFromApi($websiteData, $user->id);

                            if ($provisioningWebsite && $selectedPlan instanceof Plan) {
                                $provisioningWebsite->update([
                                    'plan' => $selectedPlan->name,
                                    'plan_ref' => (string) $selectedPlan->id,
                                    'plan_data' => [
                                        'id' => $selectedPlan->id,
                                        'code' => $selectedPlan->code,
                                        'name' => $selectedPlan->name,
                                        'type' => 'paid',
                                        'trial_days' => 0,
                                        'price_id' => $selectedPrice?->id,
                                        'billing_cycle' => $selectedPrice?->billing_cycle ?? null,
                                        'price' => $selectedPrice?->price ?? null,
                                        'currency' => $selectedPrice?->currency ?? null,
                                    ],
                                ]);
                            }
                        }

                        if ($selectedPlan instanceof Plan && module_enabled('subscriptions')) {
                            $this->subscriptionService->subscribeCustomer(
                                $customer->id,
                                $selectedPlan->id,
                                [
                                    'plan_price_id' => $selectedPrice?->id,
                                    'metadata' => [
                                        'onboarding' => true,
                                        'site_id' => $websiteData['site_id'] ?? null,
                                        'coupon_full_discount' => true,
                                    ],
                                ]
                            );
                        }

                        $meta = $customer->metadata ?? [];
                        $meta['onboarding'] = array_merge($meta['onboarding'] ?? [], [
                            'payment_completed_at' => now()->toDateTimeString(),
                            'current_step' => 'provisioning',
                            'last_site_id' => $provisioningWebsite?->id,
                        ]);
                        unset(
                            $meta['onboarding']['selected_plan_id'],
                            $meta['onboarding']['selected_plan_price_id'],
                            $meta['onboarding']['plan_selected_at'],
                            $meta['onboarding']['website'],
                            $meta['onboarding']['website_submitted_at'],
                            $meta['onboarding']['stripe_checkout_session_id'],
                            $meta['onboarding']['pending_order_id'],
                        );
                        $customer->update(['metadata' => $meta]);

                        // Mark order as paid and redeem the coupon atomically with provisioning.
                        // This ensures a provisioning API failure cannot leave the order paid
                        // or the coupon consumed without a working website.
                        if ($pendingOrder instanceof Order && $orderService instanceof OrderService) {
                            $orderService->markPaid($pendingOrder, 'coupon_full_discount');

                            if ($couponCode !== '' && $validatedCouponId
                                && module_enabled('billing') && class_exists(CouponService::class)) {
                                /** @var CouponService $couponSvc */
                                $couponSvc = resolve(CouponService::class);
                                $coupon = Coupon::query()->find($validatedCouponId);
                                if ($coupon) {
                                    $couponSvc->redeem($coupon, $customer->id, $pendingOrder->id, $discountAmount);
                                }
                            }
                        }

                        return $provisioningWebsite?->id;
                    });

                    $redirect = $provisioningWebsiteId
                        ? to_route('agency.onboarding.provisioning.website', $provisioningWebsiteId)
                        : to_route('agency.onboarding.provisioning');

                    return $redirect->with('success', 'Coupon applied — full discount. We are now provisioning your website.');
                } catch (PlatformApiException $e) {
                    Log::error('Zero-amount checkout: Platform API provisioning error', [
                        'customer_id' => $customer->id,
                        'status_code' => $e->statusCode,
                        'message' => $e->getMessage(),
                        'body' => $e->body,
                    ]);

                    return back()->withErrors(['payment' => 'Website provisioning failed: '.$e->getMessage()]);
                } catch (Throwable $ex) {
                    Log::error('Zero-amount checkout: provisioning failed', [
                        'exception' => $ex->getMessage(),
                        'customer_id' => $customer->id,
                    ]);

                    return back()->withErrors(['payment' => 'Setup failed. Please contact support.']);
                }
            }

            try {
                StripeClient::setApiKey(config('cashier.secret'));

                // Build a single line item at the final (discounted) amount.
                // Stripe Checkout does not support negative unit_amount values,
                // so the discount is reflected in the price, not as a separate line.
                $productDescription = implode(' · ', array_filter([
                    $websiteName ?: null,
                    $websiteDomain ? 'https://'.$websiteDomain : null,
                ]));
                if ($discountAmount > 0) {
                    $productDescription .= ' · Discount: -'.number_format($discountAmount, 2).' ('.$couponCode.')';
                }

                $lineItems = [[
                    'price_data' => [
                        'currency' => strtolower((string) $selectedPrice->currency),
                        'product_data' => [
                            'name' => ($selectedPlan->name ?? 'Plan').' — '.$selectedPrice->billing_cycle_label,
                            'description' => $productDescription,
                        ],
                        'unit_amount' => (int) round($finalAmount * 100),
                    ],
                    'quantity' => 1,
                ]];

                $stripeMetadata = [
                    'customer_id' => (string) $customer->id,
                    'plan_id' => (string) ($selectedPlan->id ?? ''),
                    'plan_price_id' => (string) $selectedPrice->id,
                    'website_domain' => $websiteDomain,
                    'website_name' => $websiteName,
                ];

                if ($couponCode !== '') {
                    $stripeMetadata['coupon_code'] = $couponCode;
                    $stripeMetadata['discount_amount'] = (string) $discountAmount;
                }

                if ($pendingOrder !== null) {
                    $stripeMetadata['order_id'] = (string) $pendingOrder->id;
                }

                $session = StripeCheckoutSession::create([
                    'mode' => 'payment',
                    'customer_email' => $user->email,
                    'line_items' => $lineItems,
                    'custom_text' => [
                        'submit' => [
                            'message' => 'Your website ('.$websiteDomain.') will be provisioned automatically as soon as payment is confirmed.',
                        ],
                    ],
                    'success_url' => route('agency.onboarding.checkout.stripe.success').'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('agency.onboarding.checkout.stripe.cancel'),
                    'metadata' => $stripeMetadata,
                ]);

                // Persist the session ID for idempotency and order tracking.
                $metadata = $customer->metadata ?? [];
                $metadata['onboarding']['stripe_checkout_session_id'] = $session->id;
                if ($pendingOrder !== null) {
                    $metadata['onboarding']['pending_order_id'] = $pendingOrder->id;
                }

                $customer->update(['metadata' => $metadata]);

                // Transition order to processing now that we have the Stripe session ID.
                if ($pendingOrder !== null && $orderService !== null) {
                    $orderService->markPendingPayment($pendingOrder, $session->id);
                }

                return redirect((string) $session->url);
            } catch (StripeApiErrorException $e) {
                Log::error('Failed to create Stripe Checkout Session', [
                    'error' => $e->getMessage(),
                    'customer_id' => $customer->id,
                ]);

                return back()->withErrors([
                    'payment' => 'Could not initiate payment: '.$e->getMessage(),
                ]);
            }
        }

        // --- Create order for trial signup (must persist even if provisioning fails) ---
        $selectedPrice = isset($onboarding['selected_plan_price_id'])
            ? PlanPrice::query()->find((int) $onboarding['selected_plan_price_id'])
            : null;

        /** @var OrderService|null $orderServiceTrial */
        $orderServiceTrial = (module_enabled('orders') && class_exists(OrderService::class))
            ? resolve(OrderService::class)
            : null;

        if ($orderServiceTrial !== null && $selectedPlan instanceof Plan) {
            $planLabel = $selectedPlan->name.' — '.($selectedPrice?->billing_cycle_label ?? 'Trial');
            $trialOrder = $orderServiceTrial->createFromCheckout([
                'customer_id' => $customer->id,
                'type' => Order::TYPE_SUBSCRIPTION_SIGNUP,
                'currency' => strtolower((string) ($selectedPrice?->currency ?? 'usd')),
                'subtotal' => (float) ($selectedPrice?->price ?? 0),
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'notes' => 'Free trial — '.$selectedPlan->trial_days.' days',
                'metadata' => ['trial' => true, 'trial_days' => $selectedPlan->trial_days ?? 0],
                'items' => [[
                    'plan_id' => $selectedPlan->id,
                    'name' => $planLabel,
                    'description' => ($websiteDetails['domain'] ?? '').' (free trial)',
                    'quantity' => 1,
                    'unit_price' => 0,
                    'total' => 0,
                ]],
            ]);
            // Mark immediately paid since no payment is required for a trial.
            $orderServiceTrial->markPaid($trialOrder, 'free_trial');
        }

        try {
            // 1. Call Platform API to provision the website.
            // 'type' and billing enforcement are managed by the Agency module;
            // we pass plan/billing info as opaque `meta` for Platform to store.
            $apiResponse = $this->platformApiClient->createWebsite([
                'domain' => $websiteDetails['domain'],
                'name' => $websiteDetails['name'],
                'dns_mode' => $onboarding['dns_mode'] ?? 'subdomain',
                'customer' => [
                    'email' => $user->email,
                    'name' => $user->name,
                ],
                'meta' => [
                    'plan_id' => $selectedPlan instanceof Plan ? (string) $selectedPlan->id : null,
                    'plan_name' => $selectedPlan instanceof Plan ? $selectedPlan->name : null,
                    'plan_type' => $isTrial ? 'trial' : 'paid',
                    'trial_days' => $selectedPlan instanceof Plan ? ($selectedPlan->trial_days ?? 0) : 0,
                    'price_id' => $selectedPrice?->id ? (string) $selectedPrice->id : null,
                    'billing_cycle' => $selectedPrice?->billing_cycle ?? null,
                    'price' => $selectedPrice?->price ?? null,
                    'currency' => $selectedPrice?->currency ?? null,
                ],
            ]);

            $websiteData = $apiResponse['data'];
            $websiteData['dns_mode'] = $onboarding['dns_mode'] ?? 'subdomain';

            // 2. Create local records and subscription inside a transaction
            $provisioningWebsiteId = DB::transaction(function () use ($customer, $user, $websiteData, $selectedPlan, $selectedPrice, $isTrial): ?int {
                $provisioningWebsite = null;

                // Create the local website record
                if (! empty($websiteData['site_id'])) {
                    $provisioningWebsite = AgencyWebsite::syncFromApi(
                        $websiteData,
                        $user->id
                    );

                    // Save plan data locally (Platform API doesn't return plan)
                    if ($provisioningWebsite && $selectedPlan instanceof Plan) {
                        $provisioningWebsite->update([
                            'plan' => $selectedPlan->name,
                            'plan_ref' => (string) $selectedPlan->id,
                            'plan_data' => [
                                'id' => $selectedPlan->id,
                                'code' => $selectedPlan->code,
                                'name' => $selectedPlan->name,
                                'type' => $isTrial ? 'trial' : 'paid',
                                'trial_days' => $selectedPlan->trial_days ?? 0,
                                'price_id' => $selectedPrice?->id,
                                'billing_cycle' => $selectedPrice?->billing_cycle ?? null,
                                'price' => $selectedPrice?->price ?? null,
                                'currency' => $selectedPrice?->currency ?? null,
                            ],
                        ]);
                    }
                }

                // Create subscription record
                if (module_enabled('subscriptions')) {
                    $subscription = $this->subscriptionService->subscribeCustomer(
                        $customer->id,
                        $selectedPlan->id,
                        [
                            'plan_price_id' => $selectedPrice?->id,
                            'metadata' => [
                                'onboarding' => true,
                                'site_id' => $websiteData['site_id'] ?? null,
                            ],
                        ]
                    );
                }

                // Mark onboarding as provisioning and clear website-creation session
                $metadata = $customer->metadata ?? [];
                $metadata['onboarding'] = array_merge($metadata['onboarding'] ?? [], [
                    'payment_completed_at' => now()->toDateTimeString(),
                    'current_step' => 'provisioning',
                    'last_site_id' => $provisioningWebsite?->id,
                ]);
                // Clear current flow so user can create another website
                unset(
                    $metadata['onboarding']['selected_plan_id'],
                    $metadata['onboarding']['selected_plan_price_id'],
                    $metadata['onboarding']['plan_selected_at'],
                    $metadata['onboarding']['website'],
                    $metadata['onboarding']['website_submitted_at'],
                );
                $customer->update(['metadata' => $metadata]);

                return $provisioningWebsite?->id;
            });

            $redirect = $provisioningWebsiteId
                ? to_route('agency.onboarding.provisioning.website', $provisioningWebsiteId)
                : to_route('agency.onboarding.provisioning');

            return $redirect->with('success', $isTrial
                ? 'Free trial started. We are now provisioning your website.'
                : 'Payment received. We are now provisioning your website.');
        } catch (PlatformApiException $e) {
            Log::error('Agency provisioning failed — Platform API error', [
                'customer_id' => $customer->id,
                'status_code' => $e->statusCode,
                'message' => $e->getMessage(),
                'body' => $e->body,
            ]);

            return back()->withErrors([
                'payment' => 'Website provisioning failed: '.$e->getMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Agency checkout failed', [
                'exception' => $exception->getMessage(),
                'customer_id' => $customer->id,
            ]);

            return back()->withErrors([
                'payment' => 'Payment processing failed. Please try again.',
            ]);
        }
    }

    /**
     * Stripe Checkout hosted page — success return URL.
     * Stripe appends ?session_id=cs_xxx. We retrieve the session, verify it is
     * paid, then provision the website and subscription exactly as we would for
     * a trial, but mark the subscription with the Stripe checkout session ID.
     */
    public function stripeSuccess(Request $request): RedirectResponse
    {
        $sessionId = $request->query('session_id');

        if (empty($sessionId)) {
            return to_route('agency.onboarding.checkout')
                ->withErrors(['payment' => 'Missing payment session. Please contact support if you were charged.']);
        }

        $user = $request->user();
        $customer = $this->resolveCustomer($user);

        if (! $customer instanceof Customer) {
            return to_route('agency.onboarding.checkout')
                ->withErrors(['payment' => 'Customer record not found. Please contact support.']);
        }

        // Idempotency: if this exact Stripe session was already processed, don't provision twice.
        // Keyed on session_id so that new orders (new session_id) always proceed normally.
        $metadata = $customer->metadata ?? [];
        if ($sessionId === ($metadata['onboarding']['last_paid_stripe_session_id'] ?? null)) {
            $rawSiteId = $metadata['onboarding']['last_site_id'] ?? null;

            // Resolve to a local integer id (route requires a numeric param).
            // New format: integer local id. Legacy format: platform site_id string.
            $localWebsiteId = null;
            if (is_int($rawSiteId) && $rawSiteId > 0) {
                $localWebsiteId = $rawSiteId;
            } elseif (is_string($rawSiteId) && $rawSiteId !== '') {
                $localWebsiteId = AgencyWebsite::query()
                    ->where('owner_id', $user->id)
                    ->where('site_id', $rawSiteId)
                    ->value('id');
            }

            return $localWebsiteId
                ? to_route('agency.onboarding.provisioning.website', $localWebsiteId)
                : to_route('agency.onboarding.provisioning');
        }

        // Security: verify the incoming session_id matches the one we stored during checkout creation.
        // This prevents an attacker from using another customer's paid Checkout Session ID.
        $expectedSessionId = $metadata['onboarding']['stripe_checkout_session_id'] ?? null;
        if ($expectedSessionId !== null && $sessionId !== $expectedSessionId) {
            Log::warning('stripeSuccess: session_id mismatch', [
                'incoming' => $sessionId,
                'expected' => $expectedSessionId,
                'customer_id' => $customer->id,
                'user_id' => $user->id,
            ]);

            return to_route('agency.onboarding.checkout')
                ->withErrors(['payment' => 'Payment session mismatch. Please contact support if you were charged.']);
        }

        $onboarding = $metadata['onboarding'] ?? [];

        $selectedPlan = isset($onboarding['selected_plan_id'])
            ? Plan::query()->find((int) $onboarding['selected_plan_id'])
            : null;

        $websiteDetails = $onboarding['website'] ?? [];

        try {
            StripeClient::setApiKey(config('cashier.secret'));
            $session = StripeCheckoutSession::retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                return to_route('agency.onboarding.checkout')
                    ->withErrors(['payment' => 'Payment is not yet confirmed. Please wait a moment and try again.']);
            }

            // Verify the Stripe session's metadata customer_id matches the current customer
            $stripeCustomerId = $session->metadata['customer_id'] ?? null;
            if ($stripeCustomerId !== null && (int) $stripeCustomerId !== $customer->id) {
                Log::warning('stripeSuccess: Stripe session customer_id mismatch', [
                    'session_customer_id' => $stripeCustomerId,
                    'local_customer_id' => $customer->id,
                    'session_id' => $sessionId,
                ]);

                return to_route('agency.onboarding.checkout')
                    ->withErrors(['payment' => 'Payment session does not belong to your account. Please contact support.']);
            }
        } catch (StripeApiErrorException $stripeApiErrorException) {
            Log::error('stripeSuccess: failed to retrieve Checkout Session', [
                'session_id' => $sessionId,
                'error' => $stripeApiErrorException->getMessage(),
                'customer_id' => $customer->id,
            ]);

            return to_route('agency.onboarding.checkout')
                ->withErrors(['payment' => 'Could not verify payment status. Please contact support.']);
        }

        // --- Locate the pending Order created during checkout (if Orders module is enabled) ---
        /** @var OrderService|null $orderService */
        $orderService = (module_enabled('orders') && class_exists(OrderService::class))
            ? resolve(OrderService::class)
            : null;

        $pendingOrder = ($orderService !== null)
            ? Order::query()->where('stripe_checkout_session_id', $sessionId)->first()
            : null;

        try {
            // 'type' and billing enforcement are managed by the Agency module;
            // send plan/billing info as opaque `meta` for Platform to store.
            $selectedPrice = isset($onboarding['selected_plan_price_id'])
                ? PlanPrice::query()->find((int) $onboarding['selected_plan_price_id'])
                : null;

            $apiResponse = $this->platformApiClient->createWebsite([
                'domain' => $websiteDetails['domain'],
                'name' => $websiteDetails['name'],
                'dns_mode' => $onboarding['dns_mode'] ?? 'subdomain',
                'customer' => [
                    'email' => $user->email,
                    'name' => $user->name,
                ],
                'meta' => [
                    'plan_id' => $selectedPlan instanceof Plan ? (string) $selectedPlan->id : null,
                    'plan_name' => $selectedPlan instanceof Plan ? $selectedPlan->name : null,
                    'plan_type' => 'paid',
                    'trial_days' => 0,
                    'price_id' => $selectedPrice?->id ? (string) $selectedPrice->id : null,
                    'billing_cycle' => $selectedPrice?->billing_cycle ?? null,
                    'price' => $selectedPrice?->price ?? null,
                    'currency' => $selectedPrice?->currency ?? null,
                ],
            ]);

            $websiteData = $apiResponse['data'];
            $websiteData['dns_mode'] = $onboarding['dns_mode'] ?? 'subdomain';

            $provisioningWebsiteId = DB::transaction(function () use ($customer, $user, $websiteData, $selectedPlan, $selectedPrice, $sessionId, $pendingOrder, $orderService, $session): ?int {
                $provisioningWebsite = null;

                if (! empty($websiteData['site_id'])) {
                    $provisioningWebsite = AgencyWebsite::syncFromApi($websiteData, $user->id);

                    // Save plan data locally (Platform API doesn't return plan)
                    if ($provisioningWebsite && $selectedPlan instanceof Plan) {
                        $provisioningWebsite->update([
                            'plan' => $selectedPlan->name,
                            'plan_ref' => (string) $selectedPlan->id,
                            'plan_data' => [
                                'id' => $selectedPlan->id,
                                'code' => $selectedPlan->code,
                                'name' => $selectedPlan->name,
                                'type' => 'paid',
                                'trial_days' => 0,
                                'price_id' => $selectedPrice?->id,
                                'billing_cycle' => $selectedPrice?->billing_cycle ?? null,
                                'price' => $selectedPrice?->price ?? null,
                                'currency' => $selectedPrice?->currency ?? null,
                            ],
                        ]);
                    }
                }

                if ($selectedPlan instanceof Plan && module_enabled('subscriptions')) {
                    $subscription = $this->subscriptionService->subscribeCustomer(
                        $customer->id,
                        $selectedPlan->id,
                        [
                            'plan_price_id' => $selectedPrice?->id,
                            'metadata' => [
                                'onboarding' => true,
                                'site_id' => $websiteData['site_id'] ?? null,
                                'stripe_checkout_session_id' => $sessionId,
                            ],
                        ]
                    );
                }

                // Invoice + payment record creation is handled by
                // Billing\Listeners\CreateInvoiceForOrder which fires when
                // OrderService::markPaid() dispatches the OrderPaid event below.

                // Mark the Order paid and fire the OrderPaid event.
                if ($pendingOrder instanceof Order && $orderService instanceof OrderService) {
                    $orderService->markPaid($pendingOrder, (string) ($session->payment_intent ?? ''));

                    // Redeem the coupon now that payment is confirmed.
                    if ($pendingOrder->coupon_code && $pendingOrder->coupon_id
                        && module_enabled('billing') && class_exists(CouponService::class)) {
                        /** @var CouponService $couponServiceInstance */
                        $couponServiceInstance = resolve(CouponService::class);
                        $coupon = Coupon::query()->find($pendingOrder->coupon_id);
                        if ($coupon) {
                            $couponServiceInstance->redeem($coupon, $customer->id, $pendingOrder->id, (float) $pendingOrder->discount_amount);
                        }
                    }
                }

                $meta = $customer->metadata ?? [];
                $meta['onboarding'] = array_merge($meta['onboarding'] ?? [], [
                    'payment_completed_at' => now()->toDateTimeString(),
                    'last_paid_stripe_session_id' => $sessionId,
                    'current_step' => 'provisioning',
                    'last_site_id' => $provisioningWebsite?->id,
                ]);
                unset(
                    $meta['onboarding']['selected_plan_id'],
                    $meta['onboarding']['selected_plan_price_id'],
                    $meta['onboarding']['plan_selected_at'],
                    $meta['onboarding']['website'],
                    $meta['onboarding']['website_submitted_at'],
                    $meta['onboarding']['stripe_checkout_session_id'],
                    $meta['onboarding']['pending_order_id'],
                );
                $customer->update(['metadata' => $meta]);

                return $provisioningWebsite?->id;
            });

            $redirect = $provisioningWebsiteId
                ? to_route('agency.onboarding.provisioning.website', $provisioningWebsiteId)
                : to_route('agency.onboarding.provisioning');

            return $redirect->with('success', 'Payment received. We are now provisioning your website.');
        } catch (PlatformApiException $e) {
            Log::error('stripeSuccess: Platform API provisioning error', [
                'customer_id' => $customer->id,
                'status_code' => $e->statusCode,
                'message' => $e->getMessage(),
                'body' => $e->body,
            ]);

            return to_route('agency.onboarding.checkout')
                ->withErrors(['payment' => 'Payment was received but website provisioning failed: '.$e->getMessage()]);
        } catch (Throwable $exception) {
            Log::error('stripeSuccess: provisioning failed', [
                'exception' => $exception->getMessage(),
                'customer_id' => $customer->id,
                'session_id' => $sessionId,
            ]);

            return to_route('agency.onboarding.checkout')
                ->withErrors(['payment' => 'Payment received but setup failed. Please contact support.']);
        }
    }

    /**
     * Stripe Checkout hosted page — cancel return URL.
     * Redirects back to the checkout step with an informational message.
     */
    public function stripeCancel(): RedirectResponse
    {
        return to_route('agency.onboarding.checkout')
            ->with('warning', 'Payment was cancelled. You can try again whenever you are ready.');
    }

    /**
     * AJAX: validate a coupon code at checkout.
     * Returns discount info so Alpine.js can render the discount row immediately.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        if (! module_enabled('billing') || ! class_exists(CouponService::class)) {
            return response()->json(['valid' => false, 'message' => 'Coupons are not available.'], 422);
        }

        $request->validate(['coupon_code' => ['required', 'string', 'max:50']]);

        $customer = $this->resolveCustomer($request->user());

        if (! $customer instanceof Customer) {
            return response()->json(['valid' => false, 'message' => 'Customer record not found.'], 422);
        }

        $onboarding = $customer->getMetadata('onboarding', []);

        $selectedPrice = isset($onboarding['selected_plan_price_id'])
            ? PlanPrice::query()->find((int) $onboarding['selected_plan_price_id'])
            : null;

        $subtotal = (float) ($selectedPrice?->price ?? 0);
        $planId = isset($onboarding['selected_plan_id']) ? (int) $onboarding['selected_plan_id'] : null;

        /** @var CouponService $couponService */
        $couponService = resolve(CouponService::class);
        $result = $couponService->validate(
            strtoupper(trim((string) $request->input('coupon_code', ''))),
            $customer->id,
            $subtotal,
            $planId,
        );

        if ($result->valid) {
            $newTotal = max(0.0, $subtotal - $result->discount);

            return response()->json([
                'valid' => true,
                'discount_amount' => $result->discount,
                'discount_formatted' => number_format($result->discount, 2),
                'new_total' => $newTotal,
                'new_total_formatted' => number_format($newTotal, 2),
                'currency' => strtoupper((string) ($selectedPrice?->currency ?? '')),
                'message' => 'Coupon applied!',
            ]);
        }

        return response()->json(['valid' => false, 'message' => $result->error], 422);
    }

    /**
     * Step 5: Provisioning status page.
     */
    public function provisioning(?int $website = null): InertiaResponse|RedirectResponse
    {
        $user = auth()->user();
        $customer = $this->resolveCustomer($user);
        $provisioningWebsite = $this->resolveProvisioningWebsite($user, $customer, $website);

        if (! $provisioningWebsite instanceof AgencyWebsite) {
            return to_route('agency.websites.index')
                ->with('warning', 'No active provisioning session was found.');
        }

        if ($website === null) {
            return to_route('agency.onboarding.provisioning.website', $provisioningWebsite->id);
        }

        $platformProvisioning = $this->fetchPlatformProvisioningData($provisioningWebsite->site_id);
        $statusData = $this->buildProvisioningStatusData($provisioningWebsite, $platformProvisioning);
        $this->syncOnboardingCompletion($customer, $statusData['status']);

        return Inertia::render('agency/onboarding/provisioning', [
            'website' => [
                'id' => $provisioningWebsite->id,
                'name' => $provisioningWebsite->name ?? $provisioningWebsite->domain,
                'domain' => $provisioningWebsite->domain,
            ],
            'statusData' => $statusData,
            'statusUrl' => route('agency.onboarding.provisioning.website.status', $provisioningWebsite->id),
        ]);
    }

    /**
     * Polling endpoint for provisioning updates.
     */
    public function provisioningStatus(?int $website = null): JsonResponse
    {
        $user = auth()->user();
        $customer = $this->resolveCustomer($user);
        $provisioningWebsite = $this->resolveProvisioningWebsite($user, $customer, $website);

        if (! $provisioningWebsite instanceof AgencyWebsite) {
            return response()->json([
                'status' => 'unknown',
                'status_label' => 'Unknown',
                'headline' => 'Provisioning session not found',
                'detail' => 'We could not find an active provisioning session for your account.',
                'is_complete' => false,
                'is_failed' => true,
                'timeline' => [],
                'next_actions' => [
                    'Go back to websites and start a new setup if needed.',
                ],
                'manage_url' => route('agency.websites.index'),
                'steps' => [],
                'email_step' => null,
                'progress' => [
                    'total_steps' => 0,
                    'completed_steps' => 0,
                    'failed_steps' => 0,
                    'in_progress_steps' => 0,
                    'pending_steps' => 0,
                    'percentage' => 0,
                ],
            ], 404)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $platformProvisioning = $this->fetchPlatformProvisioningData($provisioningWebsite->site_id);
        $statusData = $this->buildProvisioningStatusData($provisioningWebsite, $platformProvisioning);
        $this->syncOnboardingCompletion($customer, $statusData['status']);

        return response()
            ->json($statusData)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Confirm that the user has updated their DNS records.
     *
     * POST /onboarding/provisioning/{website}/confirm-dns
     *
     * Sends confirmation to Platform so polling checks begin.
     */
    public function confirmDns(int $website): JsonResponse
    {
        $user = auth()->user();
        $customer = $this->resolveCustomer($user);
        $provisioningWebsite = $this->resolveProvisioningWebsite($user, $customer, $website);

        if (! $provisioningWebsite instanceof AgencyWebsite) {
            return response()->json(['message' => 'Website not found.'], 404);
        }

        if ($provisioningWebsite->status !== WebsiteStatus::WaitingForDns) {
            return response()->json(['message' => 'Website is not waiting for DNS verification.'], 400);
        }

        try {
            $platformApi = app(PlatformApiClient::class);
            $result = $platformApi->confirmDns($provisioningWebsite->site_id);

            return response()->json([
                'message' => $result['message'] ?? 'DNS confirmation received. Verification checks will begin shortly.',
                'confirmed' => true,
            ]);
        } catch (PlatformApiException $e) {
            Log::warning('Failed to confirm DNS with Platform API', [
                'website_id' => $provisioningWebsite->id,
                'site_id' => $provisioningWebsite->site_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to confirm DNS update. Please try again.',
            ], 500);
        }
    }

    /**
     * Legacy wizard endpoint (redirects to proper step).
     */
    public function wizard(): RedirectResponse
    {
        $user = auth()->user();
        $customer = $user ? $this->resolveCustomer($user) : null;
        $onboarding = $customer?->getMetadata('onboarding', []);

        if (! empty($onboarding['completed_at'])) {
            return to_route('agency.websites.index');
        }

        // Redirect to the appropriate step based on progress
        $currentStep = $onboarding['current_step'] ?? 'plan_selection';

        return match ($currentStep) {
            'checkout' => to_route('agency.onboarding.checkout'),
            'provisioning' => (($website = $this->resolveProvisioningWebsite($user, $customer)) instanceof AgencyWebsite)
                ? to_route('agency.onboarding.provisioning.website', $website->id)
                : to_route('agency.onboarding.provisioning'),
            default => to_route('agency.onboarding.plans'),
        };
    }

    /**
     * Legacy complete endpoint (redirects to proper step).
     */
    public function complete(): RedirectResponse
    {
        return $this->wizard();
    }

    private function resolveProvisioningWebsite(User $user, ?Customer $customer, ?int $websiteId = null): ?AgencyWebsite
    {
        $allowedStatuses = [
            WebsiteStatus::Provisioning->value,
            WebsiteStatus::WaitingForDns->value,
            WebsiteStatus::Active->value,
            WebsiteStatus::Failed->value,
        ];

        if ($websiteId !== null) {
            return AgencyWebsite::query()
                ->where('owner_id', $user->id)
                ->where('id', $websiteId)
                ->whereIn('status', $allowedStatuses)
                ->first();
        }

        $onboarding = $customer?->getMetadata('onboarding', []);
        $lastSiteId = is_array($onboarding) ? ($onboarding['last_site_id'] ?? null) : null;

        if (is_int($lastSiteId) && $lastSiteId > 0) {
            // New format: stores local AgencyWebsite integer id
            $website = AgencyWebsite::query()
                ->where('owner_id', $user->id)
                ->where('id', $lastSiteId)
                ->whereIn('status', $allowedStatuses)
                ->first();

            if ($website) {
                return $website;
            }
        } elseif (is_string($lastSiteId) && $lastSiteId !== '') {
            // Legacy format: stores platform site_id string
            $website = AgencyWebsite::query()
                ->where('owner_id', $user->id)
                ->where('site_id', $lastSiteId)
                ->whereIn('status', $allowedStatuses)
                ->first();

            if ($website) {
                return $website;
            }
        }

        return AgencyWebsite::query()
            ->where('owner_id', $user->id)
            ->whereIn('status', $allowedStatuses)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProvisioningStatusData(AgencyWebsite $website, ?array $platformProvisioning = null): array
    {
        $localStatus = $website->status;

        $platformStatusValue = is_string($platformProvisioning['website_status'] ?? null)
            ? $platformProvisioning['website_status']
            : null;

        $status = WebsiteStatus::tryFrom($platformStatusValue ?? '') ?? $localStatus;

        $summary = match ($status) {
            WebsiteStatus::Active => [
                'headline' => 'Your website is ready',
                'detail' => 'Provisioning is complete. You can now open and manage your website.',
            ],
            WebsiteStatus::Failed => [
                'headline' => 'We hit a setup issue',
                'detail' => 'Provisioning did not finish successfully. We can help you resolve it quickly.',
            ],
            WebsiteStatus::WaitingForDns => [
                'headline' => 'Action required: Update your DNS',
                'detail' => 'Your server is ready. Please add the DNS records below to continue setup.',
            ],
            default => [
                'headline' => 'We are provisioning your website',
                'detail' => 'This usually takes a few minutes. Keep this page open while we refresh the latest status.',
            ],
        };

        $steps = $this->normalizeProvisioningSteps(
            is_array($platformProvisioning['steps'] ?? null) ? $platformProvisioning['steps'] : []
        );
        $timeline = $steps;
        $emailStep = $this->resolveEmailStep($steps, $platformProvisioning);
        $progress = $this->resolveProvisioningProgress($steps, $status);

        if ($status === WebsiteStatus::Active) {
            $summary = $this->resolveActiveProvisioningSummary($emailStep);
        }

        return [
            'site_id' => $website->site_id,
            'name' => $website->name ?? 'New Website',
            'domain' => $website->domain,
            'status' => $status->value,
            'status_label' => $status->label(),
            'badge_class' => $status->badgeClass(),
            'headline' => $summary['headline'],
            'detail' => $summary['detail'],
            'is_complete' => $status === WebsiteStatus::Active,
            'is_failed' => $status === WebsiteStatus::Failed,
            'is_waiting' => $status === WebsiteStatus::WaitingForDns,
            'dns_instructions' => $platformProvisioning['dns_instructions'] ?? null,
            'dns_confirmed_by_user' => (bool) ($platformProvisioning['dns_confirmed_by_user'] ?? false),
            'dns_confirmed_at' => $platformProvisioning['dns_confirmed_at'] ?? null,
            'dns_check_count' => (int) ($platformProvisioning['dns_check_count'] ?? 0),
            'dns_last_checked_at' => $platformProvisioning['dns_last_checked_at'] ?? null,
            'dns_check_result' => $platformProvisioning['dns_check_result'] ?? null,
            'dns_domain_not_registered' => (bool) ($platformProvisioning['dns_domain_not_registered'] ?? false),
            'confirm_dns_url' => route('agency.onboarding.provisioning.website.confirm-dns', $website->id),
            'manage_url' => route('agency.websites.show', $website->id),
            'websites_url' => route('agency.websites.index'),
            'support_url' => route('agency.tickets.create'),
            'steps' => $steps,
            'timeline' => $timeline,
            'email_step' => $emailStep,
            'progress' => $progress,
            'next_actions' => $this->buildProvisioningNextActions($status, $emailStep),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $emailStep
     * @return array{headline: string, detail: string}
     */
    private function resolveActiveProvisioningSummary(?array $emailStep): array
    {
        $emailStatus = is_string($emailStep['status'] ?? null)
            ? $emailStep['status']
            : 'pending';

        return match ($emailStatus) {
            'completed' => [
                'headline' => 'Your website is ready and your login details are in your email',
                'detail' => 'Provisioning is complete. We sent your login details by email. You can now open and manage your website.',
            ],
            'failed' => [
                'headline' => 'Your website is ready',
                'detail' => 'Provisioning is complete, but login details email needs attention. Contact support if you do not receive it shortly.',
            ],
            default => [
                'headline' => 'Your website is ready',
                'detail' => 'Provisioning is complete. We are sending your login details by email now. You can open and manage your website.',
            ],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProvisioningSteps(array $steps): array
    {
        $rawStepsByKey = [];

        foreach ($steps as $step) {
            $key = is_array($step) ? ($step['key'] ?? null) : null;
            if (! is_string($key)) {
                continue;
            }

            if ($key === '') {
                continue;
            }

            $rawStepsByKey[$key] = $step;
        }

        $normalized = [];

        foreach ($this->provisioningStepBlueprint() as $index => $step) {
            $aggregateKeys = array_filter(
                $step['aggregate_keys'],
                static fn (string $value): bool => $value !== '',
            );

            $rawGroupSteps = [];
            foreach ($aggregateKeys as $aggregateKey) {
                if (isset($rawStepsByKey[$aggregateKey]) && is_array($rawStepsByKey[$aggregateKey])) {
                    $rawGroupSteps[] = $rawStepsByKey[$aggregateKey];
                }
            }

            $status = $this->resolveAggregatedStepStatus($rawGroupSteps, max(1, count($aggregateKeys)));
            $updatedAt = $this->resolveLatestStepTimestamp($rawGroupSteps);

            $tone = match ($status) {
                'completed' => 'success',
                'failed' => 'danger',
                'reverted' => 'warning',
                'in_progress' => 'info',
                default => 'secondary',
            };

            $badgeClass = match ($status) {
                'completed' => 'bg-success-subtle text-success',
                'failed' => 'bg-danger-subtle text-danger',
                'reverted' => 'bg-warning-subtle text-warning',
                'in_progress' => 'bg-info-subtle text-info',
                default => 'bg-secondary-subtle text-secondary',
            };

            $icon = match ($status) {
                'completed' => 'ri-check-line',
                'failed' => 'ri-error-warning-line',
                'reverted' => 'ri-arrow-go-back-line',
                'in_progress' => 'ri-loader-4-line',
                default => 'ri-time-line',
            };

            $stepKey = $step['key'];

            $normalized[] = [
                'key' => $stepKey,
                'title' => $step['title'],
                'description' => $step['description'],
                'status' => $status,
                'status_label' => $this->humanizeStepStatus($status),
                'message' => $this->customerFacingStepMessage($stepKey, $status),
                'updated_at' => $updatedAt?->toIso8601String(),
                'updated_at_display' => $updatedAt?->format('M d, Y \a\t h:i A'),
                'updated_at_ago' => $updatedAt?->diffForHumans(),
                'timestamp' => $updatedAt?->getTimestamp() ?? 0,
                'has_timestamp' => $updatedAt instanceof Carbon,
                'tone' => $tone,
                'badge_class' => $badgeClass,
                'icon' => $icon,
                'sequence' => $index,
                'is_email_step' => $step['is_email_step'] || ($stepKey === 'send_emails'),
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{key: string, title: string, description: string, aggregate_keys: list<string>, is_email_step: bool}>
     */
    private function provisioningStepBlueprint(): array
    {
        return [
            [
                'key' => 'domain_dns',
                'title' => 'Domain & DNS',
                'description' => 'We connect your domain and verify it is ready.',
                'aggregate_keys' => ['resolve_domain', 'setup_bunny_dns'],
                'is_email_step' => false,
            ],
            [
                'key' => 'hosting_setup',
                'title' => 'Hosting Setup',
                'description' => 'We prepare the core hosting resources for your website.',
                'aggregate_keys' => ['create_user', 'create_website', 'create_database'],
                'is_email_step' => false,
            ],
            [
                'key' => 'application_setup',
                'title' => 'Application Setup',
                'description' => 'We configure and install your website application.',
                'aggregate_keys' => ['configure_env', 'prepare_astero', 'install_astero'],
                'is_email_step' => false,
            ],
            [
                'key' => 'security_performance',
                'title' => 'Security & Performance',
                'description' => 'We apply security and performance optimizations.',
                'aggregate_keys' => ['install_ssl', 'setup_bunny_cdn'],
                'is_email_step' => false,
            ],
            [
                'key' => 'send_emails',
                'title' => 'Email Delivery',
                'description' => 'We send your login details by email.',
                'aggregate_keys' => ['send_emails'],
                'is_email_step' => true,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawGroupSteps
     */
    private function resolveAggregatedStepStatus(array $rawGroupSteps, int $expectedStepCount): string
    {
        if ($rawGroupSteps === []) {
            return 'pending';
        }

        $statuses = array_values(array_map(
            static fn (array $step): string => (string) ($step['status'] ?? 'pending'),
            $rawGroupSteps
        ));

        if (in_array('failed', $statuses, true)) {
            return 'failed';
        }

        if (in_array('reverted', $statuses, true)) {
            return 'reverted';
        }

        if (
            in_array('in_progress', $statuses, true)
            || in_array('running', $statuses, true)
            || in_array('provisioning', $statuses, true)
        ) {
            return 'in_progress';
        }

        $completedCount = count(array_filter(
            $statuses,
            static fn (string $status): bool => in_array($status, ['completed', 'done', 'success'], true),
        ));

        if ($completedCount >= max(1, $expectedStepCount)) {
            return 'completed';
        }

        if ($completedCount > 0) {
            return 'in_progress';
        }

        return 'pending';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawGroupSteps
     */
    private function resolveLatestStepTimestamp(array $rawGroupSteps): ?Carbon
    {
        $latest = null;

        foreach ($rawGroupSteps as $step) {
            $rawUpdatedAt = $step['updated_at'] ?? null;
            if (! is_string($rawUpdatedAt)) {
                continue;
            }

            if ($rawUpdatedAt === '') {
                continue;
            }

            try {
                $parsed = Date::parse($rawUpdatedAt);
            } catch (Throwable) {
                $parsed = null;
            }

            if (! $parsed instanceof Carbon) {
                continue;
            }

            if (! $latest || $parsed->greaterThan($latest)) {
                $latest = $parsed;
            }
        }

        return $latest;
    }

    private function humanizeStepStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'Completed',
            'failed' => 'Failed',
            'reverted' => 'Reverted',
            'in_progress' => 'In Progress',
            default => 'Pending',
        };
    }

    private function customerFacingStepMessage(string $stepKey, string $status): string
    {
        return match ($stepKey) {
            'domain_dns' => match ($status) {
                'completed' => 'Domain and DNS setup completed.',
                'failed' => 'Domain or DNS setup needs attention.',
                'in_progress' => 'Configuring domain and DNS settings.',
                'reverted' => 'Domain setup was rolled back and will be retried.',
                default => 'Waiting to start domain and DNS setup.',
            },
            'hosting_setup' => match ($status) {
                'completed' => 'Hosting setup completed.',
                'failed' => 'Hosting setup needs attention.',
                'in_progress' => 'Preparing hosting resources.',
                'reverted' => 'Hosting setup was rolled back and will be retried.',
                default => 'Waiting to start hosting setup.',
            },
            'application_setup' => match ($status) {
                'completed' => 'Application setup completed.',
                'failed' => 'Application setup needs attention.',
                'in_progress' => 'Installing and configuring your website application.',
                'reverted' => 'Application setup was rolled back and will be retried.',
                default => 'Waiting to start application setup.',
            },
            'security_performance' => match ($status) {
                'completed' => 'Security and performance setup completed.',
                'failed' => 'Security or performance setup needs attention.',
                'in_progress' => 'Applying security and performance settings.',
                'reverted' => 'Security setup was rolled back and will be retried.',
                default => 'Waiting to apply security and performance settings.',
            },
            'send_emails' => match ($status) {
                'completed' => 'Login details email sent.',
                'failed' => 'Login details email could not be sent yet.',
                'in_progress' => 'Preparing your login details email.',
                'reverted' => 'Email delivery was rolled back and will be retried.',
                default => 'Login details email will be sent after setup is complete.',
            },
            default => match ($status) {
                'completed' => 'Completed.',
                'failed' => 'Needs attention.',
                'in_progress' => 'In progress.',
                'reverted' => 'Rolled back and will be retried.',
                default => 'Waiting to start.',
            },
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<string, mixed>|null  $platformProvisioning
     * @return array<string, mixed>|null
     */
    private function resolveEmailStep(array $steps, ?array $platformProvisioning): ?array
    {
        $emailStep = collect($steps)->first(static fn (array $step): bool => (bool) ($step['is_email_step'] ?? false));

        if ($emailStep) {
            return $emailStep;
        }

        $rawEmailStep = $platformProvisioning['email_step'] ?? null;
        if (! is_array($rawEmailStep)) {
            return null;
        }

        $normalized = $this->normalizeProvisioningSteps([$rawEmailStep]);

        return collect($normalized)->first(
            static fn (array $step): bool => (bool) ($step['is_email_step'] ?? false)
        );
    }

    /**
     * @return array{total_steps: int, completed_steps: int, failed_steps: int, in_progress_steps: int, pending_steps: int, percentage: int}
     */
    private function resolveProvisioningProgress(array $steps, WebsiteStatus $status): array
    {
        $completedSteps = count(array_filter($steps, static fn (array $step): bool => ($step['status'] ?? '') === 'completed'));
        $failedSteps = count(array_filter($steps, static fn (array $step): bool => ($step['status'] ?? '') === 'failed'));
        $inProgressSteps = count(array_filter($steps, static fn (array $step): bool => ($step['status'] ?? '') === 'in_progress'));
        $pendingSteps = count(array_filter($steps, static fn (array $step): bool => ($step['status'] ?? '') === 'pending'));
        $totalSteps = count($steps);

        $percentage = $totalSteps > 0
            ? (int) round($completedSteps / $totalSteps * 100)
            : 0;

        if ($status === WebsiteStatus::Active) {
            $percentage = 100;
        }

        return [
            'total_steps' => $totalSteps,
            'completed_steps' => $completedSteps,
            'failed_steps' => $failedSteps,
            'in_progress_steps' => $inProgressSteps,
            'pending_steps' => $pendingSteps,
            'percentage' => $percentage,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $emailStep
     * @return array<int, string>
     */
    private function buildProvisioningNextActions(WebsiteStatus $status, ?array $emailStep): array
    {
        return match ($status) {
            WebsiteStatus::Active => [
                'Open your website and review your homepage.',
                'Manage your site settings from the Websites section.',
                $emailStep && ($emailStep['status'] ?? null) === 'completed'
                    ? 'Login details were emailed to you. Check inbox and spam folder.'
                    : 'Login details email may still be processing. Keep this page open until it completes.',
            ],
            WebsiteStatus::Failed => [
                'Open a support ticket so we can help you finish setup.',
                $emailStep && ($emailStep['status'] ?? null) === 'failed'
                    ? 'Email delivery also failed. Support will verify your contact details.'
                    : 'We will verify pending setup and email steps with you.',
                'You can start a new site setup after we resolve the issue.',
            ],
            default => [
                'No action is needed right now. We will keep checking progress every 10 seconds.',
                $emailStep && ($emailStep['status'] ?? null) === 'in_progress'
                    ? 'We are preparing and sending your login details email.'
                    : 'You will receive login details by email once setup completes.',
            ],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPlatformProvisioningData(string $siteId): ?array
    {
        try {
            $response = $this->platformApiClient->getWebsiteProvisioningStatus($siteId);

            return $response['data'];
        } catch (PlatformApiException $exception) {
            Log::warning('Failed to fetch provisioning steps from Platform API', [
                'site_id' => $siteId,
                'status_code' => $exception->statusCode,
                'message' => $exception->getMessage(),
                'body' => $exception->body,
            ]);

            return null;
        } catch (Throwable $exception) {
            Log::warning('Unexpected error while fetching provisioning steps from Platform API', [
                'site_id' => $siteId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function syncOnboardingCompletion(?Customer $customer, string $websiteStatus): void
    {
        if (! $customer || $websiteStatus !== WebsiteStatus::Active->value) {
            return;
        }

        $metadata = $customer->metadata ?? [];
        $onboarding = $metadata['onboarding'] ?? [];

        if (! empty($onboarding['completed_at']) && ($onboarding['current_step'] ?? '') === 'completed') {
            return;
        }

        $onboarding['current_step'] = 'completed';
        $onboarding['completed_at'] = now()->toDateTimeString();
        $metadata['onboarding'] = $onboarding;

        $customer->update(['metadata' => $metadata]);
    }

    private function resolveCustomer(User $user): ?Customer
    {
        $customer = Customer::query()->where('user_id', $user->id)->first();

        // If no customer exists, create one automatically for the logged-in user
        if (! $customer) {
            return $this->createCustomerForUser($user);
        }

        return $customer;
    }

    /**
     * Create a customer profile for an existing user who doesn't have one.
     */
    private function createCustomerForUser(User $user): ?Customer
    {
        try {
            $customerData = [
                'user_id' => $user->id,
                'user_action' => 'associate',
                'type' => 'person',
                'contact_first_name' => $user->first_name ?? $user->name,
                'contact_last_name' => $user->last_name ?? '',
                'email' => $user->email,
                'metadata' => [
                    'onboarding' => [
                        'source' => 'agency',
                        'started_at' => now()->toDateTimeString(),
                        'current_step' => 'plan_selection',
                        'auto_created' => true,
                    ],
                ],
            ];

            $this->customerService->setCustomer($customerData);

            // Assign customer role if not already assigned
            $this->assignCustomerRole($user);

            return Customer::query()->where('user_id', $user->id)->first();
        } catch (Throwable $throwable) {
            Log::error('Failed to create customer profile during onboarding', [
                'user_id' => $user->id,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    private function assignCustomerRole(User $user): void
    {
        $role = Role::query()->where('name', 'customer')->first();

        if (! $role) {
            return;
        }

        // Use assignRole to ADD the customer role without removing existing roles
        if (! $user->hasRole($role->name)) {
            $user->assignRole($role->name);
        }
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function accountTypeOptions(): array
    {
        return [
            ['value' => 'company', 'label' => 'Company or Agency'],
            ['value' => 'individual', 'label' => 'Individual or Freelancer'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function useCaseOptions(): array
    {
        return [
            ['value' => 'website_builder', 'label' => 'Launch a new website'],
            ['value' => 'client_sites', 'label' => 'Manage client websites'],
            ['value' => 'storefront', 'label' => 'Sell products online'],
            ['value' => 'portfolio', 'label' => 'Portfolio or personal site'],
        ];
    }
}
