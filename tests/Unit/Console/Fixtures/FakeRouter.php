<?php

declare(strict_types=1);

/*
 * This file is part of the UhifadhiLabs Devkit Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Devkit\Tests\Unit\Console\Fixtures;

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;

/**
 * A router the registry unit tests fill with stamped routes — so "how many routes
 * does this module declare" can be asserted without a real routing graph.
 */
final class FakeRouter implements RouterInterface
{
    private RouteCollection $routes;
    private RequestContext $context;

    /**
     * @param array<string, int> $stampedRoutesBySlug slug => how many stamped routes to add for it
     */
    public function __construct(array $stampedRoutesBySlug = [])
    {
        $this->routes = new RouteCollection();
        $this->context = new RequestContext();

        foreach ($stampedRoutesBySlug as $slug => $count) {
            for ($i = 0; $i < $count; ++$i) {
                $this->routes->add(
                    \sprintf('%s_%d', $slug, $i),
                    new Route('/'.$slug.'/'.$i, [RegistryBundle::MODULE_ROUTE_DEFAULT => $slug]),
                );
            }
        }
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return '/'.$name;
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function match(string $pathinfo): array
    {
        return [];
    }
}
