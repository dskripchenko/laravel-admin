<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Audit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry of the audit log.
 *
 * The shape of `changes` follows the event:
 *   - created → `{after: {...}}`
 *   - updated → `{before: {...}, after: {...}}`, the changed fields alone
 *   - deleted/forceDeleted → `{before: {...}}`
 *   - login/logout → `{guard: 'admin'}`, the auth payload
 *
 * @property int $id
 * @property string|null $actor_type
 * @property int|string|null $actor_id Stored as a string (see MorphKey): an integer key reads back as an int.
 * @property string|null $impersonator_type The administrator signed in as the actor, during an impersonation.
 * @property int|string|null $impersonator_id
 * @property string|null $subject_type
 * @property int|string|null $subject_id An integer, a uuid or any other key — see MorphKey.
 * @property string $event
 * @property array<string, mixed>|null $changes
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $url
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class AuditLog extends Model
{
    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'impersonator_type',
        'impersonator_id',
        'subject_type',
        'subject_id',
        'event',
        'changes',
        'ip',
        'user_agent',
        'url',
    ];

    /**
     * The human-readable type labels. They travel with every serialization,
     * the list and the timeline alike, so that the UI never shows an FQCN. The
     * values come from getActorLabelAttribute and its kin.
     *
     * @var list<string>
     */
    protected $appends = ['actor_label', 'subject_label'];

    /** Whether the table has the impersonator columns; memoized once true. */
    private static ?bool $hasImpersonatorColumns = null;

    /**
     * An entry written while an administrator is signed in as someone else
     * names that administrator too, whoever wrote it — the panel's own
     * listeners or a host's code. Until the migration adding the columns has
     * run, nothing is added.
     */
    protected static function booted(): void
    {
        self::creating(static function (self $log): void {
            if ($log->getAttribute('impersonator_id') !== null) {
                return;
            }
            $impersonator = \Dskripchenko\LaravelAdmin\Impersonation\ImpersonationManager::currentImpersonator();
            if ($impersonator !== null && self::supportsImpersonator()) {
                $log->setAttribute('impersonator_type', $impersonator['type']);
                $log->setAttribute('impersonator_id', $impersonator['id']);
            }
        });
    }

    /**
     * Whether the impersonator columns exist (the 2026_11_14 migration).
     */
    public static function supportsImpersonator(): bool
    {
        // Only a positive answer is kept: a worker started before the
        // migration picks the columns up once they exist.
        if (self::$hasImpersonatorColumns === true) {
            return true;
        }
        try {
            $model = new self;
            $has = \Illuminate\Support\Facades\Schema::connection($model->getConnectionName())
                ->hasColumn($model->getTable(), 'impersonator_id');
        } catch (\Throwable) {
            return false;
        }
        if ($has) {
            self::$hasImpersonatorColumns = true;
        }

        return $has;
    }

    /** Forgets the memoized schema check — for tests and migrations. */
    public static function flushSchemaCache(): void
    {
        self::$hasImpersonatorColumns = null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_id' => MorphKey::class,
            'impersonator_id' => MorphKey::class,
            'subject_id' => MorphKey::class,
            'changes' => 'array',
        ];
    }

    /**
     * Resolves a morph type's FQCN into a human-readable label.
     *
     * In order: config('admin.audit.type_labels')[$fqcn] → the reverse
     * morph-map alias, when the host called Relation::enforceMorphMap →
     * class_basename. An empty or null type — a login with no subject, say —
     * gives null.
     */
    public static function resolveTypeLabel(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $map = (array) config('admin.audit.type_labels', []);
        $label = $map[$type] ?? null;
        if (is_string($label)) {
            return $label;
        }

        $alias = array_search($type, \Illuminate\Database\Eloquent\Relations\Relation::morphMap(), true);
        if (is_string($alias)) {
            return $alias;
        }

        return class_basename($type);
    }

    public function getActorLabelAttribute(): ?string
    {
        return self::resolveTypeLabel($this->actor_type);
    }

    public function getSubjectLabelAttribute(): ?string
    {
        return self::resolveTypeLabel($this->subject_type);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The administrator who was signed in as the actor; null outside an
     * impersonation.
     *
     * @return MorphTo<Model, $this>
     */
    public function impersonator(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The scope of one particular subject — a model and an id.
     *
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', MorphKey::toString($subject->getKey()));
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeForActor(Builder $query, Model $actor): Builder
    {
        return $query
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', MorphKey::toString($actor->getKey()));
    }

    /**
     * Where an audited change came from: the client's IP, its User-Agent and
     * the URL, cut to the configured lengths. Query parameters that carry a
     * credential — an OAuth code or token, a signed link's signature — are
     * masked (`admin.audit.redacted_query`): the audit log outlives them and
     * is read by more people than they were meant for.
     *
     * @return array{ip: string|null, user_agent: string, url: string}
     */
    public static function requestContext(): array
    {
        $request = request();

        return [
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, (int) config('admin.audit.user_agent_max_length', 1024)),
            'url' => substr(self::redactUrl($request->fullUrl()), 0, (int) config('admin.audit.url_max_length', 2048)),
        ];
    }

    /**
     * Masked unless `admin.audit.redacted_query` says otherwise. The default
     * lives here, not only in the config file: a host that published
     * config/admin.php before the key existed has no such key, and without a
     * default nothing would be masked at all.
     */
    public const DEFAULT_REDACTED_QUERY = ['code', 'state', 'session_state', 'token', 'access_token', 'id_token', 'refresh_token', 'password', 'signature', 'secret', 'key'];

    /** The URL with the credential-carrying query parameters masked. */
    public static function redactUrl(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return $url;
        }
        $redacted = array_map('strtolower', (array) config('admin.audit.redacted_query', self::DEFAULT_REDACTED_QUERY));
        $parts = [];
        foreach (explode('&', $query) as $pair) {
            [$name] = explode('=', $pair, 2);
            $parts[] = in_array(strtolower(urldecode($name)), $redacted, true) ? $name.'=[redacted]' : $pair;
        }

        return str_replace('?'.$query, '?'.implode('&', $parts), $url);
    }
}
