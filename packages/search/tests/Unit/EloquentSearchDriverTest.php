<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch\Tests\Unit;

use Dskripchenko\LaravelAdminSearch\Drivers\EloquentSearchDriver;
use Dskripchenko\LaravelAdminSearch\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

/** Inline-fixture model для search-driver tests. */
class TestSearchUser extends Model
{
    protected $table = 'test_search_users';

    protected $guarded = ['id'];
}

final class EloquentSearchDriverTest extends TestCase
{
    public function test_finds_by_name_substring(): void
    {
        TestSearchUser::create(['name' => 'Иван Петров', 'email' => 'ivan@a.com']);
        TestSearchUser::create(['name' => 'Анна Сидорова', 'email' => 'anna@b.com']);

        $driver = new EloquentSearchDriver;
        $rows = $driver->search(TestSearchUser::class, ['name', 'email'], 'Иван', 10);

        $this->assertCount(1, $rows);
        $this->assertSame('Иван Петров', $rows[0]['name']);
    }

    public function test_finds_by_email_substring(): void
    {
        TestSearchUser::create(['name' => 'Foo', 'email' => 'foo@example.com']);
        TestSearchUser::create(['name' => 'Bar', 'email' => 'bar@other.com']);

        $driver = new EloquentSearchDriver;
        $rows = $driver->search(TestSearchUser::class, ['name', 'email'], 'example', 10);

        $this->assertCount(1, $rows);
        $this->assertSame('foo@example.com', $rows[0]['email']);
    }

    public function test_respects_limit(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            TestSearchUser::create(['name' => "User $i", 'email' => "u$i@a.com"]);
        }
        $driver = new EloquentSearchDriver;
        $rows = $driver->search(TestSearchUser::class, ['name'], 'User', 5);

        $this->assertCount(5, $rows);
    }

    public function test_returns_empty_for_empty_fields(): void
    {
        $driver = new EloquentSearchDriver;
        $this->assertSame([], $driver->search(TestSearchUser::class, [], 'whatever', 10));
    }

    public function test_returns_empty_for_unknown_class(): void
    {
        $driver = new EloquentSearchDriver;
        $this->assertSame([], $driver->search('NoSuchClass', ['name'], 'whatever', 10));
    }
}
