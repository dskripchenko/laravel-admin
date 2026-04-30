<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Number;
use Dskripchenko\LaravelAdmin\Field\Switcher;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Settings\SettingsResource;

final class BlogSettings extends SettingsResource
{
    public function fields(): array
    {
        return [
            Input::make('site_name')
                ->required()
                ->default('My Blog')
                ->title('Название сайта'),

            Textarea::make('site_description')
                ->rows(3)
                ->title('Описание для SEO'),

            Number::make('articles_per_page')
                ->integer()
                ->min(5)
                ->max(50)
                ->default(10)
                ->title('Статей на странице'),

            Switcher::make('comments_enabled')
                ->default(true)
                ->title('Разрешить комментарии'),
        ];
    }
}
