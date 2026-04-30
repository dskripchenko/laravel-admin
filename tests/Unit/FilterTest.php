<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Filter\SwitcherFilter;
use Illuminate\Database\Eloquent\Model;

/**
 * Минимальная Eloquent-модель для тестов фильтров.
 *
 * @internal
 */
final class TestFilterUserModel extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}

it('InputFilter applies LIKE on non-empty value', function (): void {
    $query = TestFilterUserModel::query();
    $applied = InputFilter::for('email')->apply($query, 'ivan');

    expect($applied->toSql())->toContain('"email" like ?');
    expect($applied->getBindings())->toContain('%ivan%');
});

it('InputFilter does nothing on empty value', function (): void {
    $query = TestFilterUserModel::query();
    $applied = InputFilter::for('email')->apply($query, '');

    expect($applied->toSql())->toBe('select * from "users"');
});

it('InputFilter does nothing on null', function (): void {
    $query = TestFilterUserModel::query();
    $applied = InputFilter::for('email')->apply($query, null);

    expect($applied->toSql())->toBe('select * from "users"');
});

it('SwitcherFilter applies WHERE = true for "true" string', function (): void {
    $query = TestFilterUserModel::query();
    $applied = SwitcherFilter::for('is_active')->apply($query, 'true');

    expect($applied->toSql())->toContain('"is_active" = ?');
    expect($applied->getBindings())->toContain(true);
});

it('SwitcherFilter applies WHERE = false for "0"', function (): void {
    $query = TestFilterUserModel::query();
    $applied = SwitcherFilter::for('is_active')->apply($query, '0');

    expect($applied->getBindings())->toContain(false);
});

it('SwitcherFilter ignores null', function (): void {
    $query = TestFilterUserModel::query();
    $applied = SwitcherFilter::for('is_active')->apply($query, null);

    expect($applied->toSql())->toBe('select * from "users"');
});

it('SwitcherFilter ignores garbage values', function (): void {
    $query = TestFilterUserModel::query();
    $applied = SwitcherFilter::for('is_active')->apply($query, 'maybe');

    expect($applied->toSql())->toBe('select * from "users"');
});

it('Filter toArray exposes name/label/type', function (): void {
    $arr = InputFilter::for('email')->label('Электронная почта')->toArray();
    expect($arr['name'])->toBe('email');
    expect($arr['label'])->toBe('Электронная почта');
    expect($arr['type'])->toBe('input');
});

it('Filter::label is humanized when not set', function (): void {
    $arr = InputFilter::for('user_email')->toArray();
    expect($arr['label'])->toBe('User email');
});

it('Filter default()', function (): void {
    $arr = SwitcherFilter::for('is_active')->default(true)->toArray();
    expect($arr['default'])->toBeTrue();
});

it('Filter::for returns new instance for static factory', function (): void {
    $a = InputFilter::for('email');
    $b = InputFilter::for('email');
    expect($a)->not->toBe($b);
});
