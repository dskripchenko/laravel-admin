<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Settings\SettingsResource;

/**
 * Тестовый SettingsResource: бренд-настройки сайта.
 *
 * @internal
 */
final class TestBrandSettings extends SettingsResource
{
    public function fields(): array
    {
        return [
            Input::make('site_name')->required()->default('My Site'),
            Input::make('contact_email')->type('email')->default('contact@example.com'),
            Number::make('items_per_page')->integer()->min(1)->max(100)->default(25),
        ];
    }
}
