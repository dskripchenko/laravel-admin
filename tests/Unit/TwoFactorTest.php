<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Auth\TwoFactor\Base32;
use Dskripchenko\LaravelAdmin\Auth\TwoFactor\RecoveryCodes;
use Dskripchenko\LaravelAdmin\Auth\TwoFactor\TotpGenerator;

it('Base32: encode/decode is roundtrip', function (): void {
    $original = random_bytes(20);
    $encoded = Base32::encode($original);

    expect($encoded)->toMatch('/^[A-Z2-7]+$/');
    expect(Base32::decode($encoded))->toBe($original);
});

it('Base32: generates a 32-char secret', function (): void {
    $secret = Base32::generateSecret();

    expect($secret)->toHaveLength(32);
    expect($secret)->toMatch('/^[A-Z2-7]{32}$/');
});

it('TotpGenerator: matches RFC 6238 test vector for T=59, secret=12345678901234567890', function (): void {
    // RFC 6238 Appendix B test vector (SHA1, T0=0, X=30s).
    // Secret = "12345678901234567890" (ASCII) → base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ
    $secret = Base32::encode('12345678901234567890');
    $code = TotpGenerator::code($secret, 59);

    // RFC vector: T=0x0000000000000001, TOTP=287082 (8 digits) → 6 digits = 287082
    expect($code)->toBe('287082');
});

it('TotpGenerator: verify accepts current-window code', function (): void {
    $secret = Base32::generateSecret();
    $now = time();
    $code = TotpGenerator::code($secret, $now);

    expect(TotpGenerator::verify($secret, $code, 1, $now))->toBeTrue();
});

it('TotpGenerator: verify rejects invalid code', function (): void {
    $secret = Base32::generateSecret();

    expect(TotpGenerator::verify($secret, '000000'))->toBeFalse();
});

it('TotpGenerator: verify rejects malformed code', function (): void {
    $secret = Base32::generateSecret();

    expect(TotpGenerator::verify($secret, 'abc'))->toBeFalse();
    expect(TotpGenerator::verify($secret, '12345'))->toBeFalse();
});

it('TotpGenerator: verify accepts code from previous period within window', function (): void {
    $secret = Base32::generateSecret();
    $now = time();
    $previousPeriod = $now - 30;
    $oldCode = TotpGenerator::code($secret, $previousPeriod);

    expect(TotpGenerator::verify($secret, $oldCode, 1, $now))->toBeTrue();
});

it('TotpGenerator: provisioningUri returns valid otpauth URL', function (): void {
    $secret = 'JBSWY3DPEHPK3PXP';
    $uri = TotpGenerator::provisioningUri($secret, 'admin@example.com', 'Acme');

    expect($uri)->toStartWith('otpauth://totp/');
    expect($uri)->toContain('secret=JBSWY3DPEHPK3PXP');
    expect($uri)->toContain('issuer=Acme');
});

it('RecoveryCodes: generates the requested number', function (): void {
    expect(RecoveryCodes::generate(3))->toHaveCount(3);
    expect(RecoveryCodes::generate())->toHaveCount(RecoveryCodes::DEFAULT_COUNT);
});

it('RecoveryCodes: verify returns remaining list when matched', function (): void {
    $codes = ['aaaaaa-bbbbbb', 'ccccccc-ddddd', 'eeeeee-ffffff'];
    $remaining = RecoveryCodes::verify($codes, 'ccccccc-ddddd');

    expect($remaining)->toBe(['aaaaaa-bbbbbb', 'eeeeee-ffffff']);
});

it('RecoveryCodes: verify returns null when no match', function (): void {
    $codes = ['aaaaaa-bbbbbb'];
    expect(RecoveryCodes::verify($codes, 'nope'))->toBeNull();
});

it('RecoveryCodes: each generated code is in xxxxxx-xxxxxx format', function (): void {
    $codes = RecoveryCodes::generate(5);

    foreach ($codes as $code) {
        expect($code)->toMatch('/^[a-f0-9]{6}-[a-f0-9]{6}$/');
    }
});
