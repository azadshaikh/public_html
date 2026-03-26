<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteDomainVerificationService;

class HestiaPublishDomainVerificationCommand extends BaseCommand
{
    use ActivityTrait;

    protected $signature = 'platform:hestia:publish-domain-verification {website_id : The ID of the website}';

    protected $description = 'Publish the domain verification file used by HTTP-based DNS validation.';

    protected ?string $stepKey = 'publish_domain_verification';

    protected function handleCommand(Website $website): void
    {
        $service = resolve(WebsiteDomainVerificationService::class);
        $service->publishVerificationFile($website);

        $message = sprintf(
            'Domain verification file published at %s.',
            $service->verificationPath()
        );

        $this->logActivity($website, ActivityAction::UPDATE, $message);
        $website->markProvisioningStepDone('publish_domain_verification', $message);
    }
}
