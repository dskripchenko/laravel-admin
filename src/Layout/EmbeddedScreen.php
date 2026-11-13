<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Screen\ScreenAccess;
use InvalidArgumentException;

/**
 * Another screen shown in place — most often in a tab:
 *
 *   Layout::tabs([
 *       'Сайт' => [...$this->siteFields()],
 *       'Капча' => Layout::screen(CaptchaSettingsScreen::class),
 *   ])->persistInQuery('tab')->keys(['site', 'captcha']);
 *
 * The embedded screen stays a screen of its own: the panel loads it through
 * its own `/{slug}/state` with `$params` as its query, its command bar and
 * methods go to its own endpoints, and its permissions apply — a user who
 * may not open it does not get the tab. It must be registered
 * (`$admin->screens([...])`); hide it from the menu with
 * `menu()->hideAuto($slug)` when it is only meant to be seen here.
 *
 * `->title(false)` hides its name above it; the description stays.
 */
final class EmbeddedScreen extends Layout
{
    /** @var class-string<Screen> */
    private string $screenClass;

    /**
     * @param  class-string<Screen>  $screenClass
     * @param  array<string, scalar>  $params
     */
    public static function for(string $screenClass, array $params = []): self
    {
        if (! is_subclass_of($screenClass, Screen::class)) {
            throw new InvalidArgumentException('Layout::screen() expects a subclass of '.Screen::class.", got {$screenClass}");
        }
        $instance = new self;
        $instance->screenClass = $screenClass;
        $instance->props['params'] = array_map(static fn (mixed $v): string => is_bool($v) ? ($v ? '1' : '0') : (string) $v, $params);

        return $instance;
    }

    /** Whether to show the screen's name above it (shown by default). */
    public function title(bool $show = true): self
    {
        $this->props['showTitle'] = $show;

        return $this;
    }

    public function type(): string
    {
        return 'admin.screen';
    }

    public function isVisible(): bool
    {
        if (! parent::isVisible()) {
            return false;
        }
        try {
            $screen = app($this->screenClass);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return $screen instanceof Screen && ScreenAccess::allows($screen);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->props['screen'] = $this->screenClass::slug();

        return parent::toArray();
    }
}
