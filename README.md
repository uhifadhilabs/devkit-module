# uhifadhi/devkit-module

Devkit is **the** dev-only module for a uhifadhi installation. It installs through
`require-dev`, so it — and everything it registers — is absent from a production
build. `require-dev` is the production firewall.

Its job is to be a **collector**. Other modules ship **inert provider classes**
declared through two contracts in `uhifadhi/module-contracts`, and devkit gathers
them and materialises real developer tools — only in a dev install, because that
is the only place devkit exists.

## Contents

- [What it collects](#what-it-collects)
- [`fixtures:demo` — demo content in dependency order](#fixturesdemo--demo-content-in-dependency-order)
- [Descriptor commands](#descriptor-commands)
- [How a module contributes](#how-a-module-contributes)
- [Why the contracts live in `module-contracts`, not here](#why-the-contracts-live-in-module-contracts-not-here)

## What it collects

Two seams, both defined in `uhifadhi/module-contracts` under `Devkit\`:

- `ContentProviderInterface` — a slice of demo content to seed, identified by a
  `key()` and ordered against other slices by `dependsOn()`.
- `CommandProviderInterface` — a bag of `CommandDescriptor`s, each a name, a help
  line, and a `\Closure(list<string>): int` that does the work.

A module tags its provider services and devkit `tagged_iterator`s them.

## `fixtures:demo` — demo content in dependency order

`fixtures:demo` collects every tagged `ContentProviderInterface`, **topologically
sorts** them on their `dependsOn()` edges, and calls `load()` on each in order,
printing each slice's label and description.

Ordering is a dependency graph, not a priority number: an incidents slice that
`dependsOn` `['area', 'patrol']` is seeded strictly after both. The sort refuses
a malformed graph loudly — a duplicate key, a dependency no provider supplies, or
a cycle each fail with a message naming the offending keys, rather than guessing
an order that would break far from its cause.

An installation with no providers registered seeds nothing and exits cleanly.

This command is the successor to `fixtures-module`'s hand-written `fixtures:all`
orchestrator, generalised: the step list is no longer written by hand, it is
derived from the providers the installed modules contribute.

## Descriptor commands

For every tagged `CommandProviderInterface`, each `CommandDescriptor` it returns
becomes a real Symfony console command. Because a descriptor's name is only known
at runtime, devkit registers them through a command **loader** that decorates the
framework's own — it answers for every descriptor name and delegates everything
else, including `fixtures:demo`, to the inner loader. The wrapper passes the
argument tail to the descriptor's closure as a `list<string>` and uses the
returned int as the command's exit code.

## How a module contributes

A module's provider is an ordinary tagged service. The tag is added as a
**literal string** in the module's own service config — a module cannot reference
devkit's constants, because devkit is absent from the production build the module
also ships into:

```php
// in an always-installed module's config/services.php
$services->set('patrol.devkit.content', PatrolContentProvider::class)
    ->args([service('doctrine.orm.entity_manager')])
    ->tag('uhifadhi.devkit.content_provider');   // == UhifadhiDevkitBundle::CONTENT_PROVIDER_TAG

$services->set('patrol.devkit.commands', PatrolCommandProvider::class)
    ->tag('uhifadhi.devkit.command_provider');   // == UhifadhiDevkitBundle::COMMAND_PROVIDER_TAG
```

In production the tag has no consumer (devkit is not installed), so the provider
is inert data. In a dev install devkit is present and collects it.

## Why the contracts live in `module-contracts`, not here

The inert provider classes ship inside always-installed modules, so their
`implements` clause must resolve at runtime **even when devkit is absent**. An
interface those modules point at therefore has to live in a package they always
have — `uhifadhi/module-contracts` — and devkit depends on the same interfaces to
collect the providers. Neither side depends on the other. See
`module-contracts/docs/devkit-contracts.md`.
