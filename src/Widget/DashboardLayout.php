<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Persisted per-user dashboard customization.
 *
 * It stores the order and the size of the widgets for one user on one
 * dashboard, keyed by `dashboard_key`. When there is no row for that user, the
 * DashboardScreen's own default layout is returned.
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
        'period',
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
