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

use Composer\InstalledVersions;

/**
 * The production {@see PackageIntrospector}: Composer's runtime metadata, read at
 * request time rather than remembered.
 *
 * A dev console that reported the fleet from a list somebody typed would be wrong
 * the first time a module was tagged and re-tagged, which is the whole reason to
 * build it. So the fleet is Composer's — `InstalledVersions` for the installed
 * set and each version, and a package's own composer.json for the constraints it
 * declares (which the runtime API does not expose). Owner resolution is
 * reflection: a service's class file, matched against the install path each
 * package reports.
 *
 * The composer.json reads are memoised per package: the Modules registry and the
 * Doctor matrix both ask the same package for its constraints in one render, and
 * a dev surface need not stat the same file twice.
 */
final class ComposerPackageIntrospector implements PackageIntrospector
{
    /** Everything the platform ships is a Composer package under this vendor. */
    private const string VENDOR = 'uhifadhi/';

    /**
     * @var array<string, array<string, string>> package name => its require map,
     *                                           read once per package
     */
    private array $requirements = [];

    /** @var array<string, string>|null install path (with trailing slash) => package name */
    private ?array $installPaths = null;

    public function ownerOf(object $service): ?ResolvedPackage
    {
        return $this->ownerOfFile((new \ReflectionObject($service))->getFileName());
    }

    public function ownerOfClass(string $class): ?ResolvedPackage
    {
        if (!class_exists($class) && !interface_exists($class)) {
            return null;
        }

        return $this->ownerOfFile((new \ReflectionClass($class))->getFileName());
    }

    private function ownerOfFile(string|false $file): ?ResolvedPackage
    {
        if (false === $file) {
            return null;
        }

        $file = $this->normalise($file);

        $longestMatch = null;
        $longest = -1;
        foreach ($this->installPaths() as $path => $name) {
            if (str_starts_with($file, $path) && \strlen($path) > $longest) {
                $longest = \strlen($path);
                $longestMatch = $name;
            }
        }

        return null === $longestMatch ? null : $this->package($longestMatch);
    }

    public function package(string $name): ?ResolvedPackage
    {
        if (!InstalledVersions::isInstalled($name)) {
            return null;
        }

        return ResolvedPackage::of($name, InstalledVersions::getPrettyVersion($name) ?? 'dev');
    }

    public function fleet(): array
    {
        $fleet = [];
        foreach (InstalledVersions::getInstalledPackages() as $name) {
            if (!str_starts_with($name, self::VENDOR)) {
                continue;
            }
            $package = $this->package($name);
            if (null !== $package) {
                $fleet[] = $package;
            }
        }

        return $fleet;
    }

    public function requirements(string $name): array
    {
        if (isset($this->requirements[$name])) {
            return $this->requirements[$name];
        }

        if (!InstalledVersions::isInstalled($name)) {
            return $this->requirements[$name] = [];
        }

        $path = InstalledVersions::getInstallPath($name);
        if (null === $path || !is_file($path.'/composer.json')) {
            return $this->requirements[$name] = [];
        }

        $raw = file_get_contents($path.'/composer.json');
        if (false === $raw) {
            return $this->requirements[$name] = [];
        }

        $decoded = json_decode($raw, true);
        $require = \is_array($decoded) && isset($decoded['require']) && \is_array($decoded['require'])
            ? $decoded['require']
            : [];

        $map = [];
        foreach ($require as $dependency => $constraint) {
            if (\is_string($dependency) && \is_string($constraint)) {
                $map[$dependency] = $constraint;
            }
        }

        return $this->requirements[$name] = $map;
    }

    /**
     * @return array<string, string>
     */
    private function installPaths(): array
    {
        if (null !== $this->installPaths) {
            return $this->installPaths;
        }

        $paths = [];
        foreach (InstalledVersions::getInstalledPackages() as $name) {
            $path = InstalledVersions::getInstallPath($name);
            if (null !== $path) {
                $paths[$this->normalise($path).'/'] = $name;
            }
        }

        return $this->installPaths = $paths;
    }

    private function normalise(string $path): string
    {
        $real = realpath($path);

        return rtrim(false === $real ? $path : $real, '/');
    }
}
