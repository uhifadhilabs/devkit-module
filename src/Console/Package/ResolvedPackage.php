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

namespace Uhifadhi\Devkit\Console\Package;

/**
 * One installed Composer package, as the console needs to speak of it: its full
 * name, its pretty version, and the short label the fleet calls it by.
 *
 * The short label is how the platform's own vocabulary works — a package named
 * `uhifadhi/patrol-module` is "patrol" in the module grid, the sub-nav and every
 * console surface. It is derived, never stored twice: strip the vendor and the
 * `-module` suffix, and what remains is the slug the rest of the fleet already
 * uses. The one package that is not a module — `uhifadhi/uhifadhi`, the core the
 * modules pin — keeps its own name, because the core is not a module and
 * calling it one would be the first lie a registry tells.
 */
final readonly class ResolvedPackage
{
    public function __construct(
        public string $name,
        public string $version,
        public string $shortName,
    ) {
    }

    /**
     * Build from a package name + version, deriving the short label.
     */
    public static function of(string $name, string $version): self
    {
        return new self($name, $version, self::shorten($name));
    }

    /**
     * `uhifadhi/patrol-module` → `patrol`; `uhifadhi/uhifadhi` → `uhifadhi`
     * (it is the core, not a module).
     */
    private static function shorten(string $name): string
    {
        $bare = str_contains($name, '/') ? substr($name, strrpos($name, '/') + 1) : $name;

        if (str_ends_with($bare, '-module')) {
            return substr($bare, 0, -\strlen('-module'));
        }

        return $bare;
    }
}
