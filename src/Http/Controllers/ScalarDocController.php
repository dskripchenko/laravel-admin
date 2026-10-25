<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Controllers;

/*
 * The controller's name before 1.56, when the page was Scalar. Kept so a host
 * that routes to it keeps working; autoloading this name loads the alias.
 *
 * @deprecated use ApiReferenceController
 */
class_alias(ApiReferenceController::class, 'Dskripchenko\LaravelAdmin\Http\Controllers\ScalarDocController');
