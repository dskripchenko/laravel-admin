<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;

it('labels a field from its name when no title is given', function (): void {
    expect(Input::make('opens_at')->toArray()['label'])->toBe('Opens At')
        ->and(Input::make('contact.person')->toArray()['label'])->toBe('Contact Person');
});

it('keeps an explicit title, including an empty one', function (): void {
    expect(Input::make('opens_at')->title('Opening time')->toArray()['label'])->toBe('Opening time')
        ->and(Input::make('opens_at')->title('')->toArray()['label'])->toBe('');
});
