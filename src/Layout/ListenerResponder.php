<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shared body of the `listener` actions of screens and resources.
 *
 * It reads `{listener, state}` from the request, finds the listener with that
 * id in the layout tree the caller built, and answers with the listener's
 * re-rendered children and its handler's state patch. The authorization is the
 * caller's: the route's middleware and whatever the controller checks first.
 */
final class ListenerResponder
{
    /**
     * @param  iterable<mixed>  $layouts  The owner's layout tree.
     */
    public static function respond(ApiController $controller, object $owner, iterable $layouts, Request $request): JsonResponse
    {
        $id = $request->input('listener');
        if (! is_string($id) || $id === '') {
            return $controller->error([
                'errorKey' => 'validation',
                'message' => '`listener` is required',
                'messages' => ['listener' => ['`listener` is required']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $state = $request->input('state', []);
        if (! is_array($state)) {
            return $controller->error([
                'errorKey' => 'validation',
                'message' => '`state` must be an object',
                'messages' => ['state' => ['`state` must be an object']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $listener = Listener::find($layouts, $id);
        if ($listener === null) {
            return $controller->error([
                'errorKey' => 'listener_not_found',
                'message' => "Listener `{$id}` is not declared here",
            ], Response::HTTP_NOT_FOUND);
        }

        if (! $listener->handlerIsCallable($owner)) {
            return $controller->error([
                'errorKey' => 'listener_handler_not_callable',
                'message' => "The handler of listener `{$id}` is not a public method of ".$owner::class,
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $state */
        $result = $listener->respond($owner, $state, $request);

        return $controller->success([
            'listener' => $result['listener'],
            // An object even when empty, so the SPA always merges a map.
            'state' => (object) $result['state'],
            'layouts' => $result['layouts'],
        ]);
    }
}
