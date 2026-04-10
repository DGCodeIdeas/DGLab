# Lit Web Components Integration

## Overview
DGLab integrates [Lit](https://lit.dev) as a core frontend vendor library using a completely Node-free, offline-first approach. This allows for building high-performance, reactive web components that work seamlessly with SuperPHP and the Superpowers SPA engine.

## Version and Distribution
- **Version**: Lit 3.2.0
- **Distribution**: ES Modules (ESM)
- **Location**: `public/vendor/lit/`
- **Management**: Managed via `cli/fetch-lit.php` which recursively downloads Lit and its dependencies from Unpkg.

## Module Resolution
Bare module specifiers (e.g., `import {html} from 'lit'`) are handled using a native **HTML Import Map**.

- **Configuration**: `config/vendor_map.php`
- **Directive**: `@importmap` (placed in the `<head>` of `shell.super.php`)
- **Generator**: `DGLab\Services\AssetPacker\ImportMapGenerator`

### Example Import Map
```json
{
  "imports": {
    "lit": "/vendor/lit/index.js",
    "lit-html": "/vendor/lit/lit-html.js",
    "@lit/reactive-element": "/vendor/lit/reactive-element.js"
  }
}
```

## Usage in SuperPHP
You can use Lit components directly in any `.super.php` view.

```html
<my-lit-component greeting="Hello from SuperPHP!"></my-lit-component>

<script type="module">
  import { LitElement, html, css } from 'lit';

  class MyLitComponent extends LitElement {
    static properties = {
      greeting: { type: String }
    };

    render() {
      return html`<p>${this.greeting}</p>`;
    }
  }
  customElements.define('my-lit-component', MyLitComponent);
</script>
```

## Available Directives
The following Lit directives are pre-configured in the vendor map:
- `lit/directives/until`
- `lit/directives/repeat`
- `lit/directives/if-defined`
- `lit/directives/class-map`
- `lit/directives/style-map`

## Maintenance
To update Lit or add new dependencies:
1. Modify `cli/fetch-lit.php` if necessary.
2. Run `php cli/fetch-lit.php`.
3. Commit the updated `public/vendor/lit/` files and `config/vendor_map.php`.
