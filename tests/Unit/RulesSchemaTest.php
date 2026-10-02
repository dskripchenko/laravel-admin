<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\FileUpload;
use Dskripchenko\LaravelAdmin\Field\TranslatableInput;
use Dskripchenko\LaravelAdmin\Field\ValidationRulesExporter;
use Dskripchenko\LaravelAdmin\Http\OpenApi\RulesSchema;
use Illuminate\Validation\Rule;

enum RulesSchemaTestColour: string
{
    case Red = 'red';
    case Blue = 'blue';
}

it('maps the common rules onto JSON Schema keywords', function (): void {
    $schema = RulesSchema::object([
        'name' => ['required', 'string', 'between:2,40'],
        'pin' => ['nullable', 'string', 'size:4', 'regex:/^[0-9]+$/'],
        'color' => ['nullable', 'regex:/^#?[0-9a-f]{3,8}$/i'],
        'age' => ['integer', 'min:18'],
        'site' => ['url'],
        'ids' => ['required', 'array', 'min:1'],
        'ids.*' => ['integer'],
        'colour' => [Rule::enum(RulesSchemaTestColour::class)],
        'password' => ['required', 'string', 'confirmed'],
    ]);

    $p = $schema['properties'];
    expect($p['name'])->toMatchArray(['type' => 'string', 'minLength' => 2, 'maxLength' => 40]);
    expect($p['pin'])->toMatchArray(['minLength' => 4, 'maxLength' => 4, 'pattern' => '^[0-9]+$', 'nullable' => true]);
    // A flagged regex has nowhere to put its flags: left to the validator.
    expect($p['color'])->not->toHaveKey('pattern');
    expect($p['age'])->toMatchArray(['type' => 'integer', 'minimum' => 18]);
    expect($p['site'])->toMatchArray(['type' => 'string', 'format' => 'uri']);
    expect($p['ids'])->toMatchArray(['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'integer']]);
    expect($p['colour']['enum'])->toBe(['red', 'blue']);
    expect($p)->toHaveKey('password_confirmation');
    expect($schema['required'])->toBe(['name', 'ids', 'password', 'password_confirmation']);
});

it('describes the upload-first shape of a file field as an object', function (): void {
    $field = FileUpload::make('avatar');
    $schema = RulesSchema::object(ValidationRulesExporter::export([$field]), [$field]);

    $avatar = $schema['properties']['avatar'];
    expect($avatar['type'])->toBe('object');
    expect(array_keys($avatar['properties']))->toBe(['disk', 'path']);
    expect($avatar['properties']['disk']['type'])->toBe('string');
});

it('describes a translatable value as one string per locale', function (): void {
    $field = TranslatableInput::make('title')->locales(['ru', 'en'])->requireAllLocales();
    $schema = RulesSchema::object(ValidationRulesExporter::export([$field]), [$field]);

    expect($schema['properties']['title'])->toMatchArray([
        'type' => 'object',
        'properties' => ['ru' => ['type' => 'string'], 'en' => ['type' => 'string']],
        'required' => ['ru', 'en'],
    ]);
});

it('returns an empty object for no rules', function (): void {
    expect(RulesSchema::object([]))->toBe(['type' => 'object', 'properties' => []]);
});
