<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use InvalidArgumentException;

/**
 * Embed-виджет — внешний iframe (Grafana panel, статус-страница и т.д.).
 *
 * Безопасность: src должен пройти `allowedHosts` — fnmatch-проверку, чтобы
 * SPA не пускал произвольные URL'ы (XSS via clickjacking). Если allowedHosts
 * пуст — пропускает любые URL'ы (decision реализатора).
 */
final class IframeWidget extends Widget
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
     * @param  list<string>  $hosts  fnmatch-паттерны (`grafana.*.example.com`).
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
