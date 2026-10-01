<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Tests\Support\BackendTypes;

it('keeps the frontend parity fixture in step with the backend types', function () {
    expect(file_get_contents(BackendTypes::FIXTURE))->toBe(
        BackendTypes::json(),
        'A field, layout, widget, entry or chart type changed. Run `composer types:export` '
        .'and give the new type a component in the SPA (see backendParity.test.ts).',
    );
});
