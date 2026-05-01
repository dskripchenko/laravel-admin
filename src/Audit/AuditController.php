<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Audit;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Чтение audit-лога: список + timeline по конкретному subject.
 */
final class AuditController extends ApiController
{
    /**
     * Список последних событий с фильтрами.
     *
     * @input string ?$subject_type
     * @input integer ?$subject_id
     * @input string ?$actor_type
     * @input integer ?$actor_id
     * @input string ?$event
     * @input string ?$from
     * @input string ?$to
     * @input integer ?$per_page
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {AuditListResponse}
     */
    public function list(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        if ($subjectType = $request->input('subject_type')) {
            $query->where('subject_type', $subjectType);
        }
        if ($subjectId = $request->input('subject_id')) {
            $query->where('subject_id', $subjectId);
        }
        if ($actorType = $request->input('actor_type')) {
            $query->where('actor_type', $actorType);
        }
        if ($actorId = $request->input('actor_id')) {
            $query->where('actor_id', $actorId);
        }
        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }
        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', $to);
        }

        $perPage = max(1, min(
            (int) $request->input('per_page', (int) config('admin.pagination.default_per_page', 25)),
            (int) config('admin.pagination.max_per_page', 100),
        ));
        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->success([
            'data' => AuditTimelineProjector::project($paginator->getCollection()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Полный timeline по конкретному subject (model + id).
     *
     * @input string $subject_type
     * @input integer $subject_id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {AuditTimelineResponse}
     */
    public function timeline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', 'string'],
            'subject_id' => ['required'],
        ]);

        $logs = AuditLog::query()
            ->where('subject_type', $data['subject_type'])
            ->where('subject_id', $data['subject_id'])
            ->with('actor')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return $this->success([
            'data' => AuditTimelineProjector::project($logs),
        ]);
    }
}
