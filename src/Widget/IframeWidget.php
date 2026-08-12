<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use InvalidArgumentException;

/**
 * The embed widget — an external iframe: a Grafana panel, a status page and so
 * on.
 *
 * On safety: the src must pass `allowedHosts`, an fnmatch check, so that the
 * SPA does not admit arbitrary URLs — that is XSS by way of clickjacking. An
 * empty allowedHosts lets any URL through, which is the implementer's
 * decision.
 */
class IframeWidget extends Widget
{
    private string $src = '';

    /** @var list<string> */
    private array $allowedHosts = [];

    private ?int $height = null;

    private string $sandbox = 'allow-scripts allow-same-origin';

    public function widgetType(): string
    {
        return 'iframe';
    }

    public function src(string $src): static
    {
        if ($this->allowedHosts !== [] && ! $this->matchesAllowedHosts($src)) {
            throw new InvalidArgumentException("URL `{$src}` is not in allowedHosts");
        }
        $this->src = $src;

        return $this;
    }

    /**
     * @param  list<string>  $hosts  fnmatch patterns, `grafana.*.example.com` for one.
     */
    public function allowedHosts(array $hosts): static
    {
        $this->allowedHosts = $hosts;

        return $this;
    }

    public function height(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function sandbox(string $sandbox): static
    {
        $this->sandbox = $sandbox;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'src' => $this->src,
            'height' => $this->height,
            'sandbox' => $this->sandbox,
        ];
    }

    private function matchesAllowedHosts(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host)) {
            return false;
        }
        foreach ($this->allowedHosts as $pattern) {
            if (fnmatch($pattern, $host)) {
                return true;
            }
        }

        return false;
    }
}
