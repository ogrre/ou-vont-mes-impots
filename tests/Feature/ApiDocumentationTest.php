<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_the_interactive_api_documentation_is_public(): void
    {
        $this->get('/docs/api')
            ->assertOk()
            ->assertSee('Mais où vont mes impôts ?', escape: false);
    }

    public function test_the_openapi_document_describes_every_public_endpoint(): void
    {
        $response = $this->getJson('/docs/api.json')->assertOk();

        $response
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.version', config('api.version'));

        $paths = $response->json('paths');

        $this->assertIsArray($paths);
        $this->assertArrayHasKey('/version', $paths);
        $this->assertArrayHasKey('/state-expenditure', $paths);
        $this->assertArrayHasKey('/state-revenue', $paths);
        $this->assertArrayHasKey('get', $paths['/version']);
        $this->assertArrayHasKey('get', $paths['/state-expenditure']);
        $this->assertArrayHasKey('get', $paths['/state-revenue']);
        $this->assertSame(
            'array',
            $paths['/state-expenditure']['get']['responses']['200']['content']['application/json']['schema']['properties']['items']['type'],
        );

        $parameterNames = array_column($paths['/state-expenditure']['get']['parameters'], 'name');

        $this->assertSame(['year', 'classification', 'measure'], $parameterNames);
    }
}
