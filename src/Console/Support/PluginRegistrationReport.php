<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console\Support;

use Illuminate\Console\Command;

/**
 * Prints what AdminPluginUpdater::ensurePluginRegistered() did, the same way
 * for every generator.
 */
final class PluginRegistrationReport
{
    /**
     * @param  array{status: 'listed'|'registered'|'manual', class: string, config: string, instructions: ?string}  $result
     */
    public static function print(Command $command, array $result): void
    {
        match ($result['status']) {
            'registered' => $command->line("  Plugin {$result['class']} added to 'plugins' in {$result['config']}."),
            'listed' => null,
            'manual' => $command->warn('  The plugin is not loaded yet. '.$result['instructions']),
        };
    }
}
