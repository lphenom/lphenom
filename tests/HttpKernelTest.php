<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Tests;

use LPhenom\Http\HandlerInterface;
use LPhenom\Http\MiddlewareStack;
use LPhenom\Http\Request;
use LPhenom\Http\Response;
use LPhenom\Http\Router;
use LPhenom\LPhenom\Http\HttpKernel;
use PHPUnit\Framework\TestCase;

final class HttpKernelTest extends TestCase
{
    public function testHandleRouteMatch(): void
    {
        $router = new Router();
        $router->get('/hello', new class () implements HandlerInterface {
            public function handle(Request $request): Response
            {
                return Response::text('Hello, World!');
            }
        });

        $kernel  = new HttpKernel($router, new MiddlewareStack());
        $request = new Request('GET', '/hello', [], [], [], '', [], '127.0.0.1');

        $response = $kernel->handle($request);

        self::assertSame(200, $response->getStatus());
        self::assertSame('Hello, World!', $response->getBody());
    }

    public function testHandleRouteNotFound(): void
    {
        $router  = new Router();
        $kernel  = new HttpKernel($router, new MiddlewareStack());
        $request = new Request('GET', '/missing', [], [], [], '', [], '127.0.0.1');

        $response = $kernel->handle($request);

        self::assertSame(404, $response->getStatus());
        self::assertSame('Not Found', $response->getBody());
    }
}
