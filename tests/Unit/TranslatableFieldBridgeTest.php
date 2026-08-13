<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\TranslatableInput;
use Dskripchenko\LaravelAdmin\Theme\TranslatableFieldBridge;
use Illuminate\Database\Eloquent\Model;

it('extract pulls translatable values out of payload by reference', function (): void {
    $payload = [
        'title' => ['ru' => 'Привет', 'en' => 'Hello'],
        'description' => ['ru' => 'Описание'],
        'untouched' => 'simple-string',
    ];

    $extracted = TranslatableFieldBridge::extract([
        TranslatableInput::make('title'),
        TranslatableInput::make('description'),
        Input::make('untouched'),
    ], $payload);

    expect($extracted)->toBe([
        'title' => ['ru' => 'Привет', 'en' => 'Hello'],
        'description' => ['ru' => 'Описание'],
    ]);
    // The translatable keys are removed from the payload.
    expect($payload)->toBe(['untouched' => 'simple-string']);
});

it('extract skips non-array values for translatable fields', function (): void {
    $payload = ['title' => 'broken-string'];
    $extracted = TranslatableFieldBridge::extract(
        [TranslatableInput::make('title')],
        $payload,
    );
    expect($extracted)->toBe([]);
    // the payload is cleared either way
    expect($payload)->not->toHaveKey('title');
});

it('extract leaves non-translatable fields alone', function (): void {
    $payload = ['email' => 'a@example.com'];
    $extracted = TranslatableFieldBridge::extract(
        [Input::make('email')],
        $payload,
    );
    expect($extracted)->toBe([]);
    expect($payload)->toBe(['email' => 'a@example.com']);
});

it('extract converts non-string locale values to string', function (): void {
    $payload = ['n' => ['ru' => 42, 'en' => null]];
    $extracted = TranslatableFieldBridge::extract(
        [TranslatableInput::make('n')],
        $payload,
    );
    expect($extracted['n'])->toBe(['ru' => '42', 'en' => '']);
});

it('extract skips empty/non-string locale keys', function (): void {
    $payload = ['n' => ['' => 'X', 'ru' => 'Привет']];
    $extracted = TranslatableFieldBridge::extract(
        [TranslatableInput::make('n')],
        $payload,
    );
    expect($extracted['n'])->toBe(['ru' => 'Привет']);
});

it('saveAll silently skips models without saveTranslation method', function (): void {
    $plainModel = new class extends Model
    {
        protected $table = 'test';
    };

    // It must not throw.
    TranslatableFieldBridge::saveAll($plainModel, [
        'title' => ['ru' => 'Привет'],
    ]);
    expect(true)->toBeTrue();
});

it('saveAll silently skips when no Language-rows exist', function (): void {
    expect(class_exists(Dskripchenko\LaravelTranslatable\Models\Language::class))->toBeTrue();

    $model = new class extends Model
    {
        public function saveTranslation(string $field, ?string $value, mixed $language = null): void
        {
            // It must not be called — we have no Language with code='ru'.
            throw new RuntimeException('should not be called');
        }
    };

    // Without a Language row for 'ru' saveAll skips.
    TranslatableFieldBridge::saveAll($model, ['title' => ['ru' => 'Привет']]);
    expect(true)->toBeTrue();
});
