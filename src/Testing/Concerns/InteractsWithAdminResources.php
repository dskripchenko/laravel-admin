<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Testing\Concerns;

use Illuminate\Testing\TestResponse;

/**
 * Trait для тестов, работающих с admin Resource API.
 *
 * Предоставляет high-level helpers вместо ручного `getJson('/api/admin/...')`:
 *
 *     $this->getResourceMeta('users')->assertOk();
 *     $this->postResourceCreate('users', ['name' => 'X', 'email' => 'x@a.com']);
 *     $this->getResourceRead('users', $id);
 *     $this->postResourceUpdate('users', $id, ['name' => 'Y']);
 *     $this->postResourceDelete('users', $id);
 *     $this->postResourceSearch('users', filters: ['email' => 'x']);
 *
 * URL формируется как `/api/admin/{slug}/{action}` — это базовый паттерн
 * laravel-api с {version}=admin.
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
     * Утверждение: meta-action вернул success + содержит ожидаемые ключи.
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
     * Утверждение: search-action вернул success + meta.total === ожидаемое.
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
