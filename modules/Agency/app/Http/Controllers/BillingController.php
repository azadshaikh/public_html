<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Agency\Http\Requests\UpdateTaxDetailsRequest;
use Modules\Customers\Models\Customer;

class BillingController extends Controller
{
    /**
     * Billing overview page with card navigation.
     */
    public function index(): Response
    {
        return Inertia::render('agency/billing/index');
    }

    /**
     * Tax details configuration page.
     */
    public function taxDetails(Request $request): Response
    {
        $customer = Customer::query()->where('user_id', $request->user()->id)->first();
        $billingAddress = null;

        if ($customer) {
            $billingAddress = $customer->addresses()->where('type', 'billing')->first();
        }

        return Inertia::render('agency/billing/tax-details', [
            'customer' => $customer,
            'billingAddress' => $billingAddress,
        ]);
    }

    /**
     * Update tax details.
     */
    public function updateTaxDetails(UpdateTaxDetailsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $customer = Customer::query()->where('user_id', $request->user()->id)->first();

        if ($customer) {
            $customer->update([
                'tax_id' => $validated['vat_id'] ?? null,
                'company_name' => $validated['company_name'],
            ]);

            $billingAddress = $customer->addresses()->firstOrNew(['type' => 'billing']);

            $billingAddress->fill([
                'country_code' => $validated['country_code'],
                'state' => $validated['state'] ?? null,
                'state_code' => $validated['state_code'] ?? null,
                'city' => $validated['city'] ?? null,
                'address1' => $validated['address'],
                'company' => $validated['company_name'],
            ])->save();
        }

        return to_route('agency.billing.tax-details')
            ->with('success', 'Tax details updated successfully.');
    }
}
