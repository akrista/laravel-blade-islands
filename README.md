# 🏝️ Blade Islands For Laravel

<p align="center">
  <img src="art/header.png" alt="Blade Islands for Laravel" width="1024">
</p>

[![Latest Stable Version](https://img.shields.io/packagist/v/akrista/blade-islands)](https://packagist.org/packages/akrista/blade-islands)
[![Total Downloads](https://img.shields.io/packagist/dt/akrista/blade-islands)](https://packagist.org/packages/akrista/blade-islands)
[![License](https://img.shields.io/packagist/l/akrista/blade-islands)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.5%2B-777BB4)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13%2B-FF2D20)](https://laravel.com)

> **Fork notice:** This is a maintained fork of [`eznix86/blade-islands`](https://github.com/eznix86/blade-islands). The original implementation was created by **Bruno Bernard** ([github.com/eznix86](https://github.com/eznix86)). All credit for the original design goes to him. This fork is maintained by [Akrista](https://github.com/akrista) under the same MIT license.

Server-side Blade directives for React, Vue, and Svelte islands. Keep Blade as your primary rendering layer and hydrate only the parts of the page that need JavaScript.

This package is the **PHP half** of Blade Islands — it renders the island placeholders. The browser runtime lives in the npm package [`blade-islands`](https://github.com/akrista/blade-islands).

## Contents

- [Why Blade Islands](#why-blade-islands)
- [When Not to Use It](#when-not-to-use-it)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Available Directives](#available-directives)
- [Options](#options)
- [Props](#props)
- [Vite Setup](#vite-setup)
- [Component Resolution](#component-resolution)
- [Custom Root](#custom-root)
- [Preserve Mounted Islands](#preserve-mounted-islands)
- [PHP API](#php-api)
- [Protocol](#protocol)
- [Validation & Errors](#validation--errors)
- [Testing Your Islands](#testing-your-islands)
- [Troubleshooting](#troubleshooting)
- [Blade Islands vs X](#blade-islands-vs-x)
- [Requirements](#requirements)
- [Companion Package](#companion-package)
- [Contributing](#contributing)
- [License](#license)

## Why Blade Islands

Blade Islands is built for Laravel apps that are mostly server-rendered but need a few interactive components in places like:

- search inputs and live filter bars
- dashboards and charts
- maps
- counters and live numbers
- dialogs and drawers
- comment threads, like buttons, vote widgets

You keep Blade as the rendering layer and mount a small React, Vue, or Svelte component in the exact spot that needs JavaScript. You do not turn your app into a single-page app.

## When Not to Use It

Blade Islands is a poor fit when:

- **The whole page should be a single SPA.** Use [Inertia.js](https://inertiajs.com/) instead.
- **Most of your UI is interactive** and the server-rendered parts are minimal. Build a SPA or pick a Livewire-first architecture.
- **You need server-driven reactivity** — re-render a component on the server in response to events. That is what [Livewire](https://livewire.laravel.com/) is for. Blade Islands only ships the initial DOM; the runtime does not call back to Laravel.
- **You want a full client-side router and state store.** Pick the framework's native starter kit.

If you are not sure, start with Blade. Add an island only when the component clearly needs JavaScript.

## Installation

The service provider is auto-discovered, so installing the package is enough to register the `@react`, `@vue`, and `@svelte` directives.

```bash
composer require akrista/blade-islands
```

Then install the browser runtime, your frontend framework, and the matching Vite plugin.

### React

```bash
npm install blade-islands react react-dom @vitejs/plugin-react
```

### Vue

```bash
npm install blade-islands vue @vitejs/plugin-vue
```

### Svelte

```bash
npm install blade-islands svelte @sveltejs/vite-plugin-svelte
```

## Quick Start

This walks through React. Vue and Svelte are the same shape — swap the framework name everywhere you see it.

### 1. Boot the runtime

`resources/js/app.js`

```js
import islands from 'blade-islands/react'

islands()
```

The runtime scans the DOM for elements that match the island protocol and mounts the matching component. See [Protocol](#protocol) for the attribute contract.

### 2. Load the entry from your layout

```blade
<head>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
```

For Vue and Svelte, drop `@viteReactRefresh`.

### 3. Add the component

`resources/js/islands/ProfileCard.jsx`

```jsx
export default function ProfileCard({ user }) {
  return <div className="card">Hello, {user.name}</div>
}
```

### 4. Render the island from Blade

```blade
@react('ProfileCard', ['user' => $user])
```

Blade renders a placeholder, and the runtime mounts `resources/js/islands/ProfileCard.jsx` with `user` as a prop.

## Available Directives

```blade
@react('Dashboard', ['user' => $user])
@vue('Support/TicketList', ['tickets' => $tickets])
@svelte('Cart/Drawer', ['count' => $count])
```

Each directive takes a component name and an optional props array. See [Options](#options) for the full argument list.

## Options

Each directive accepts up to four positional arguments:

```php
@react($component, $props = [], $preserve = false, $key = null)
```

| Argument     | Type   | Description |
|--------------|--------|-------------|
| `$component` | string | Component path relative to the JavaScript component root (see [Component Resolution](#component-resolution)). |
| `$props`     | array  | Props encoded into the rendered HTML as JSON. |
| `$preserve`  | bool   | Keep an existing island mounted when the same DOM is processed again. See [Preserve Mounted Islands](#preserve-mounted-islands). |
| `$key`       | string | Unique key for distinguishing repeated preserved islands. |

Named arguments are also supported, which is clearer when you only need to set the later ones:

```blade
@react(
    component: 'Dashboard',
    props: ['user' => $user],
    preserve: true,
    key: 'dashboard-main',
)
```

## Props

Props are encoded as JSON inside a `data-props` attribute and `htmlspecialchars`-escaped, so the placeholder is safe to drop into Blade without further escaping. Any JSON-serializable value works:

```blade
@react('SearchBox', [
    'placeholder' => 'Search products...',
    'initial' => 'php',
    'limit' => 20,
    'tags' => ['php', 'laravel', 'vue'],
    'filters' => ['category' => 'docs', 'sort' => 'newest'],
    'user' => ['id' => $user->id, 'name' => $user->name],
])
```

A few rules to keep in mind:

- **Pass the fields you need, not whole models.** Eloquent's `toArray()` runs inside `json_encode`, so simple casts work, but explicit props are easier to reason about and avoid leaking fields you did not intend to send to the browser.
- **Closures, resources, and other non-serializable values throw.** The encoder uses `JSON_THROW_ON_ERROR`, so a bad prop fails loudly instead of silently producing `null`.
- **HTML in props is treated as data, not markup.** The runtime passes the prop through to your component as a string. Render it with `dangerouslySetInnerHTML` (React), `v-html` (Vue), or `{@html ...}` (Svelte) only when you actually trust it.

## Vite Setup

Register the plugin for the framework you use. Blade Islands does not require its own Vite plugin — the framework plugin is enough.

### React

```js
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
})
```

Include `@viteReactRefresh` in `<head>` for fast refresh during development.

### Vue

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
})
```

### Svelte

```js
import { defineConfig } from 'vite'
import { svelte } from '@sveltejs/vite-plugin-svelte'

export default defineConfig({
  plugins: [svelte()],
})
```

## Component Resolution

The default component root is `resources/js/islands`. Subdirectories resolve automatically, so:

```blade
@vue('Billing/Invoices/Table', [...])
```

mounts:

```text
resources/js/islands/Billing/Invoices/Table.vue
```

The same shape applies to React (`.jsx` / `.tsx`) and Svelte (`.svelte`).

## Custom Root

The PHP side does not know about the filesystem. The root is configured in the browser runtime:

```js
import islands from 'blade-islands/vue'

islands({
  root: '/resources/js/widgets',
  components: import.meta.glob('/resources/js/widgets/**/*.vue'),
})
```

With that config, `@vue('Dashboard', [...])` mounts `resources/js/widgets/Dashboard.vue`. The component string in Blade is just a path inside whatever root the runtime is using.

## Preserve Mounted Islands

By default, the runtime mounts every placeholder it finds in the DOM. If the same DOM is processed more than once — for example after an AJAX update, a partial reload, or your own boot logic runs again — the placeholder will be re-mounted and any local state inside the component is lost.

Pass `preserve: true` to keep an existing island mounted:

```blade
@react('Dashboard/RevenueChart', ['stats' => $stats], preserve: true)
@vue('Dashboard/RevenueChart', ['stats' => $stats], preserve: true)
@svelte('Dashboard/RevenueChart', ['stats' => $stats], preserve: true)
```

### Keys for repeated islands

When you render the same preserved component more than once, give each one a unique `key` so the runtime can tell them apart:

```blade
@foreach ($products as $product)
    @react('Product/Card', ['product' => $product], preserve: true, key: "product-{$product->id}")
@endforeach
```

### Default keys

If you set `preserve: true` without a `key`, Blade Islands falls back to `framework:componentname` with both parts lowercased. For example, `@svelte('CartDrawer', [], true)` produces `data-key="svelte:cartdrawer"`. The default is fine for a single preserved island on the page, but you must pass an explicit `key` as soon as the same preserved component appears more than once.

## PHP API

If you need to render an island outside a Blade template — from a controller, a view composer, a queued job, or a test — call the `IslandRenderer` directly. It is bound as a singleton on the container:

```php
use Akrista\BladeIslands\Support\IslandRenderer;

app(IslandRenderer::class)->render('react', 'Dashboard', [
    'user' => $user->only(['id', 'name', 'email']),
]);
```

The signature mirrors the Blade directive:

```php
public function render(
    string $framework,
    string $component,
    array $props = [],
    bool $preserve = false,
    ?string $key = null,
): \Illuminate\Support\HtmlString;
```

`renderDirective()` is the lower-level entry point used by the Blade directives. It accepts the same arguments either positionally or by name:

```php
app(IslandRenderer::class)->renderDirective('vue', [
    'component' => 'Support/TicketList',
    'props' => ['tickets' => $tickets],
    'preserve' => true,
    'key' => 'tickets-main',
]);
```

Both methods return an `Illuminate\Support\HtmlString`, which Blade prints without further escaping. Inject the renderer through the constructor when you can:

```php
use Akrista\BladeIslands\Support\IslandRenderer;

class DashboardController
{
    public function __construct(private readonly IslandRenderer $islands) {}

    public function show(): \Illuminate\Http\Response
    {
        $html = $this->islands->render('react', 'Dashboard/RevenueChart', [
            'stats' => Stats::forCurrentUser(),
        ]);

        return response($html);
    }
}
```

## Protocol

Blade Islands renders lightweight placeholders. A typical output looks like:

```html
<div
  data-island="react"
  data-component="Dashboard"
  data-props="{&quot;user&quot;:{&quot;name&quot;:&quot;Bruno&quot;}}"
  data-preserve="true"
  data-key="dashboard-main"
></div>
```

| Attribute        | Required           | Notes |
|------------------|--------------------|-------|
| `data-island`    | yes                | Framework identifier: `react`, `vue`, or `svelte`. |
| `data-component` | yes                | Component path relative to the runtime's `root`. |
| `data-props`     | yes                | JSON-encoded props, HTML-attribute-escaped. |
| `data-preserve`  | no                 | `true` to opt the island into preservation. |
| `data-key`       | with `data-preserve` | Identity used by the runtime when preserving. Falls back to `framework:componentname` (lowercased). |

The runtime is responsible for replacing the empty `<div>` with the mounted component. You do not need to add CSS or wrappers — an island is just one `<div>`.

## Validation & Errors

The renderer validates its inputs and throws `InvalidArgumentException` for the two cases that almost always indicate a bug:

- the component name is empty or not a string
- `$props` is not an array

`json_encode` runs with `JSON_THROW_ON_ERROR`, so props that cannot be serialized (a resource handle, a `PDO` connection, a closure) throw `JsonException` instead of silently producing `null`. Wrap a render call in a `try` block if you are rendering user-driven data and want a graceful failure.

## Testing Your Islands

The placeholders are plain HTML, which makes them easy to test. Use `Blade::render` directly:

```php
use Illuminate\Support\Facades\Blade;

it('renders a react island placeholder', function (): void {
    $html = Blade::render("@react('ProfileCard', ['user' => ['name' => 'Bruno']])");

    expect($html)->toContain('data-island="react"')
        ->toContain('data-component="ProfileCard"')
        ->toContain('&quot;name&quot;:&quot;Bruno&quot;');
});
```

For end-to-end tests (Dusk, Playwright, Cypress), assert on the mounted DOM the way you would for any other component — the placeholder disappears once the runtime takes over.

## Troubleshooting

**The component does not appear.**

- Check the browser console for the runtime to report a missing import. The most common cause is a typo between `@react('Foo', ...)` and the file `resources/js/islands/Foo.jsx`.
- Make sure the framework's Vite plugin is registered, otherwise the component file is not transpiled.
- Confirm the JS entry is loaded in your layout. View source and look for `data-island="react"` in the response — if it is missing, Blade is not seeing the directive.

**The component mounts but the props look wrong.**

- If `json_encode` cannot handle a value, the renderer throws. A `Carbon` instance is fine; a `PDO` connection is not. Pass plain arrays, scalars, and JSON-serializable values.
- Open devtools and inspect the element. The `data-props` attribute holds the encoded value with HTML entities. Pipe it through a JSON decoder in the console to read it.

**A preserved island re-mounts on every update.**

- You are probably using the default `key`. Pass a unique `key` per island whenever the same preserved component appears more than once on the page.

**The element flashes before the component is ready.**

- The runtime mounts on `DOMContentLoaded`. If you inject islands after load, call the runtime's `mount` / `scan` entry point again, or pass the new DOM to the runtime manually. This is a runtime concern, not a PHP-side one.

## Blade Islands vs X

### Inertia.js

Inertia is a better fit when your application wants React, Vue, or Svelte to render full pages with a JavaScript-first page architecture.

Blade Islands is a better fit when your application is already Blade-first and you want to keep server-rendered pages while hydrating only selected components.

### Livewire

Livewire is the right tool when you want server-driven reactivity — render a component on the server in response to events, wire, and Alpine. The client does not ship its own state for that component.

Blade Islands is the right tool when you have a small React/Vue/Svelte component that runs entirely in the browser and you just need a Blade-shaped hole to mount it in. The two packages compose: you can put an island inside a Livewire component.

### MingleJS

MingleJS is often used in Laravel applications that embed React or Vue components, especially in Livewire-heavy codebases.

Blade Islands is more naturally suited to Blade-first applications that want progressive enhancement with minimal architectural change. It does not depend on Livewire and may also be used alongside Livewire when that fits your application.

### Laravel UI

Laravel UI is a legacy scaffolding package for frontend presets and authentication views.

Blade Islands solves a different problem: adding targeted client-side interactivity to server-rendered Blade pages.

### htmx / Hotwire

htmx and Hotwire are excellent fits for partial page updates from the server with a sprinkle of JavaScript.

Blade Islands is the right choice when the interactivity you need is not a server round-trip — a charting library, a map, a complex client-side filter, a `<canvas>` widget — and you want a normal React/Vue/Svelte component for it.

## Requirements

- PHP 8.5+
- Laravel 13+
- A Vite-driven asset pipeline with the framework's Vite plugin

## Companion Package

The browser runtime is a separate npm package:

- repository: [`akrista/blade-islands`](https://github.com/akrista/blade-islands)

Install it alongside the framework plugin. See [Installation](#installation).

## Contributing

Contributions are welcome.

1. Fork the repository
2. Create a focused branch
3. Add or update tests
4. Run the full check suite:

    ```bash
    composer install
    composer test
    composer test:types
    composer lint
    ```

5. Open a pull request with a clear summary

## License

MIT. See [LICENSE](LICENSE) for the full text.
