<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;

/**
 * A test screen — a custom feedback form.
 *
 * It demonstrates: query/state, a layout with Rows plus Input/Textarea, a
 * commandBar with Button::method('send'), and the command method send($state)
 * with validation and a response.
 *
 * @internal
 */
final class TestContactScreen extends Screen
{
    /** @var array<int, array<string, mixed>> */
    public static array $sent = [];

    public function name(): string
    {
        return 'Contact';
    }

    public function description(): ?string
    {
        return 'Тестовая форма';
    }

    public function permission(): array|string|null
    {
        return null;
    }

    public function query(mixed ...$params): Repository|array
    {
        return [
            'email' => '',
            'message' => '',
        ];
    }

    public function layout(): array
    {
        return [
            Rows::make([
                Input::make('email')->required(),
                Textarea::make('message')->required(),
            ]),
        ];
    }

    public function commandBar(): array
    {
        return [
            Button::make('Отправить')->method('send')->primary(),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function send(array $state): array
    {
        validator($state, [
            'email' => 'required|email',
            'message' => 'required|string|min:3',
        ])->validate();

        self::$sent[] = $state;

        return [
            'message' => 'Письмо отправлено',
            'state' => ['email' => '', 'message' => ''],
            'alerts' => [['type' => 'success', 'message' => 'OK']],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function sendWithLink(array $state): array
    {
        return [
            'message' => 'Задача поставлена',
            'message_link' => ['url' => '/r/jobs/7', 'label' => 'Открыть задание'],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function sendWithBrokenLink(array $state): array
    {
        return [
            'message' => 'Задача поставлена',
            'message_link' => ['url' => '/r/jobs/7'],
        ];
    }

    /**
     * The message_link shapes besides the canonical one, picked by `shape`.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function sendWithLinkShape(array $state): array
    {
        $links = [
            'string' => '/r/jobs/7',
            'href' => ['href' => '/r/jobs/7', 'text' => 'Открыть задание'],
            'pair' => ['/r/jobs/7', 'Открыть задание'],
            'no_url' => ['label' => 'Открыть задание'],
        ];

        return [
            'message' => 'Задача поставлена',
            'message_link' => $links[(string) ($state['shape'] ?? '')] ?? null,
        ];
    }

    /**
     * Refuses on the merits.
     *
     * @param  array<string, mixed>  $state
     */
    public function refuse(array $state): never
    {
        throw new Dskripchenko\LaravelAdmin\Resource\ActionFailedException('SMTP-сервер недоступен');
    }

    /**
     * Answers with a toast only — no message, so no banner.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function notifyOnly(array $state): array
    {
        return [
            'alerts' => [['type' => 'success', 'message' => 'Saved']],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function levelAlert(array $state): array
    {
        return [
            'alerts' => [
                ['level' => 'success', 'message' => 'Done'],
                ['message' => 'Plain'],
            ],
        ];
    }
}
