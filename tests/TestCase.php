<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'cache.limiter' => 'array',
            'session.driver' => 'array',
        ]);
    }

    private function withCsrfProtection(array $data = [], array $headers = []): array
    {
        $token = 'test-csrf-token';

        $this->withSession(['_token' => $token]);

        return [
            array_merge(['_token' => $token], $data),
            array_merge(['X-CSRF-TOKEN' => $token], $headers),
        ];
    }

    public function post($uri, array $data = [], array $headers = [])
    {
        [$data, $headers] = $this->withCsrfProtection($data, $headers);

        return parent::post($uri, $data, $headers);
    }

    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        [$data, $headers] = $this->withCsrfProtection($data, $headers);

        return parent::postJson($uri, $data, $headers, $options);
    }

    public function put($uri, array $data = [], array $headers = [])
    {
        [$data, $headers] = $this->withCsrfProtection($data, $headers);

        return parent::put($uri, $data, $headers);
    }

    public function putJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        [$data, $headers] = $this->withCsrfProtection($data, $headers);

        return parent::putJson($uri, $data, $headers, $options);
    }

    public function patch($uri, array $data = [], array $headers = [])
    {
        [$data, $headers] = $this->withCsrfProtection($data, $headers);

        return parent::patch($uri, $data, $headers);
    }

    public function patchJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        [$data, $headers] = $this->withCsrfProtection($data, $headers);

        return parent::patchJson($uri, $data, $headers, $options);
    }

    public function delete($uri, array $data = [], array $headers = [])
    {
        [$data, $headers] = $this->withCsrfProtection($data, $headers);

        return parent::delete($uri, $data, $headers);
    }

    public function deleteJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        [$data, $headers] = $this->withCsrfProtection($data, $headers);

        return parent::deleteJson($uri, $data, $headers, $options);
    }
}
