# Core View

Application-facing template rendering with a minimal driver contract.

## Configuration

```php
return [
    'view' => [
        'driver' => 'twig',
    ],
];
```

Supported drivers:
- `twig`: functional Twig rendering.
- `neospx`: integration point only; not implemented yet.

## Usage

```php
use SovereignStack\Core\View\ViewFactory;

$view = ViewFactory::fromConfig($config, '/path/to/templates');
echo $view->render('template.twig', ['name' => 'Ada']);
```

Twig uses HTML autoescaping by default. Template resolution is constrained to the
configured template root by Twig's filesystem loader.
