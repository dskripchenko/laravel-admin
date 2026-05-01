<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Один sample телеметрии.
 *
 * @property int $id
 * @property string $kind 'request' | 'query' | 'job' | 'exception' | 'cache'
 * @property string $key
 * @property string|null $label
 * @property int $duration_ms
 * @property int|null $status_code
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon $sampled_at
 */
final class PulseSample extends Model
{
    protected $table = 'admin_pulse_samples';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
        'sampled_at' => 'datetime',
        'duration_ms' => 'integer',
        'status_code' => 'integer',
    ];
}
