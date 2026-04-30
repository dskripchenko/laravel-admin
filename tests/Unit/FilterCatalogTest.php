<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Filter\DateRangeFilter;
use Dskripchenko\LaravelAdmin\Filter\HttpFilterParser;
use Dskripchenko\LaravelAdmin\Filter\OptionsFilter;
use Dskripchenko\LaravelAdmin\Filter\QueryFilter;
use Dskripchenko\LaravelAdmin\Filter\SelectFromModelFilter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->string('status')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
    }
});

it('DateRangeFilter applies between when both from/to provided', function (): void {
    TestResourceUserModel::create(['name' => 'A', 'created_at' => '2024-01-15']);
    TestResourceUserModel::create(['name' => 'B', 'created_at' => '2024-06-15']);
    TestResourceUserModel::create(['name' => 'C', 'created_at' => '2024-12-15']);

    $filter = DateRangeFilter::for('created_at');
    $query = TestResourceUserModel::query();
    $query = $filter->apply($query, ['from' => '2024-04-01', 'to' => '2024-08-01']);

    expect($query->count())->toBe(1);
    expect($query->first()->name)->toBe('B');
});

it('DateRangeFilter only-from is open-ended above', function (): void {
    TestResourceUserModel::create(['name' => 'old', 'created_at' => '2020-01-01']);
    TestResourceUserModel::create(['name' => 'new', 'created_at' => '2025-01-01']);

    $query = DateRangeFilter::for('created_at')
        ->apply(TestResourceUserModel::query(), ['from' => '2024-01-01']);

    expect($query->count())->toBe(1);
});

it('DateRangeFilter ignores empty value', function (): void {
    $base = TestResourceUserModel::query()->getQuery()->wheres;
    $applied = DateRangeFilter::for('created_at')
        ->apply(TestResourceUserModel::query(), null);
    expect($applied->getQuery()->wheres)->toEqual($base);
});

it('OptionsFilter applies = on single value', function (): void {
    TestResourceUserModel::create(['name' => 'A', 'status' => 'active']);
    TestResourceUserModel::create(['name' => 'B', 'status' => 'banned']);

    $filter = OptionsFilter::for('status')->options(['active' => 'A', 'banned' => 'B']);
    $query = $filter->apply(TestResourceUserModel::query(), 'active');
    expect($query->count())->toBe(1);
});

it('OptionsFilter::multiple applies whereIn', function (): void {
    TestResourceUserModel::create(['name' => 'A', 'status' => 'active']);
    TestResourceUserModel::create(['name' => 'B', 'status' => 'pending']);
    TestResourceUserModel::create(['name' => 'C', 'status' => 'banned']);

    $filter = OptionsFilter::for('status')
        ->options(['active' => 'A', 'pending' => 'P', 'banned' => 'B'])
        ->multiple();
    $query = $filter->apply(TestResourceUserModel::query(), ['active', 'pending']);
    expect($query->count())->toBe(2);
});

it('OptionsFilter::toArray includes options as list', function (): void {
    $arr = OptionsFilter::for('s')->options(['a' => 'A', 'b' => 'B'])->toArray();
    expect($arr['options'])->toBe([
        ['value' => 'a', 'label' => 'A'],
        ['value' => 'b', 'label' => 'B'],
    ]);
});

it('SelectFromModelFilter loads options from Eloquent', function (): void {
    TestResourceUserModel::create(['name' => 'Alice']);
    TestResourceUserModel::create(['name' => 'Bob']);

    $filter = SelectFromModelFilter::for('user_id')
        ->fromModel(TestResourceUserModel::class, 'name');
    $arr = $filter->toArray();

    expect($arr['type'])->toBe('select_from_model');
    expect($arr['options'])->toHaveCount(2);
    expect(array_column($arr['options'], 'label'))->toContain('Alice', 'Bob');
});

it('SelectFromModelFilter applies = on single value', function (): void {
    TestResourceUserModel::create(['name' => 'A']);
    $row = TestResourceUserModel::create(['name' => 'B']);

    $query = SelectFromModelFilter::for('id')
        ->fromModel(TestResourceUserModel::class, 'name')
        ->apply(TestResourceUserModel::query(), $row->id);

    expect($query->count())->toBe(1);
    expect($query->first()->name)->toBe('B');
});

it('QueryFilter delegates to callback', function (): void {
    TestResourceUserModel::create(['name' => 'foo', 'email' => 'bar@example.com']);
    TestResourceUserModel::create(['name' => 'qux', 'email' => 'baz@example.com']);

    $filter = QueryFilter::for('name_or_email')
        ->using(fn ($q, $v) => $q->where('name', 'like', "%{$v}%")
            ->orWhere('email', 'like', "%{$v}%"));

    $query = $filter->apply(TestResourceUserModel::query(), 'foo');
    expect($query->count())->toBe(1);
});

it('QueryFilter::as overrides UI type', function (): void {
    $f = QueryFilter::for('x')->as('switcher')->using(fn ($q) => $q);
    expect($f->type())->toBe('switcher');
});

it('HttpFilterParser parses map form', function (): void {
    $req = Request::create('/?filters[email]=ivan&filters[is_active]=1');
    expect(HttpFilterParser::parse($req))->toBe([
        'email' => 'ivan',
        'is_active' => '1',
    ]);
});

it('HttpFilterParser parses list form with column/value', function (): void {
    $req = Request::create('/', 'GET', [
        'filters' => [
            ['column' => 'email', 'value' => 'ivan'],
            ['column' => 'status', 'value' => 'active'],
        ],
    ]);
    expect(HttpFilterParser::parse($req))->toBe([
        'email' => 'ivan',
        'status' => 'active',
    ]);
});

it('HttpFilterParser parses range/object form', function (): void {
    $req = Request::create('/', 'GET', [
        'filters' => ['created_at' => ['from' => '2024-01-01', 'to' => '2024-12-31']],
    ]);
    expect(HttpFilterParser::parse($req))->toBe([
        'created_at' => ['from' => '2024-01-01', 'to' => '2024-12-31'],
    ]);
});

it('HttpFilterParser::searchTerm trims q', function (): void {
    $req = Request::create('/', 'GET', ['q' => '  hello  ']);
    expect(HttpFilterParser::searchTerm($req))->toBe('hello');
});

it('HttpFilterParser returns empty array for non-array filters', function (): void {
    $req = Request::create('/?filters=string-value');
    expect(HttpFilterParser::parse($req))->toBe([]);
});
