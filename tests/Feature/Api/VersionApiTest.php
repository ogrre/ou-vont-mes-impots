<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class VersionApiTest extends TestCase
{
    public function test_it_exposes_the_application_and_api_versions(): void
    {
        config([
            'api.name' => 'Mais où vont mes impôts ? API',
            'api.version' => '0.1.0-test',
            'api.version_prefix' => 'v1',
        ]);

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertExactJson([
                'name' => 'Mais où vont mes impôts ? API',
                'version' => '0.1.0-test',
                'api_version' => 'v1',
            ]);
    }

    public function test_version_endpoint_is_read_only(): void
    {
        $this->postJson('/api/v1/version')->assertMethodNotAllowed();
    }
}
