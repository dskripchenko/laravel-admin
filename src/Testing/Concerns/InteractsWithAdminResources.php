<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Testing\Concerns;

use Illuminate\Testing\TestResponse;

/**
 * The trait for the tests that work against the admin's resource API.
 *
 * It offers high-level helpers instead of hand-written
 * `getJson('/api/admin/...')` calls:
 *
 *     $this->getResourceMeta('users')->assertOk();
 *     $this->postResourceCreate('users', ['name' => 'X', 'email' => 'x@a.com']);
 *     $this->getResourceRead('users', $id);
 *     $this->postResourceUpdate('users', $id, ['name' => 'Y']);
 *     $this->postResourceDelete('users', $id);
 *     $this->postResourceSearch('users', filters: ['email' => 'x']);
 *
 * The URL is `/api/admin/{slug}/{action}`, which is laravel-api's basic
 * pattern with {version}=admin.
 */
trait InteractsWithAdminResources
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public function getResourceMeta(string $slug, array $headers = []): TestResponse
    {
        return $this->getJson($this->resourceUrl($slug, 'meta'), $headers);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function postResourceCreate(string $slug, array $payload = [], array $headers = []): TestResponse
    {
        return $this->postJson($this->resourceUrl($slug, 'create'), $payload, $headers);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    public function getResourceRead(string $slug, int|string $id, array $headers = []): TestResponse
    {
        return $this->getJson($this->resourceUrl($slug, 'read').'?id='.urlencode((string) $id), $headers);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function postResourceUpdate(string $slug, int|string $id, array $payload = [], array $headers = []): TestResponse
    {
        return $this->postJson(
            $this->resourceUrl($slug, 'update'),
            array_merge(['id' => $id], $payload),
            $headers,
        );
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    public function postResourceDelete(string $slug, int|string $id, array $headers = []): TestResponse
    {
        return $this->postJson($this->resourceUrl($slug, 'delete'), ['id' => $id], $headers);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $extra
     */
    public function postResourceSearch(string $slug, array $filters = [], array $extra = []): TestResponse
    {
        $payload = $extra;
        if ($filters !== []) {
            $payload['filters'] = $filters;
        }

        return $this->postJson($this->resourceUrl($slug, 'search'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function postResourceAction(string $slug, string $action, array $payload = []): TestResponse
    {
        return $this->postJson($this->resourceUrl($slug, $action), $payload);
    }

    /**
     * Asserts that the meta action succeeded and carries the expected keys.
     *
     * @param  list<string>  $expectedKeys
     */
    public function assertResourceMetaOk(string $slug, array $expectedKeys = ['fields', 'columns', 'permissions']): TestResponse
    {
        $response = $this->getResourceMeta($slug);
        $response->assertOk();
        $response->assertJsonPath('success', true);
        foreach ($expectedKeys as $key) {
            $response->assertJsonPath("payload.{$key}", fn ($value): bool => $value !== null);
        }

        return $response;
    }

    /**
     * Asserts that the search action succeeded and that meta.total is as expected.
     */
    public function assertResourceCount(string $slug, int $expected): TestResponse
    {
        $response = $this->postResourceSearch($slug);
        $response->assertOk();
        expect((int) $response->json('payload.meta.total'))->toBe($expected);

        return $response;
    }

    private function resourceUrl(string $slug, string $action): string
    {
        $apiPath = (string) config('admin.api_path', 'api/admin');

        return '/'.trim($apiPath, '/').'/'.$slug.'/'.$action;
    }
}
