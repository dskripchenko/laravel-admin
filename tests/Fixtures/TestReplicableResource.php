<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * A resource with replicable=true and a custom replicate() override.
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
        // A demonstration: regenerate the email with a timestamp suffix.
        $copy->setAttribute('email', 'copy-'.uniqid().'@example.com');

        return $copy;
    }

    public function fields(): array
    {
        return [Input::make('name')->required()];
    }
}
