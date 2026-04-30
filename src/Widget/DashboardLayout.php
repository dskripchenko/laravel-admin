<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Persisted per-user dashboard customization.
 *
 * Хранит порядок и размер виджетов для конкретного пользователя на конкретном
 * dashboard'е (по `dashboard_key`). Если для пользователя нет записи —
 * возвращается дефолтный layout из самого DashboardScreen.
 *
 * @property int $id
 * @property string $dashboard_key
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property array<int, array{slug: string, size: int, position: int, hidden?: bool}> $widgets
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class DashboardLayout extends Model
{
    protected $table = 'admin_dashboard_layouts';

    protected $fillable = [
        'dashboard_key',
        'owner_type',
        'owner_id',
        'widgets',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'widgets' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
