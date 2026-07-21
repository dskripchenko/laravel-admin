<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\ResourceController;
use Illuminate\Database\QueryException;

/**
 * dbExceptionToValidation обязан класть field-ошибки в ключ `messages` —
 * SPA (ValidationError.fields) читает payload.messages; ключ `errors`
 * фронт молча игнорировал и DB-нарушения не подсвечивали поля.
 */
function dbExceptionPayload(string $sqlState, string $message): array
{
    $e = new QueryException('pgsql', 'insert into t', [], new Exception($message));
    (function () use ($sqlState, $message): void {
        $this->errorInfo = [$sqlState, 7, $message];
    })->call($e);

    $controller = (new ReflectionClass(ResourceController::class))->newInstanceWithoutConstructor();
    $m = new ReflectionMethod(ResourceController::class, 'dbExceptionToValidation');

    return $m->invoke($controller, $e);
}

it('maps unique violation to messages keyed by column', function (): void {
    $payload = dbExceptionPayload('23505', 'ERROR: duplicate key value violates unique constraint "t_key" DETAIL: Key (group_id, key)=(7, foo) already exists.');

    expect($payload['errorKey'])->toBe('unique_violation');
    expect($payload['messages'])->toHaveKeys(['group_id', 'key']);
    expect($payload)->not->toHaveKey('errors');
});

it('maps not-null violation to messages keyed by column', function (): void {
    $payload = dbExceptionPayload('23502', 'ERROR: null value in column "name" of relation "t" violates not-null constraint');

    expect($payload['errorKey'])->toBe('not_null_violation');
    expect($payload['messages'])->toHaveKey('name');
});
