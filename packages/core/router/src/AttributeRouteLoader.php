<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router;

/**
 * Walks controller class-strings, reflects on classes/methods,
 * reads #[RouteAttribute] attributes, and produces a RouteCollection.
 *
 * Boot-time only — never touches the hot path (Router::match()).
 */
final class AttributeRouteLoader
{
    /**
     * Load routes from a list of controller class-strings.
     *
     * @param list<class-string> $controllers
     */
    public function load(array $controllers): RouteCollection
    {
        $collection = new RouteCollection();

        foreach ($controllers as $class) {
            if (!\class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(RouteAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);

                foreach ($attributes as $attribute) {
                    /** @var RouteAttribute $routeAttr */
                    $routeAttr = $attribute->newInstance();

                    $collection->add(new Route(
                        path: $routeAttr->path,
                        methods: $routeAttr->methods,
                        name: $routeAttr->name,
                        controllerClass: $class,
                        controllerMethod: $method->getName(),
                        middleware: $routeAttr->middleware,
                        constraints: $routeAttr->constraints,
                    ));
                }
            }
        }

        return $collection;
    }
}
