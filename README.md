# uhifadhi/devkit-module

Devkit is **the** dev-only module for a uhifadhi installation. It installs through
`require-dev`, so it — and everything it registers — is absent from a production
build. `require-dev` is the production firewall.

Its job is to be a **collector**. Other modules ship **inert provider classes**
declared through two contracts the core publishes, and devkit gathers
them and materialises real developer tools — only in a dev install, because that
is the only place devkit exists.

## Contents

- [What it collects](#what-it-collects)
- [`fixtures:demo` — demo content in dependency order](#fixturesdemo--demo-content-in-dependency-order)
- [Descriptor commands](#descriptor-commands)
- [How a module contributes](#how-a-module-contributes)
- [The dev console](#the-dev-console)
- [Why the contracts live in the core, not here](#why-the-contracts-live-in-the-core-not-here)
- [Installing it](#installing-it)

## What it collects

Two contracts, both published by `uhifadhi/uhifadhi` under `Uhifadhi\Contracts\Devkit\`:

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

## The dev console

Devkit also ships a **dev-only inspector console** — the home a module builder
leaves open on a second monitor. It renders in the core shell's frame
and has four surfaces, reached under `/_devkit`:

- **Commands** — the assembled dev commands and demo-content loaders, grouped by
  the module that contributed them (the same collection `fixtures:demo` and the
  descriptor commands are built from, seen from the side).
- **Modules** — the installed fleet as one register: each package's version, the
  core it pins (`Composer\Semver` against the installed `uhifadhi/uhifadhi`), its
  declared permissions and stamped routes, and its DB-free reach classification.
- **Doctor** — the compatibility matrix and the findings that turn it into
  pass / warn / fail. Devkit computes the checks it can read (pins the core, no
  `dev-main` marker, routes stamped) and **flags the rest as deferred** rather
  than faking them green.
- **Wiring** — the tag inspector: for every contribution point the platform
  defines, who is registered and how many collected.

The console **reads**; it runs nothing (v1). The Run affordances are drawn
deliberately inert — commands run from the CLI.

Two firewalls keep it out of production. The first is `require-dev` (devkit is
not in a production build at all); the second is a `%kernel.debug%` guard in the
controller. A dev application makes the surfaces reachable by importing the
route resource in a `when@dev` block it owns:

```yaml
# config/routes/devkit.yaml (your application)
when@dev:
    devkit:
        resource: '@UhifadhiDevkitBundle/config/routes/console.php'
```

The introspection is DB-free: everything reads Composer, the router and the
tagged services. The one thing it cannot read standalone — the exact **per-area**
on/off count — needs the registry's per-area ledger (a database) and the host's list
of areas, and is flagged deferred on the Modules surface rather than faked.

## Why the contracts live in the core, not here

The inert provider classes ship inside always-installed modules, so their
`implements` clause must resolve at runtime **even when devkit is absent**. An
interface those modules point at therefore has to live in a package they always
have — `uhifadhi/uhifadhi` — and devkit depends on the same interfaces to
collect the providers. Neither side depends on the other. See the core's
`src/Uhifadhi/Contracts/docs/devkit-contracts.md`.

## Installing it

```console
composer require --dev uhifadhi/devkit-module
```

Until the core is on Packagist, the installation names where it comes from —
a repository entry in a dependency's own `composer.json` is ignored, so this
line belongs in the application's:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/uhifadhilabs/uhifadhi" }
]
```

The commands the installed modules describe appear on the console at once,
including the core's own:

```console
bin/console team:user:create ada@example.test Ada Mwangi --tier=super-admin
bin/console fixtures:demo
```

The first is how an installation gets its first administrator — the one account
no screen can make, because every screen is behind the sign-in it does not yet
have. Leave `--password=` off and the passphrase is read from standard input, so
it need never reach a shell history:

```console
printf '%s' "$PASSPHRASE" | bin/console team:user:create ada@example.test Ada Mwangi
```
