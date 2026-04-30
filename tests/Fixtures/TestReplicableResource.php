<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource с replicable=true и кастомным replicate() override'ом.
 *
 * @internal
 */
final class TestReplicableResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public function replicable(): bool
    {
        return true;
    }

    public function replicate(Model $original): Model
    {
        $copy = parent::replicate($original);
        // Demonstration: regenerate email с timestamp suffix.
        $copy->setAttribute('email', 'copy-'.uniqid().'@example.com');

        return $copy;
    }

    public function fields(): array
    {
        return [Input::make('name')->required()];
    }
}
