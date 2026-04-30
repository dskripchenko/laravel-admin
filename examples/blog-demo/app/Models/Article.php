<?php

declare(strict_types=1);

namespace App\Models;

use Dskripchenko\LaravelAdmin\Audit\Concerns\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $category_id
 * @property string $title
 * @property string $slug
 * @property string|null $body
 * @property bool $is_published
 * @property \Illuminate\Support\Carbon|null $published_at
 */
class Article extends Model
{
    use Loggable;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'body',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
