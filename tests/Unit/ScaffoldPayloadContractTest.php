<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Definitions\RoleDefinition;
use App\Definitions\UserDefinition;
use App\Http\Resources\EmailProviderResource;
use App\Http\Resources\UserResource;
use App\Models\Address;
use App\Models\EmailProvider;
use App\Models\Role;
use App\Models\User;
use App\Scaffold\Column;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\CMS\Definitions\CategoryDefinition;
use Modules\Platform\Definitions\AgencyDefinition;
use Modules\Platform\Http\Resources\DomainDnsRecordResource;
use Modules\Platform\Http\Resources\WebsiteResource;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\DomainDnsRecord;
use Modules\Platform\Models\Website;
use Tests\TestCase;

class ScaffoldPayloadContractTest extends TestCase
{
    public function test_scaffold_resource_defaults_raw_attributes_to_definition_columns(): void
    {
        $definition = new class extends ScaffoldDefinition
        {
            protected string $routePrefix = 'test.posts';

            protected bool $includeRowActionsInInertiaRows = false;

            public function columns(): array
            {
                return [
                    Column::make('title')->label('Title'),
                    Column::make('status')->label('Status'),
                    Column::make('_actions')->label('Actions'),
                ];
            }

            public function getModelClass(): string
            {
                return Model::class;
            }
        };

        $model = new class extends Model
        {
            protected $guarded = [];

            public $timestamps = false;
        };

        $model->forceFill([
            'id' => 5,
            'title' => 'Hello',
            'status' => 'draft',
            'secret' => 'should-not-leak',
        ]);
        $model->exists = true;

        $resource = new class($model, $definition) extends ScaffoldResource
        {
            public function __construct($resource, private readonly ScaffoldDefinition $testDefinition)
            {
                parent::__construct($resource);
            }

            protected function definition(): ScaffoldDefinition
            {
                return $this->testDefinition;
            }
        };

        $payload = $resource->toArray(Request::create('/'));

        $this->assertSame(5, $payload['id']);
        $this->assertSame('Hello', $payload['title']);
        $this->assertSame('draft', $payload['status']);
        $this->assertArrayNotHasKey('secret', $payload);
    }

    public function test_scaffold_resource_omits_row_actions_when_definition_disables_row_actions(): void
    {
        $definition = new class extends ScaffoldDefinition
        {
            protected string $routePrefix = 'test.posts';

            protected bool $includeRowActionsInInertiaRows = false;

            public function columns(): array
            {
                return [
                    Column::make('title')->label('Title'),
                ];
            }

            public function getModelClass(): string
            {
                return Model::class;
            }
        };

        $model = new class extends Model
        {
            protected $guarded = [];

            public $timestamps = false;
        };

        $model->forceFill([
            'id' => 9,
            'title' => 'Hidden Actions',
        ]);
        $model->exists = true;

        $resource = new class($model, $definition) extends ScaffoldResource
        {
            public function __construct($resource, private readonly ScaffoldDefinition $testDefinition)
            {
                parent::__construct($resource);
            }

            protected function definition(): ScaffoldDefinition
            {
                return $this->testDefinition;
            }
        };

        $payload = $resource->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('actions', $payload);
    }

    public function test_standard_and_custom_definitions_expose_expected_inertia_payload_flags(): void
    {
        $agencyDefinition = new AgencyDefinition;
        $roleDefinition = new RoleDefinition;
        $userDefinition = new UserDefinition;
        $categoryDefinition = new CategoryDefinition;

        $this->assertTrue($agencyDefinition->shouldIncludeActionConfigInInertia());
        $this->assertTrue($agencyDefinition->shouldIncludeEmptyStateConfigInInertia());
        $this->assertTrue($agencyDefinition->shouldIncludeRowActionsInInertiaRows());

        $this->assertFalse($roleDefinition->shouldIncludeActionConfigInInertia());
        $this->assertFalse($roleDefinition->shouldIncludeEmptyStateConfigInInertia());
        $this->assertFalse($roleDefinition->shouldIncludeRowActionsInInertiaRows());

        $this->assertFalse($userDefinition->shouldIncludeActionConfigInInertia());
        $this->assertFalse($userDefinition->shouldIncludeEmptyStateConfigInInertia());
        $this->assertTrue($userDefinition->shouldIncludeRowActionsInInertiaRows());

        $this->assertFalse($categoryDefinition->shouldIncludeActionConfigInInertia());
        $this->assertFalse($categoryDefinition->shouldIncludeEmptyStateConfigInInertia());
        $this->assertFalse($categoryDefinition->shouldIncludeRowActionsInInertiaRows());
    }

    public function test_website_resource_keeps_domain_in_list_payload(): void
    {
        $website = new Website;
        $website->forceFill([
            'id' => 11,
            'uid' => 'W-11',
            'name' => 'Marketing Site',
            'domain' => 'example.com',
            'status' => 'active',
            'dns_mode' => 'managed',
        ]);
        $website->exists = true;

        $payload = (new WebsiteResource($website))->toArray(Request::create('/'));

        $this->assertSame('example.com', $payload['domain']);
    }

    public function test_domain_dns_record_resource_keeps_domain_name_in_list_payload(): void
    {
        $record = new DomainDnsRecord;
        $record->forceFill([
            'id' => 21,
            'name' => 'www',
            'type' => 'A',
            'value' => '127.0.0.1',
            'ttl' => 300,
        ]);
        $record->setRelation('domain', tap(new Domain, function (Domain $domain): void {
            $domain->forceFill([
                'id' => 8,
                'name' => 'example.com',
            ]);
            $domain->exists = true;
        }));
        $record->exists = true;

        $payload = (new DomainDnsRecordResource($record))->toArray(Request::create('/'));

        $this->assertSame('example.com', $payload['domain_name']);
    }

    public function test_email_provider_resource_trims_detail_only_fields_on_index_routes(): void
    {
        $provider = new EmailProvider;
        $provider->forceFill([
            'id' => 31,
            'name' => 'Primary SMTP',
            'description' => 'Transactional mail provider',
            'sender_name' => 'App Mailer',
            'sender_email' => 'mailer@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_user' => 'mailer',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'reply_to' => 'reply@example.com',
            'bcc' => 'audit@example.com',
            'signature' => 'Thanks',
            'status' => 'active',
            'order' => 1,
        ]);
        $provider->exists = true;

        $payload = (new EmailProviderResource($provider))->toArray($this->makeMatchedRequest('app.masters.email.providers.index'));

        $this->assertArrayNotHasKey('description', $payload);
        $this->assertArrayNotHasKey('smtp_user', $payload);
        $this->assertArrayNotHasKey('smtp_port', $payload);
        $this->assertArrayNotHasKey('reply_to', $payload);
        $this->assertArrayNotHasKey('bcc', $payload);
        $this->assertArrayNotHasKey('signature', $payload);
        $this->assertSame('App Mailer', $payload['sender_name']);
        $this->assertSame('TLS', $payload['smtp_encryption']);
    }

    public function test_user_resource_trims_detail_only_fields_on_index_routes(): void
    {
        $user = new User;
        $user->forceFill([
            'id' => 41,
            'name' => 'Ada Lovelace',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'username' => 'ada',
            'gender' => 'female',
            'tagline' => 'First programmer',
            'bio' => 'Mathematician',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->setRelation('roles', collect([
            tap(new Role, function (Role $role): void {
                $role->forceFill(['name' => 'admin']);
                $role->exists = true;
            }),
        ]));
        $user->setRelation('primaryAddress', tap(new Address, function (Address $address): void {
            $address->forceFill([
                'phone' => '1234567890',
                'address1' => '123 Example Street',
            ]);
            $address->exists = true;
        }));
        $user->exists = true;

        $payload = (new UserResource($user))->toArray($this->makeMatchedRequest('app.users.index'));

        $this->assertSame('Ada Lovelace', $payload['name']);
        $this->assertSame(['admin'], $payload['roles']);
        $this->assertArrayNotHasKey('username', $payload);
        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertArrayNotHasKey('bio', $payload);
        $this->assertArrayNotHasKey('tagline', $payload);
        $this->assertArrayNotHasKey('address1', $payload);
        $this->assertArrayNotHasKey('last_access', $payload);
    }

    private function makeMatchedRequest(string $routeName): Request
    {
        $request = Request::create(route($routeName), 'GET');
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        return $request;
    }
}
