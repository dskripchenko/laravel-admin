<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Code;
use Dskripchenko\LaravelAdmin\Field\ColorPicker;
use Dskripchenko\LaravelAdmin\Field\Group;
use Dskripchenko\LaravelAdmin\Field\Hidden;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Markdown;
use Dskripchenko\LaravelAdmin\Field\MorphSwitcher;
use Dskripchenko\LaravelAdmin\Field\Radio;
use Dskripchenko\LaravelAdmin\Field\TreeSelect;
use Dskripchenko\LaravelAdmin\Infolist\FieldEntry;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if (! class_exists('FieldViewTreeNodeModel')) {
    /** @internal */
    final class FieldViewTreeNodeModel extends Model
    {
        protected $table = 'field_view_tree_nodes';

        protected $guarded = [];

        public $timestamps = false;
    }
}

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->timestamps();
        });
    }
    if (! Schema::hasTable('field_view_tree_nodes')) {
        Schema::create('field_view_tree_nodes', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('name');
        });
    }
});

it('FieldEntry carries the whole serialized field', function (): void {
    $arr = FieldEntry::fromField(Markdown::make('body')->title('Body')->preview(false))->toArray();

    expect($arr['type'])->toBe('field');
    expect($arr['name'])->toBe('body');
    expect($arr['label'])->toBe('Body');
    expect($arr['attributes']['field']['type'])->toBe('markdown');
    expect($arr['attributes']['field']['attributes']['preview'])->toBeFalse();
});

it('the default infolist gives field views, a colour swatch, and skips hidden fields', function (): void {
    $resource = new class extends Resource
    {
        public static string $model = TestResourceUserModel::class;

        public function fields(): array
        {
            return [
                Input::make('name')->title('Name'),
                Markdown::make('body')->title('Body'),
                Code::make('snippet')->language('php'),
                Radio::make('kind')->options(['a' => 'A', 'b' => 'B']),
                ColorPicker::make('tint'),
                Group::make('address')->fields([Input::make('city')]),
                Hidden::make('token'),
            ];
        }

        public function columns(): array
        {
            return [];
        }
    };

    $types = array_map(static fn ($e): array => [$e->name(), $e->entryType()], $resource->infolist());

    expect($types)->toBe([
        ['name', 'text'],
        ['body', 'field'],
        ['snippet', 'field'],
        ['kind', 'field'],
        ['tint', 'color'],
        ['address', 'field'],
    ]);
    $radio = $resource->infolist()[3]->toArray();
    expect($radio['attributes']['field']['attributes']['options'])->toBe([
        ['value' => 'a', 'label' => 'A'],
        ['value' => 'b', 'label' => 'B'],
    ]);
});

it('TreeSelect::fromModel builds the nested tree at serialization', function (): void {
    $root = FieldViewTreeNodeModel::create(['name' => 'Electronics']);
    $phones = FieldViewTreeNodeModel::create(['name' => 'Phones', 'parent_id' => $root->id]);
    FieldViewTreeNodeModel::create(['name' => 'Smartphones', 'parent_id' => $phones->id]);
    FieldViewTreeNodeModel::create(['name' => 'Books']);

    $tree = TreeSelect::make('category_id')->fromModel(FieldViewTreeNodeModel::class)->toArray()['attributes']['tree'];

    expect($tree)->toHaveCount(2);
    expect($tree[0]['label'])->toBe('Electronics');
    expect($tree[0]['children'][0]['label'])->toBe('Phones');
    expect($tree[0]['children'][0]['children'][0]['label'])->toBe('Smartphones');
    expect($tree[1])->toBe(['value' => 4, 'label' => 'Books']);
});

it('TreeSelect keeps an explicit tree and survives a cycle in the data', function (): void {
    $explicit = [['value' => 1, 'label' => 'X']];
    expect(TreeSelect::make('c')->tree($explicit)->fromModel(FieldViewTreeNodeModel::class)->toArray()['attributes']['tree'])
        ->toBe($explicit);

    $a = FieldViewTreeNodeModel::create(['name' => 'A']);
    $b = FieldViewTreeNodeModel::create(['name' => 'B', 'parent_id' => $a->id]);
    $a->update(['parent_id' => $b->id]);

    // Both rows have a parent in the result, so neither is a root: nothing to show, nothing to loop on.
    expect(TreeSelect::make('c')->fromModel(FieldViewTreeNodeModel::class)->toArray()['attributes']['tree'])->toBe([]);
});

it('MorphSwitcher serializes each type with its records as options', function (): void {
    TestResourceUserModel::create(['name' => 'Alice', 'email' => 'a@example.com', 'password' => 'x']);

    $missingTable = get_class(new class extends Model
    {
        protected $table = 'field_view_no_such_table';
    });

    $types = MorphSwitcher::make('subject')
        ->morph('user', TestResourceUserModel::class)
        ->morph('broken', $missingTable)
        ->toArray()['attributes']['morphTypes'];

    expect($types['user']['options'])->toBe([['value' => 1, 'label' => 'Alice']]);
    // A failing query leaves the type without options instead of failing the manifest.
    expect($types['broken']['options'])->toBe([]);
});
