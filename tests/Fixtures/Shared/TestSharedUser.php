<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests\Fixtures\Shared;

use Dskripchenko\LaravelAdmin\Auth\Concerns\HasAdminTwoFactor;
use Dskripchenko\LaravelAdmin\Permission\Concerns\HasAdminAccess;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The host application's own User in the shared strategy: Laravel's stock
 * model plus the two admin traits.
 *
 * @internal
 */
class TestSharedUser extends Authenticatable
{
    use HasAdminAccess;
    use HasAdminTwoFactor;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}

/**
 * A host model that decides admin access by itself.
 *
 * @internal
 */
final class TestSharedStaffUser extends TestSharedUser
{
    public function canAccessAdmin(string $panelId): bool
    {
        return str_ends_with((string) $this->getAttribute('email'), '@staff.test');
    }
}
