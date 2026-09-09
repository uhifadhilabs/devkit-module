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
 * WHAT COMPOSER KNOWS, behind an interface the console reads through — so a test
 * can hand the registry a fleet it controls (an incident-module that pins the
 * old core, a module that still carries a dev-main marker) without a fixture
 * package on disk for every scenario.
 *
 * The production implementation reads {@see \Composer\InstalledVersions} and each
 * package's own composer.json; {@see ComposerPackageIntrospector}. Everything the
 * Modules registry, the Doctor matrix and the Wiring inspector say about a
 * package flows through the four questions below.
 */
interface PackageIntrospector
{
    /**
     * The Composer package a service's class was shipped in — the module that
     * contributed it — or null when it belongs to no installed package the
     * introspector can place (an app-level service, say).
     */
    public function ownerOf(object $service): ?ResolvedPackage;

    /**
     * The Composer package a class was shipped in — the same question as
     * {@see ownerOf()}, asked of a class name rather than an instance, because
     * the Wiring inspector knows tagged services by class and never builds them.
     */
    public function ownerOfClass(string $class): ?ResolvedPackage;

    /**
     * A package by name, or null when this installation does not have it.
     */
    public function package(string $name): ?ResolvedPackage;

    /**
     * Every uhifadhi package on disk, in Composer's order — the fleet the
     * registry lists. Includes the core (`uhifadhi/uhifadhi`) and the
     * infrastructure packages that carry no module provider.
     *
     * @return list<ResolvedPackage>
     */
    public function fleet(): array;

    /**
     * A package's `require` map — name => version constraint — as declared in its
     * own composer.json. Empty when the package or its manifest cannot be read.
     *
     * @return array<string, string>
     */
    public function requirements(string $name): array;
}
