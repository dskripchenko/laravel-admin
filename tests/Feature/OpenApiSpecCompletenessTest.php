<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Screen\ScreenRegistry;
use Dskripchenko\LaravelAdmin\Settings\SettingsRegistry;
use Dskripchenko\LaravelApi\Services\Linter\LintIssue;
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;

/**
 * The admin's OpenAPI document has to describe what every endpoint accepts.
 *
 * One ResourceController serves every resource, so the fields of `create` and
 * `update` are only known per resource; they come from `[operationSchema]`,
 * which laravel-api asks once per route with the resource slug in the context.
 * These tests walk the generated document for a set of fixture resources and
 * fail when an operation that validates input documents none, when a
 * resource's fields go missing, or when a `$ref` points nowhere.
 */
const SPEC_RESOURCES = [
    TestSpecResource::class,
    TestUserResource::class,
    TestSoftDeleteResource::class,
    TestReorderableResource::class,
    TestTreeNodeResource::class,
    TestActionResource::class,
    TestReplicableResource::class,
    TestArticleResource::class,
    TestEditableResource::class,
];

beforeEach(function (): void {
    $resources = app(ResourceRegistry::class);
    $resources->clear();
    foreach (SPEC_RESOURCES as $class) {
        $resources->add($class);
    }

    $settings = app(SettingsRegistry::class);
    $settings->clear();
    $settings->add(TestBrandSettings::class);

    $screens = app(ScreenRegistry::class);
    $screens->clear();
    $screens->add(TestContactScreen::class);

    AdminApi::clearCache();
});

/**
 * @return array<string, mixed>
 */
function adminSpec(): array
{
    // Round-trip through JSON: that is the document clients actually read.
    return json_decode(json_encode(AdminApi::getOpenApiConfig('admin'), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
}

/**
 * @param  array<string, mixed>  $spec
 * @return array<string, mixed>
 */
function bodySchema(array $spec, string $path): array
{
    $content = $spec['paths'][$path]['post']['requestBody']['content'] ?? [];
    expect($content)->toHaveKey('application/json');

    return $content['application/json']['schema'];
}

it('documents every field of a resource on create, with types, enums, bounds and required', function (): void {
    $schema = bodySchema(adminSpec(), '/admin/test-specs/create');
    $properties = $schema['properties'];

    expect(array_keys($properties))->toBe([
        'title', 'email', 'status', 'labels', 'price', 'quantity', 'active',
        'published_on', 'keywords', 'code', 'secret',
    ]);

    expect($properties['title'])->toMatchArray(['type' => 'string', 'maxLength' => 120, 'description' => 'Title']);
    expect($properties['email'])->toMatchArray(['type' => 'string', 'format' => 'email']);
    expect($properties['status'])->toMatchArray(['type' => 'string', 'enum' => ['draft', 'published']]);
    expect($properties['labels']['type'])->toBe('array');
    expect($properties['labels']['items'])->toBe(['type' => 'string', 'enum' => ['red', 'green']]);
    expect($properties['price'])->toMatchArray(['type' => 'number', 'minimum' => 0, 'maximum' => 1000]);
    expect($properties['quantity']['type'])->toBe('integer');
    expect($properties['active'])->toMatchArray(['type' => 'boolean', 'default' => true]);
    expect($properties['published_on'])->toMatchArray(['type' => 'string', 'format' => 'date']);
    expect($properties['keywords'])->toMatchArray(['type' => 'array', 'items' => ['type' => 'string']]);
    expect($properties['code']['enum'])->toBe(['a1', 'b2']);
    expect($properties['code']['nullable'])->toBeTrue();

    expect($schema['required'])->toBe(['title', 'status', 'secret']);
});

it('documents the record id and the update-context fields on update', function (): void {
    $schema = bodySchema(adminSpec(), '/admin/test-specs/update');

    expect($schema['properties'])->toHaveKey('id')
        ->and($schema['properties'])->toHaveKey('title')
        // onUpdate(false): not accepted on update, so not documented there.
        ->and($schema['properties'])->not->toHaveKey('secret');
    expect($schema['required'])->toBe(['id', 'title', 'status']);
});

it('documents create and update input for every registered resource', function (): void {
    $spec = adminSpec();

    foreach (SPEC_RESOURCES as $class) {
        $resource = app($class);
        $slug = $class::slug();

        foreach (['create', 'update'] as $context) {
            $expected = array_keys($resource->validationRules($context));
            $expected = array_values(array_unique(array_map(static fn (string $k): string => explode('.', $k)[0], $expected)));
            $documented = array_keys(bodySchema($spec, "/admin/{$slug}/{$context}")['properties']);

            expect(array_diff($expected, $documented))
                ->toBe([], "{$slug}/{$context} validates fields the spec does not describe");
        }
    }
});

it('documents the resource-specific input of search, export, action and reorder', function (): void {
    $spec = adminSpec();

    $search = bodySchema($spec, '/admin/test-specs/search')['properties'];
    expect(array_keys($search))->toContain('page', 'per_page', 'filters', 'q', 'order', 'group_by');
    expect($search['order']['items']['properties']['column']['enum'])->toBe(['id', 'title']);
    expect($search['filters']['oneOf'][1]['items']['properties']['column']['enum'])->toBe(['title']);

    $export = collect($spec['paths']['/admin/test-specs/export']['get']['parameters'])->keyBy('name');
    expect($export->keys()->all())->toContain('format', 'columns', 'filters', 'q');
    expect($export['columns']['schema']['items']['enum'])->toBe(['id', 'title', 'status']);

    $action = bodySchema($spec, '/admin/test-specs/action');
    expect($action['properties']['key']['enum'])->toBe(['publish']);
    expect($action['required'])->toContain('ids', 'key');

    $reorder = bodySchema($spec, '/admin/test-reorderables/reorder');
    expect($reorder['properties']['items']['items']['required'])->toBe(['id', 'position']);
});

it('documents the values of a settings group', function (): void {
    $values = bodySchema(adminSpec(), '/admin/settings_test-brand/update')['properties']['values'];

    expect(array_keys($values['properties']))->toBe(['site_name', 'contact_email', 'items_per_page']);
    expect($values['properties']['items_per_page'])->toMatchArray(['type' => 'integer', 'minimum' => 1, 'maximum' => 100]);
    expect($values['required'])->toBe(['site_name']);
});

it('references no schema that is not defined', function (): void {
    $spec = adminSpec();
    $defined = array_keys($spec['components']['schemas'] ?? []);

    $refs = [];
    array_walk_recursive($spec, static function ($value, $key) use (&$refs): void {
        if ($key === '$ref' && is_string($value)) {
            $refs[] = $value;
        }
    });

    $missing = array_values(array_unique(array_filter(
        $refs,
        static fn (string $ref): bool => ! in_array(substr($ref, strlen('#/components/schemas/')), $defined, true),
    )));

    expect($refs)->not->toBeEmpty();
    expect($missing)->toBe([]);
});

it('lints clean: no operation that validates input and declares none, nothing else either', function (): void {
    // `input.undeclared` is the regression guard for missing markup; the rest
    // (unknown templates and schemes, malformed tags) keeps the markup that is
    // there honest. `api:lint --strict` fails on any of them.
    $issues = (new OpenApiLinter)->lintVersionList(['admin' => AdminApi::class]);

    expect(array_map(
        static fn (LintIssue $i): string => "{$i->severity} {$i->rule} {$i->where}: {$i->message}",
        $issues,
    ))->toBe([]);
});

it('gives every POST operation that takes input a request body', function (): void {
    // A guard against a `[method]` quietly answering nothing: every operation
    // whose docblock names input must come out with some in the document.
    $spec = adminSpec();
    $empty = [];
    foreach ($spec['paths'] as $path => $operations) {
        foreach ($operations as $verb => $operation) {
            $hasInput = isset($operation['requestBody'])
                || collect($operation['parameters'] ?? [])->where('in', '!=', 'header')->isNotEmpty();
            $action = basename($path);
            if (! $hasInput && in_array($action, ['create', 'update', 'search', 'action', 'reorder', 'inlineUpdate', 'export', 'tree', 'summary'], true)) {
                $empty[] = "{$verb} {$path}";
            }
        }
    }

    expect($empty)->toBe([]);
});
