<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Audit\Concerns\Loggable;
use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 */
final class TestLoggablePost extends Model
{
    use Loggable;

    protected $table = 'logged_posts';

    protected $guarded = [];

    public function getAuditExcluded(): array
    {
        return ['secret'];
    }
}
