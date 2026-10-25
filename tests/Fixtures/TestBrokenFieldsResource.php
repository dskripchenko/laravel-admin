<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\Resource;

/**
 * A resource whose fields() cannot be built outside a request — like a host's
 * options read from a tenant's table — for the OpenAPI spec tests.
 *
 * @internal
 */
final class TestBrokenFieldsResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public function fields(): array
    {
        throw new RuntimeException('relation "storage_disks" does not exist');
    }
}
