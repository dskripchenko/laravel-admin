<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Services;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Сервис, инкапсулирующий операции над failed-jobs и batches.
 *
 * Использует Laravel Artisan-команды для retry (там сложная логика
 * unserialize + dispatch), и DB-уровневые операции для forget — это
 * единственная гарантия не сломать invariants.
 */
final class JobOperations
{
    /**
     * Re-enqueue одного failed-job по UUID. Internally вызывает
     * `queue:retry {uuid}`, который соберёт payload и запустит снова.
     */
    public function retryFailedJob(string $uuid): bool
    {
        $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);

        return $exitCode === 0;
    }

    /**
     * Bulk-retry. Возвращает кол-во успешно re-enqueued jobs.
     *
     * @param  list<string>  $uuids
     */
    public function retryFailedJobs(array $uuids): int
    {
        if ($uuids === []) {
            return 0;
        }
        $exitCode = Artisan::call('queue:retry', ['id' => $uuids]);

        return $exitCode === 0 ? count($uuids) : 0;
    }

    /**
     * Удалить запись из failed_jobs (но не re-enqueue).
     */
    public function forgetFailedJob(string $uuid): bool
    {
        $deleted = DB::table('failed_jobs')->where('uuid', $uuid)->delete();

        return $deleted > 0;
    }

    /**
     * @param  list<string>  $uuids
     */
    public function forgetFailedJobs(array $uuids): int
    {
        if ($uuids === []) {
            return 0;
        }

        return DB::table('failed_jobs')->whereIn('uuid', $uuids)->delete();
    }

    /**
     * Cancel batch (через Bus::findBatch + cancel). Pending-jobs
     * останутся в очереди, но при выполнении проверят `$batch->cancelled()`
     * и не будут run'нуть logic.
     */
    public function cancelBatch(string $batchId): bool
    {
        $batch = Bus::findBatch($batchId);
        if (! $batch instanceof Batch) {
            return false;
        }

        $batch->cancel();

        return true;
    }

    /**
     * Re-enqueue все failed-jobs из batch'а через `queue:retry-batch {id}`.
     */
    public function retryBatchFailures(string $batchId): bool
    {
        $exitCode = Artisan::call('queue:retry-batch', ['id' => $batchId]);

        return $exitCode === 0;
    }
}
