<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

/**
 * A minimal Eloquent model for the resource tests.
 *
 * @internal
 */
final class TestResourceUserModel extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
