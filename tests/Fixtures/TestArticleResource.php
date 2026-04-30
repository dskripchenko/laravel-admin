<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Wysiwyg;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource с Wysiwyg-полем для тестов санитизации.
 *
 * @internal
 */
final class TestArticle extends Model
{
    protected $table = 'test_articles';

    protected $guarded = [];
}

/**
 * @internal
 */
final class TestArticleResource extends Resource
{
    public static string $model = TestArticle::class;

    public function fields(): array
    {
        return [
            Input::make('title')->required(),
            Wysiwyg::make('body')->preset('default'),
        ];
    }
}

/**
 * Trusted-вариант с отключённой санитизацией (advanced use).
 *
 * @internal
 */
final class TestTrustedArticleResource extends Resource
{
    public static string $model = TestArticle::class;

    public function fields(): array
    {
        return [
            Input::make('title')->required(),
            Wysiwyg::make('body')->sanitize(false),
        ];
    }
}
