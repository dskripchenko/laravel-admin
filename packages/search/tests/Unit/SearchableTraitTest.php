<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch\Tests\Unit;

use Dskripchenko\LaravelAdminSearch\Concerns\Searchable;
use Dskripchenko\LaravelAdminSearch\Tests\TestCase;

class TestSearchResource
{
    use Searchable;

    public static string $icon = 'user';

    public static function slug(): string
    {
        return 'test-users';
    }

    public function searchableFields(): array
    {
        return ['name'];
    }
}

class TestSearchResourceWithCustom
{
    use Searchable;

    public static string $icon = 'star';

    public static function slug(): string
    {
        return 'custom';
    }

    public function searchableFields(): array
    {
        return ['name', 'email'];
    }

    public function searchTitle(): string
    {
        return 'fullname';
    }

    public function searchSubtitle(): ?string
    {
        return 'email';
    }

    public function searchPriority(): int
    {
        return 100;
    }
}

final class SearchableTraitTest extends TestCase
{
    public function test_default_search_title_is_name(): void
    {
        $r = new TestSearchResource;
        $this->assertSame('name', $r->searchTitle());
        $this->assertNull($r->searchSubtitle());
    }

    public function test_overrides_work(): void
    {
        $r = new TestSearchResourceWithCustom;
        $this->assertSame('fullname', $r->searchTitle());
        $this->assertSame('email', $r->searchSubtitle());
        $this->assertSame(100, $r->searchPriority());
    }

    public function test_default_url_is_edit_route(): void
    {
        $r = new TestSearchResource;
        $url = $r->searchUrl(['id' => 42]);
        $this->assertSame('/admin/r/test-users/42/edit', $url);
    }

    public function test_returns_empty_url_when_no_id(): void
    {
        $r = new TestSearchResource;
        $this->assertSame('', $r->searchUrl([]));
    }

    public function test_default_icon_from_static_property(): void
    {
        $r = new TestSearchResource;
        $this->assertSame('user', $r->searchIcon());
    }
}
