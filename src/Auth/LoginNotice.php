<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

/**
 * A message for the login page — why an external sign-in did not let the
 * person in ("no access: ask your administrator"), shown above the form.
 *
 * Kept in the session and taken by the next bootstrap, whichever request
 * builds it: the redirected page in the `inline` strategy, the bootstrap call
 * in `xhr`. A flash would not survive the second: the page request ages it,
 * and the bootstrap call after it finds nothing.
 */
final class LoginNotice
{
    public const SESSION_KEY = 'admin.login_notice';

    public const TONES = ['danger', 'warning', 'info', 'success'];

    public static function put(string $message, string $tone = 'danger'): void
    {
        session()->put(self::SESSION_KEY, [
            'message' => $message,
            'tone' => in_array($tone, self::TONES, true) ? $tone : 'danger',
        ]);
    }

    /**
     * The message, once: taking it removes it.
     *
     * @return array{message: string, tone: string}|null
     */
    public static function take(): ?array
    {
        if (! app()->bound('session') || ! request()->hasSession()) {
            return null;
        }
        $notice = session()->pull(self::SESSION_KEY);

        return is_array($notice) && is_string($notice['message'] ?? null) && $notice['message'] !== ''
            ? ['message' => $notice['message'], 'tone' => (string) ($notice['tone'] ?? 'danger')]
            : null;
    }
}
