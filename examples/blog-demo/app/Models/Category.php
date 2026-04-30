<?php

declare(strict_types=1);

namespace App\Models;

use Dskripchenko\LaravelAdmin\Audit\Concerns\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Category extends Model
{
    use Loggable;

    protected $fillable = ['name', 'slug', 'position'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
