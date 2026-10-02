<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(Dskripchenko\LaravelAdmin\Tests\PanelsTestCase::class)->in('Panels');
uses(Dskripchenko\LaravelAdmin\Tests\HostModuleTestCase::class)->in('Host');
uses(Dskripchenko\LaravelAdmin\Tests\SharedStrategyTestCase::class)->in('Shared');
uses(Dskripchenko\LaravelAdmin\Tests\RootPanelTestCase::class)->in('RootPanel');
