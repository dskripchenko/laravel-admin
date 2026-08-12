<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Settings\Storage;

use Illuminate\Support\Facades\DB;

/**
 * A general key-value store over the admin_settings table.
 *
 * It fits most cases: the SMTP settings, the branding, feature flags, any
 * arbitrary value. For a great many settings, or for a strictly typed schema,
 * write your own SettingsStorage over an Eloquent model with typed columns.
 */
final class KeyValueSettingsStorage implements SettingsStorage
{
    /**
     * @return array<string, mixed>
     */
    public function all(string $group): array
    {
        $rows = DB::table('admin_settings')
            ->where('group', $group)
            ->whereNull('owner_type')
            ->whereNull('owner_id')
            ->get(['key', 'value']);

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->key] = $this->decode($row->value);
        }

        return $result;
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $row = DB::table('admin_settings')
            ->where('group', $group)
            ->where('key', $key)
            ->whereNull('owner_type')
            ->whereNull('owner_id')
            ->first(['value']);

        return $row === null ? $default : $this->decode($row->value);
    }

    public function save(string $group, array $values): void
    {
        DB::transaction(function () use ($group, $values): void {
            foreach ($values as $key => $value) {
                DB::table('admin_settings')->updateOrInsert(
                    [
                        'group' => $group,
                        'key' => (string) $key,
                        'owner_type' => null,
                        'owner_id' => null,
                    ],
                    [
                        'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        });
    }

    public function replace(string $group, array $values): void
    {
        DB::transaction(function () use ($group, $values): void {
            DB::table('admin_settings')
                ->where('group', $group)
                ->whereNull('owner_type')
                ->whereNull('owner_id')
                ->delete();
            $this->save($group, $values);
        });
    }

    public function forget(string $group, string $key): void
    {
        DB::table('admin_settings')
            ->where('group', $group)
            ->where('key', $key)
            ->whereNull('owner_type')
            ->whereNull('owner_id')
            ->delete();
    }

    private function decode(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            return $value;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $value;
        }
    }
}
