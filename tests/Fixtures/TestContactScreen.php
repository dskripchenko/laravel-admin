<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;

/**
 * Тестовый Screen — кастомная форма обратной связи.
 *
 * Демонстрирует: query/state, layout с Rows+Input/Textarea, commandBar c
 * Button::method('send'), command-метод send($state) с валидацией и ответом.
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
}
