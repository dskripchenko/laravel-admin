<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;

/**
 * A process screen for the Layout::flow tests: polling, toggling, a
 * referenced sub-process, a drawer action, and a flow the user cannot see.
 *
 * @internal
 */
final class TestFlowScreen extends Screen
{
    /** @var list<array{0: string, 1: mixed}> */
    public static array $calls = [];

    public static bool $hideSecret = true;

    public function name(): string
    {
        return 'Flow';
    }

    public function query(mixed ...$params): Repository|array
    {
        return [
            'order_id' => 42,
            'process' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start', 'type' => 'start'],
                    ['id' => 'paid', 'type' => 'state', 'label' => 'Paid', 'subgraph' => 'payment'],
                    ['id' => 'end', 'type' => 'end'],
                ],
                'edges' => [
                    ['id' => 'e1', 'from' => 'start', 'to' => 'paid'],
                    ['id' => 'e2', 'from' => 'paid', 'to' => 'end'],
                ],
            ],
        ];
    }

    public function layout(): array
    {
        return [
            Layout::flow('process')
                ->poll('processState', 2)
                ->onToggle('toggleStep', permission: 'admin.flow.manage')
                ->subgraphs('subprocess')
                ->instanceFrom('order_id')
                ->actions([
                    Button::make('Retry')->method('retryStep')->permission('admin.flow.manage'),
                ]),
            Layout::flow(['nodes' => [['id' => 'a']]])
                ->poll('secretState')
                ->canSee(fn (): bool => ! self::$hideSecret),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function processState(array $context): array
    {
        self::$calls[] = ['processState', $context];

        return [
            'nodes' => ['paid' => ['status' => 'active', 'count' => 1]],
            'edges' => [],
            'path' => ['start', 'paid'],
            'current' => ['paid'],
            'updatedAt' => '2026-10-25T10:00:00Z',
        ];
    }

    /**
     * @param  array<string, mixed>  $change
     */
    public function toggleStep(array $change): void
    {
        self::$calls[] = ['toggleStep', $change];
        if (($change['id'] ?? null) === 'start') {
            throw new ActionFailedException('The start cannot be switched off');
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function subprocess(string $ref, array $node): array
    {
        self::$calls[] = ['subprocess', [$ref, $node['id'] ?? null]];

        return [
            'version' => 1,
            'nodes' => [['id' => 'authorize', 'type' => 'task'], ['id' => 'capture', 'type' => 'task']],
            'edges' => [['id' => 's1', 'from' => 'authorize', 'to' => 'capture']],
        ];
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    public function retryStep(array $selection): array
    {
        self::$calls[] = ['retryStep', $selection];

        return ['message' => 'Retried'];
    }

    /**
     * @return array<string, mixed>
     */
    public function secretState(array $context = []): array
    {
        self::$calls[] = ['secretState', $context];

        return ['nodes' => []];
    }
}
