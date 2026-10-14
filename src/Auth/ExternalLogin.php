<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

/**
 * One way to sign in other than the password form — "Sign in with SSO", a
 * company's identity provider and the like. The login page shows it as a
 * button under the form; the button leads to `url`, a full page navigation,
 * where the package that registered it takes over.
 *
 * The core knows nothing of the protocol behind it: OpenID Connect, SAML or
 * anything else lives in the package (laravel-admin-oidc, for instance).
 */
final readonly class ExternalLogin
{
    public function __construct(
        public string $label,
        public string $url,
        /** An icon name from the panel's icon set (the menu's), or null. */
        public ?string $icon = null,
        /** A line under the label: whose accounts it is for, say. */
        public ?string $description = null,
    ) {}

    /** @return array{label: string, url: string, icon: string|null, description: string|null} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'url' => $this->url, 'icon' => $this->icon, 'description' => $this->description];
    }
}
