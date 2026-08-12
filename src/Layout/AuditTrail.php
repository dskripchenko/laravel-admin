<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

/**
 * The layout showing an audit timeline on a view screen.
 *
 * Its configuration tells the SPA which subject to load — the subject_type and
 * the subject_id from the state — and which endpoint to call. The loading
 * itself goes through the `audit.timeline` action.
 *
 * Usage:
 *
 *     AuditTrail::for(\App\Models\User::class)->limit(50)
 */
final class AuditTrail extends Layout
{
    public static function for(string $subjectType): self
    {
        $instance = new self;
        $instance->props['subjectType'] = $subjectType;

        return $instance;
    }

    public function type(): string
    {
        return 'audit_trail';
    }

    /**
     * The key of the state holding the record's `id`; 'id' by default.
     */
    public function fromState(string $key): self
    {
        $this->props['idStateKey'] = $key;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->props['limit'] = $limit;

        return $this;
    }

    /**
     * The permission needed to see it: without it the component is hidden
     * entirely. null, the default, means everyone authenticated may see it.
     */
    public function withPermission(string $permission): self
    {
        $this->props['permission'] = $permission;

        return $this;
    }
}
