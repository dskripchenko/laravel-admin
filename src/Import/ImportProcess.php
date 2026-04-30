<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eloquent-модель импорт-процесса. Привязана к resource_slug и owner'у
 * (обычно AdminUser, который запустил импорт).
 *
 * @property int $id
 * @property string $resource_slug
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string $source_path
 * @property array<string, string> $mapping
 * @property string $status
 * @property int $processed_count
 * @property int $created_count
 * @property int $updated_count
 * @property int $error_count
 * @property array<int, array{row: int, error: string}>|null $errors
 * @property string|null $process_uuid
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class ImportProcess extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'admin_import_processes';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'processed_count' => 0,
        'created_count' => 0,
        'updated_count' => 0,
        'error_count' => 0,
    ];

    protected $fillable = [
        'resource_slug',
        'owner_type',
        'owner_id',
        'source_path',
        'mapping',
        'status',
        'processed_count',
        'created_count',
        'updated_count',
        'error_count',
        'errors',
        'process_uuid',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'processed_count' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'error_count' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }
}
