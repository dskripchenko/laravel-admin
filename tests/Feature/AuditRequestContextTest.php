<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Audit\AuditLog;
use Illuminate\Support\Facades\Schema;

/*
 * The request context of audit rows: credentials in the query string are
 * masked, and the columns hold what the configured lengths let through.
 */

it('masks the credential-carrying query parameters of the audited URL', function (): void {
    $url = 'https://app.test/admin/sso/callback?code=abc.def&state=xyz&iss=https%3A%2F%2Fidp.test&session_state=s1&page=2';

    expect(AuditLog::redactUrl($url))
        ->toBe('https://app.test/admin/sso/callback?code=[redacted]&state=[redacted]&iss=https%3A%2F%2Fidp.test&session_state=[redacted]&page=2')
        ->and(AuditLog::redactUrl('https://app.test/admin/clients'))->toBe('https://app.test/admin/clients')
        ->and(AuditLog::redactUrl('https://app.test/rendered/1/download?expires=1&Signature=abc'))->toBe('https://app.test/rendered/1/download?expires=1&Signature=[redacted]');
});

it('masks by default when a published config has no redacted_query key', function (): void {
    $audit = config('admin.audit');
    unset($audit['redacted_query']);
    config(['admin.audit' => $audit]);

    expect(AuditLog::redactUrl('https://app.test/cb?code=abc&page=1'))->toBe('https://app.test/cb?code=[redacted]&page=1');
});

it('builds the context from the request, cut to the configured lengths', function (): void {
    config(['admin.audit.url_max_length' => 40]);
    $this->get('/admin/login?code=secret-code-value&x=1');

    $context = AuditLog::requestContext();
    expect($context['url'])->not->toContain('secret-code-value')
        ->and(strlen($context['url']))->toBeLessThanOrEqual(40)
        ->and($context)->toHaveKeys(['ip', 'user_agent', 'url']);
});

it('keeps the URL and the User-Agent in text columns, not varchar(255)', function (): void {
    expect(Schema::getColumnType('admin_audit_logs', 'url'))->toBe('text')
        ->and(Schema::getColumnType('admin_audit_logs', 'user_agent'))->toBe('text');
});
