<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Http;

use LPhenom\Http\HandlerInterface;
use LPhenom\Http\Request;
use LPhenom\Http\Response;
use LPhenom\Http\Router;

/**
 * Internal handler that dispatches a Request to the matched route.
 *
 * Used as the final handler in the middleware pipeline.
 *
 * KPHP-compatible: implements HandlerInterface explicitly.
 */
final class RouteDispatchHandler implements HandlerInterface
{
    /** @var Router */
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function handle(Request $request): Response
    {
        $match = $this->router->match($request->getMethod(), $request->getPath());

        if ($match === null) {
            return new Response(404, [], 'Not Found');
        }

        return $match->handler->handle($request);
    }
}
