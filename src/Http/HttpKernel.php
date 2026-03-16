<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Http;

use LPhenom\Http\MiddlewareStack;
use LPhenom\Http\Request;
use LPhenom\Http\Response;
use LPhenom\Http\Router;

/**
 * HTTP Kernel — dispatches a Request through middleware and router.
 *
 * KPHP-compatible: no reflection, no closures stored in arrays,
 * explicit handler interface.
 */
final class HttpKernel
{
    /** @var Router */
    private Router $router;

    /** @var MiddlewareStack */
    private MiddlewareStack $middleware;

    public function __construct(Router $router, MiddlewareStack $middleware)
    {
        $this->router     = $router;
        $this->middleware  = $middleware;
    }

    /**
     * Handle an HTTP request through middleware pipeline and router.
     */
    public function handle(Request $request): Response
    {
        $routeHandler = new RouteDispatchHandler($this->router);
        return $this->middleware->run($request, $routeHandler);
    }
}
