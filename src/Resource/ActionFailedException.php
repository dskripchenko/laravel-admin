<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Resource;

use RuntimeException;

/**
 * The action did not go through for a good reason — and that is not a server
 * failure.
 *
 * A connection check that could not reach the database, a document that cannot
 * be frozen twice, a client that cannot be activated without a layer: these
 * are all legitimate answers from an action, not breakage. Every exception an
 * action threw used to become a 500 — and a typo in a port number looked, in
 * the monitoring, exactly like the panel falling over.
 *
 * The message thrown here reaches the user as it is, so write it for a person
 * rather than for a log.
 */
class ActionFailedException extends RuntimeException {}
