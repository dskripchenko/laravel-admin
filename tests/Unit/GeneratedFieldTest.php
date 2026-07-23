<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Generated;

it('Generated field serializes type + length/charset/autogenerate attrs', function (): void {
    $f = Generated::make('token')->title('Token')->length(24)->charset('abc')->autogenerate(false);
    $arr = $f->toArray();

    expect($arr['type'])->toBe('generated-field');
    expect($arr['attributes']['length'])->toBe(24);
    expect($arr['attributes']['charset'])->toBe('abc');
    expect($arr['attributes']['autogenerate'])->toBeFalse();
});
