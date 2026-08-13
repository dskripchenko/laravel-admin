<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Wysiwyg;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * A resource with a WYSIWYG field, for the sanitization tests.
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
 * The trusted variant with the sanitization switched off (advanced use).
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
