<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Console\Support\FieldTypeInferrer;
use Dskripchenko\LaravelAdmin\Console\Support\ResourceGenerator;
use Dskripchenko\LaravelAdmin\Console\Support\ResourceWriter;
use Dskripchenko\LaravelAdmin\Console\Support\SchemaIntrospector;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Support\Manifest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The contract of the resource generator (`admin:make-section`).
 *
 * The generator runs over a set of typical schemas — users with secrets,
 * posts with foreign keys and soft deletes, enums, JSON, dates, and a table
 * with no model — and the classes it writes are loaded, registered and
 * served: the manifest must build, and the list, the create form and the
 * create action must answer 200. On top of that, every method the generated
 * code calls must exist on the class it is called on, so that nothing is
 * silently swallowed by Field's attribute catch-all either.
 */
enum GenContractPriority: string
{
    case Low = 'low';
    case High = 'high';
}

class GenContractUser extends Model
{
    protected $table = 'gen_users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}

class GenContractCategory extends Model
{
    protected $table = 'gen_categories';

    protected $guarded = [];
}

class GenContractPost extends Model
{
    use SoftDeletes;

    protected $table = 'gen_posts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'price' => 'decimal:2'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(GenContractUser::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GenContractCategory::class, 'category_id');
    }
}

class GenContractTicket extends Model
{
    protected $table = 'gen_tickets';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['priority' => GenContractPriority::class];
    }
}

class GenContractSetting extends Model
{
    protected $table = 'gen_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}

class GenContractEvent extends Model
{
    protected $table = 'gen_events';

    protected $guarded = [];
}

/**
 * Runs the generator over a table — through its model when there is one —
 * writes the class, loads it and returns its FQCN.
 *
 * @param  class-string<Model>|null  $model
 */
function generateContractResource(string $table, ?string $model, string $singular, ?string &$source = null): string
{
    static $run = 0;
    $run++;

    $schema = new SchemaIntrospector;
    $analysis = $schema->analyzeTable($table);
    $relations = [];
    $context = ['hidden' => [], 'casts' => []];

    if ($model !== null) {
        $modelAnalysis = $schema->analyzeModel($model);
        $relations = array_map(static function (array $rel) use ($schema): array {
            if ($rel['type'] === 'BelongsTo' && $rel['related'] !== null) {
                $rel['display'] = $schema->pickDisplayColumn($rel['related']);
            }

            return $rel;
        }, $modelAnalysis['relations']);
        $context = ['hidden' => $modelAnalysis['hidden'], 'casts' => $modelAnalysis['casts']];
    } else {
        // What the wizard does for a bare table: it writes a model with these casts.
        $context['casts'] = (new FieldTypeInferrer)->modelCasts($analysis['columns']);
        $model = 'GenContractTableModel'.$run;
        $casts = var_export($context['casts'], true);
        eval("class {$model} extends \\Illuminate\\Database\\Eloquent\\Model { protected \$table = '{$table}'; protected \$guarded = []; protected \$casts = {$casts}; }");
    }

    $writer = new ResourceWriter(new Illuminate\Filesystem\Filesystem);
    $class = $writer->classNameFor($singular, 'Resource');
    $namespace = 'GenContract\\Run'.$run;

    $source = app(ResourceGenerator::class)->render([
        'namespace' => $namespace,
        'class' => $class,
        'model' => $model,
        'slug' => 'gen-'.$writer->resourceSlugFor($singular),
        'label' => Illuminate\Support\Str::plural($singular),
        'singularLabel' => $singular,
        'permission' => 'admin.gen-'.$writer->resourceSlugFor($singular),
        'icon' => 'box',
        'columns' => $analysis['columns'],
        'relations' => $relations,
        'context' => $context,
        'stub' => $writer->stubPath('resource.stub'),
    ]);

    $path = sys_get_temp_dir().'/laravel-admin-gen-'.uniqid().'-'.$class.'.php';
    file_put_contents($path, $source);
    require $path;
    @unlink($path);

    return $namespace.'\\'.$class;
}

/**
 * The `->method(` calls of the chains in the generated source, by the class
 * the chain starts from. Each must be a real method of that class, or one
 * its docblock declares (Field's @method title/placeholder/readonly, Number's
 * step) — a method Field::__call would swallow silently does not count.
 *
 * @return list<string> the offending `Class::method` pairs
 */
function unknownGeneratedMethods(string $source, string $resourceClass): array
{
    $imports = [];
    preg_match_all('/^use ([\w\\\\]+)(?: as (\w+))?;/m', $source, $uses, PREG_SET_ORDER);
    foreach ($uses as $use) {
        $imports[$use[2] ?? class_basename($use[1])] = $use[1];
    }

    $bad = [];
    preg_match_all('/(?<![\w\\\\])([A-Z]\w*)::(make|for)\((.*?)\),?\n/', $source, $chains, PREG_SET_ORDER);
    foreach ($chains as $chain) {
        $fqcn = $imports[$chain[1]] ?? null;
        expect($fqcn)->not->toBeNull("{$chain[1]} is used but not imported");
        preg_match_all('/->(\w+)\(/', $chain[0], $calls);
        foreach ($calls[1] as $method) {
            if (! generatedMethodExists((string) $fqcn, $method)) {
                $bad[] = $chain[1].'::'.$method;
            }
        }
    }

    return $bad;
}

function generatedMethodExists(string $class, string $method): bool
{
    $reflection = new ReflectionClass($class);
    if ($reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic() && $method !== '__call') {
        return true;
    }
    for ($r = $reflection; $r !== false; $r = $r->getParentClass()) {
        if (preg_match('/@method\s+(?:static\s+)?\S+\s+'.preg_quote($method, '/').'\(/', (string) $r->getDocComment()) === 1) {
            return true;
        }
    }

    return false;
}

beforeEach(function (): void {
    Schema::create('gen_users', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->string('email')->unique();
        $t->timestamp('email_verified_at')->nullable();
        $t->string('password');
        $t->rememberToken();
        $t->string('api_token', 80)->nullable();
        $t->text('two_factor_secret')->nullable();
        $t->text('recovery_codes')->nullable();
        $t->boolean('is_admin')->default(false);
        $t->timestamps();
    });
    Schema::create('gen_categories', function (Blueprint $t): void {
        $t->id();
        $t->string('title');
        $t->timestamps();
    });
    Schema::create('gen_posts', function (Blueprint $t): void {
        $t->id();
        $t->foreignId('author_id')->constrained('gen_users');
        $t->foreignId('category_id')->nullable()->constrained('gen_categories');
        $t->string('title');
        $t->string('slug')->unique();
        $t->text('excerpt')->nullable();
        $t->longText('body')->nullable();
        $t->decimal('price', 10, 2)->nullable();
        $t->unsignedInteger('views')->default(0);
        $t->timestamp('published_at')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('gen_tickets', function (Blueprint $t): void {
        $t->id();
        $t->string('subject');
        $t->enum('status', ['open', 'pending', 'closed'])->default('open');
        $t->enum('kind', ['bug', 'feature']);
        $t->string('priority');
        $t->timestamps();
    });
    Schema::create('gen_settings', function (Blueprint $t): void {
        $t->id();
        $t->string('key')->unique();
        $t->json('payload');
        $t->json('meta')->nullable();
        $t->string('color')->nullable();
        $t->timestamps();
    });
    Schema::create('gen_events', function (Blueprint $t): void {
        $t->id();
        $t->string('title');
        $t->date('starts_on');
        $t->dateTime('starts_at');
        $t->time('opens_at')->nullable();
        $t->timestamp('happened_at')->nullable();
        $t->timestamps();
    });
    Schema::create('gen_products', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->boolean('active')->default(true);
        $t->json('attributes')->nullable();
        $t->date('available_on')->nullable();
        $t->string('secret_token')->nullable();
        $t->timestamps();
    });

    app(ResourceRegistry::class)->clear();
    AdminApi::clearCache();

    $admin = AdminUser::create([
        'name' => 'Generator Admin',
        'email' => 'gen-admin-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'Super', 'slug' => 'gen-super-'.uniqid(), 'permissions' => ['*']]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

/**
 * @return array<string, array{0: string, 1: ?string, 2: string, 3: array<string, mixed>|null}>
 */
dataset('generator schemas', [
    // table, model, singular, a valid create payload (null: the form cannot
    // create a record — the users table needs a password the form leaves out)
    'users with secrets' => ['gen_users', GenContractUser::class, 'Member', null],
    'posts with foreign keys' => ['gen_posts', GenContractPost::class, 'Blog post', [
        'author_id' => '@user', 'title' => 'Hello', 'slug' => 'hello', 'body' => '<p>Hi</p>',
        'price' => 9.5, 'published_at' => '2026-10-04 10:00:00',
    ]],
    'enums' => ['gen_tickets', GenContractTicket::class, 'Ticket', [
        'subject' => 'Crash', 'kind' => 'bug', 'priority' => 'high',
    ]],
    'json' => ['gen_settings', GenContractSetting::class, 'Setting', [
        'key' => 'site', 'payload' => ['theme' => 'dark'], 'meta' => '{"a":1}', 'color' => '#ff0000',
    ]],
    'dates' => ['gen_events', GenContractEvent::class, 'Event', [
        'title' => 'Launch', 'starts_on' => '2026-10-04', 'starts_at' => '2026-10-04 10:00:00', 'opens_at' => '09:30',
    ]],
    'a table with no model' => ['gen_products', null, 'Product', [
        'name' => 'Chair', 'active' => true, 'attributes' => '{"size":"L"}', 'available_on' => '2026-10-04',
    ]],
]);

it('generates a resource that loads, builds the manifest and serves list and create', function (string $table, ?string $model, string $singular, ?array $payload): void {
    $resource = generateContractResource($table, $model, $singular, $source);

    expect(unknownGeneratedMethods($source, $resource))->toBe([]);

    app(ResourceRegistry::class)->add($resource);
    AdminApi::clearCache();
    app(Manifest::class)->flush();

    $slug = $resource::slug();

    $manifest = $this->getJson('/api/admin/system/manifest')->assertOk();
    expect(collect($manifest->json('payload.resources'))->pluck('slug'))->toContain($slug);

    $this->getJson("/api/admin/{$slug}/meta")->assertOk();
    $this->postJson("/api/admin/{$slug}/search")->assertOk();
    $this->getJson("/api/admin/{$slug}/listScreen")->assertOk();
    $this->getJson("/api/admin/{$slug}/createScreen")->assertOk();

    if ($payload !== null) {
        if (($payload['author_id'] ?? null) === '@user') {
            $payload['author_id'] = GenContractUser::query()->create([
                'name' => 'Author', 'email' => 'author-'.uniqid().'@example.com', 'password' => 'x',
            ])->getKey();
        }
        $this->postJson("/api/admin/{$slug}/create", $payload)->assertSuccessful();
        expect($resource::$model::query()->count())->toBe(1);

        // The created record lists, and reads back through the view screen.
        $this->postJson("/api/admin/{$slug}/search")->assertOk()->assertJsonCount(1, 'payload.data');
        $id = $resource::$model::query()->value('id');
        $this->getJson("/api/admin/{$slug}/read?id={$id}")->assertOk();
    }
})->with('generator schemas');

it('keeps secrets and hidden attributes out of the form, the list, the filters and the search', function (): void {
    generateContractResource('gen_users', GenContractUser::class, 'Member', $source);

    foreach (['password', 'remember_token', 'api_token', 'two_factor_secret', 'recovery_codes'] as $secret) {
        expect($source)->not->toContain("'{$secret}'");
    }
    expect($source)
        ->toContain("Input::make('email')->type('email')")
        ->toContain("Switcher::make('is_admin')")
        ->toContain("DatePicker::make('email_verified_at')->withTime()")
        ->toContain("return ['name', 'email'];");
});

it('maps foreign keys, enums, json and dates onto real fields, columns and filters', function (): void {
    generateContractResource('gen_posts', GenContractPost::class, 'Blog post', $posts);
    expect($posts)
        ->toContain("RelationSelect::make('author_id')->relation(\\GenContractUser::class, 'name')")
        ->toContain("SelectFromModelFilter::for('category_id')->fromModel(\\GenContractCategory::class, 'title')")
        ->toContain("Slug::make('slug')->from('title')")
        ->toContain("Wysiwyg::make('body')")
        ->toContain("TableColumn::make('price')->label('Price')->asMoney()")
        ->toContain("DateRangeFilter::for('published_at')")
        ->not->toContain('BaseDateFilter')
        ->not->toContain('->sortable(')
        ->not->toContain('->searchable(')
        ->not->toContain('->preset(')
        ->not->toContain('->view(');

    generateContractResource('gen_tickets', GenContractTicket::class, 'Ticket', $tickets);
    expect($tickets)
        ->toContain("Select::make('status')->options(['open' => 'Open', 'pending' => 'Pending', 'closed' => 'Closed'])")
        ->toContain("Select::make('priority')->fromEnum(\\GenContractPriority::class)")
        ->toContain("OptionsFilter::for('kind')->options(['bug' => 'Bug', 'feature' => 'Feature'])")
        ->toContain("TableColumn::make('status')->label('Status')->asBadge()");

    generateContractResource('gen_settings', GenContractSetting::class, 'Setting', $settings);
    expect($settings)
        ->toContain("KeyValue::make('payload')")
        // SQLite reports JSON as text; without an array cast it is edited as text.
        ->not->toContain("KeyValue::make('meta')")
        ->toContain("ColorPicker::make('color')");

    generateContractResource('gen_events', GenContractEvent::class, 'Event', $events);
    expect($events)
        ->toContain("DatePicker::make('starts_on')->title('Starts on')->required()")
        ->toContain("TimePicker::make('opens_at')")
        ->toContain("TableColumn::make('starts_at')->label('Starts at')->asDateTime()->sort()")
        ->toContain("TableColumn::make('starts_on')->label('Starts on')->asDate()->sort()");
});
