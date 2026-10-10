<?php

namespace Tests\Feature\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class IdempotencyOpenApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewApiDocs', fn (?User $user = null) => true);
    }

    /** @return array<string, mixed> */
    private function operations(): array
    {
        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        return $response->json('paths');
    }

    /** @return array<string, mixed>|null */
    private function headerParam(array $paths, string $path, string $method): ?array
    {
        $parameters = array_merge(
            $paths[$path]['parameters'] ?? [],
            $paths[$path][$method]['parameters'] ?? [],
        );

        foreach ($parameters as $parameter) {
            if (($parameter['name'] ?? null) === 'Idempotency-Key' && ($parameter['in'] ?? null) === 'header') {
                return $parameter;
            }
        }

        return null;
    }

    public function test_protected_write_endpoints_document_required_idempotency_key_header(): void
    {
        $paths = $this->operations();

        $protectedOperations = [
            ['post', '/register/days'],
            ['patch', '/register/days/{operatingDay}'],
            ['post', '/register/tickets'],
            ['post', '/register/tickets/{ticket}/refund'],
            ['post', '/stock/losses'],
        ];

        foreach ($protectedOperations as [$method, $path]) {
            $parameter = $this->headerParam($paths, $path, $method);

            $this->assertNotNull($parameter, "Missing Idempotency-Key header on {$method} {$path}");
            $this->assertTrue($parameter['required'] ?? false, "Idempotency-Key not required on {$method} {$path}");
            $this->assertSame('string', $parameter['schema']['type'] ?? null);
            $this->assertSame('uuid', $parameter['schema']['format'] ?? null);
        }
    }

    public function test_unprotected_endpoints_do_not_document_idempotency_key(): void
    {
        $paths = $this->operations();

        $unprotectedOperations = [
            ['post', '/register/tickets/preview'],
            ['get', '/register/days/current'],
            ['get', '/register/tickets/{ticket}'],
            ['get', '/stock/locations'],
        ];

        foreach ($unprotectedOperations as [$method, $path]) {
            $this->assertNull(
                $this->headerParam($paths, $path, $method),
                "Unexpected Idempotency-Key header on {$method} {$path}",
            );
        }
    }
}
