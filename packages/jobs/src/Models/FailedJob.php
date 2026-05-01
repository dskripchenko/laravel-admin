<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent-обёртка над стандартной таблицей Laravel `failed_jobs`.
 *
 * Не имеет собственной миграции — использует существующую.
 *
 * @property int $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $payload
 * @property string $exception
 * @property \Illuminate\Support\Carbon $failed_at
 * @property-read string $exception_class
 * @property-read string $exception_message
 * @property-read string $exception_fingerprint  Hash первых строк exception
 *                                               для группировки одинаковых fail'ов.
 */
final class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'failed_at' => 'datetime',
    ];

    /**
     * Имя класса исключения (первая строка exception до ': ').
     */
    protected function exceptionClass(): Attribute
    {
        return Attribute::get(function (): string {
            $first = strtok((string) $this->exception, "\n");
            if ($first === false) {
                return 'Exception';
            }
            $colon = strpos($first, ': ');

            return $colon === false ? $first : substr($first, 0, $colon);
        });
    }

    /**
     * Сообщение исключения (без stack trace).
     */
    protected function exceptionMessage(): Attribute
    {
        return Attribute::get(function (): string {
            $first = strtok((string) $this->exception, "\n");
            if ($first === false) {
                return '';
            }
            $colon = strpos($first, ': ');

            return $colon === false ? '' : substr($first, $colon + 2);
        });
    }

    /**
     * Hash от первых N строк exception — для группировки одинаковых fail'ов
     * (rate-limit notifications, find-similar в UI).
     */
    protected function exceptionFingerprint(): Attribute
    {
        return Attribute::get(function (): string {
            $lines = preg_split('/\r?\n/', (string) $this->exception, 6);
            $head = implode("\n", array_slice((array) $lines, 0, 5));

            return substr(hash('xxh64', $head), 0, 16);
        });
    }

    /**
     * Декодированный payload (без выкачивания всего blob'а в свойство).
     *
     * @return array<string, mixed>
     */
    public function decodedPayload(): array
    {
        $decoded = json_decode((string) $this->payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Имя класса самого job'а (из payload['displayName']).
     */
    public function jobName(): string
    {
        $p = $this->decodedPayload();

        return is_string($p['displayName'] ?? null) ? $p['displayName'] : 'Unknown';
    }
}
