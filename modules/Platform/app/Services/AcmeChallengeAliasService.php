<?php

namespace Modules\Platform\Services;

use InvalidArgumentException;

class AcmeChallengeAliasService
{
    public function buildChallengeAlias(string $rootDomain): string
    {
        $normalizedRootDomain = $this->normalizeDomain($rootDomain, 'Root domain');

        return sprintf(
            '_acme-challenge.%s.%s',
            $normalizedRootDomain,
            $this->aliasDomain()
        );
    }

    public function aliasDomain(): string
    {
        $aliasDomain = trim((string) config('platform.acme_challenge.alias_domain'));

        throw_if(
            $aliasDomain === '',
            InvalidArgumentException::class,
            'ACME challenge alias domain is not configured. Set ACME_CHALLENGE_ALIAS_DOMAIN.'
        );

        return $this->normalizeDomain($aliasDomain, 'ACME challenge alias domain');
    }

    public function bunnyApiKey(): string
    {
        $apiKey = trim((string) config('platform.acme_challenge.bunny_api_key'));

        throw_if(
            $apiKey === '',
            InvalidArgumentException::class,
            'ACME challenge Bunny API key is not configured. Set ACME_CHALLENGE_BUNNY_API_KEY.'
        );

        return $apiKey;
    }

    private function normalizeDomain(string $domain, string $label): string
    {
        $normalizedDomain = strtolower(rtrim(trim($domain), '.'));

        throw_if(
            $normalizedDomain === '',
            InvalidArgumentException::class,
            $label.' is required.'
        );

        return $normalizedDomain;
    }
}
