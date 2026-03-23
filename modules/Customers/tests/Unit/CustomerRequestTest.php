<?php

declare(strict_types=1);

namespace Modules\Customers\Tests\Unit;

use Illuminate\Routing\Route;
use Modules\Customers\Http\Requests\CustomerRequest;
use Modules\Customers\Models\Customer;
use Tests\TestCase;

class CustomerRequestTest extends TestCase
{
    public function test_prepare_for_validation_keeps_existing_user_link_when_action_is_omitted(): void
    {
        $customer = new Customer;
        $customer->user_id = 42;

        $request = TestCustomerRequest::create('/admin/customers/1', 'PUT', [
            'type' => 'person',
            'contact_first_name' => 'Jamie',
            'email' => 'jamie@example.com',
            'phone' => '5553332222',
            'status' => 'active',
        ]);

        $route = new Route('PUT', '/admin/customers/{customer}', []);
        $route->bind($request);
        $route->setParameter('customer', $customer);
        $request->setRouteResolver(static fn (): Route => $route);

        $request->runPrepareForValidation();

        $this->assertSame('keep', $request->input('user_action'));
    }

    public function test_keep_is_an_allowed_user_action(): void
    {
        $request = TestCustomerRequest::create('/admin/customers/1', 'PUT', [
            'type' => 'person',
            'contact_first_name' => 'Jamie',
            'email' => 'jamie@example.com',
            'phone' => '5553332222',
            'status' => 'active',
            'user_action' => 'keep',
        ]);

        $rules = $request->rules();

        $this->assertContains('in:none,keep,create,associate', $rules['user_action']);
    }
}

class TestCustomerRequest extends CustomerRequest
{
    public function runPrepareForValidation(): void
    {
        $this->prepareForValidation();
    }
}
