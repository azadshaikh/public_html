<?php

namespace Modules\Platform\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Website;

class WebsiteSslAssignmentService
{
    public function __construct(
        private DomainService $domainService
    ) {}

    public function findReusableCertificateForWebsite(Website $website, int $minimumValidityDays = 30): ?Secret
    {
        $domain = $website->domainRecord;

        if (! $domain) {
            return null;
        }

        $certificate = $this->domainService->getBestSslCertificate($domain);

        if (! $certificate) {
            return null;
        }

        if (! $this->domainService->certificateCoversDomain($certificate, (string) $website->domain)) {
            return null;
        }

        if (! $certificate->expires_at) {
            return null;
        }

        return $certificate->expires_at->greaterThan(now()->addDays($minimumValidityDays))
            ? $certificate
            : null;
    }

    public function assignCertificateToWebsite(Website $website, Secret $certificate): void
    {
        if ((int) $website->ssl_secret_id === (int) $certificate->id) {
            return;
        }

        $website->ssl_secret_id = $certificate->id;
        $website->save();
    }

    /**
     * @return Collection<int, Website>
     */
    public function websitesCoveredByCertificate(Domain $domain, Secret $certificate): Collection
    {
        /** @var Collection<int, Website> $websites */
        $websites = $domain->websites()
            ->whereNull('deleted_at')
            ->whereNotIn('status', [
                WebsiteStatus::Trash->value,
                WebsiteStatus::Deleted->value,
            ])
            ->get();

        return $websites
            ->filter(fn (Website $website): bool => $this->domainService->certificateCoversDomain($certificate, (string) $website->domain))
            ->values();
    }

    /**
     * @return Collection<int, Website>
     */
}
