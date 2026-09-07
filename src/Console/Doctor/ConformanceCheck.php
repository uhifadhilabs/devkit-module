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

namespace Uhifadhi\Devkit\Console\Doctor;

/**
 * The columns of the compatibility matrix — the modular-core checklist, and
 * which of it devkit can actually read at runtime.
 *
 * Three are COMPUTED from data the console already has: the core a package pins,
 * the dev-main markers in its constraints, and whether its module routes carry
 * the seam's stamp. Two are DEFERRED — extending the shell base and guarding
 * geometry reads for NULL both need source scanning devkit does not do yet — and
 * they say so ({@see CheckState::Deferred}) rather than showing green.
 */
enum ConformanceCheck: string
{
    case PinsCore = 'pins-core';
    case NoDevMain = 'no-dev-main';
    case RoutesStamped = 'routes-stamped';
    case ExtendsShell = 'extends-shell';
    case GeomGuards = 'geom-guards';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @return list<self> the checks devkit computes
     */
    public static function computed(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $c): bool => $c->isComputed()));
    }

    /**
     * @return list<self> the checks devkit has not learned to read yet
     */
    public static function deferred(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $c): bool => !$c->isComputed()));
    }

    public function isComputed(): bool
    {
        return match ($this) {
            self::PinsCore, self::NoDevMain, self::RoutesStamped => true,
            self::ExtendsShell, self::GeomGuards => false,
        };
    }

    /** The matrix column heading's first line. */
    public function header(): string
    {
        return match ($this) {
            self::PinsCore => 'pins core',
            self::NoDevMain => 'no dev-main',
            self::RoutesStamped => 'routes',
            self::ExtendsShell => 'extends',
            self::GeomGuards => 'geom',
        };
    }

    /** One line naming what the check reads — printed on the deferred cards. */
    public function caption(): string
    {
        return match ($this) {
            self::PinsCore => 'The module admits the installed module-contracts version.',
            self::NoDevMain => 'No constraint carries a dev-main marker.',
            self::RoutesStamped => 'The module’s routes carry the seam’s _uhifadhi_module stamp.',
            self::ExtendsShell => 'Domain templates extend the shell base — needs template scanning devkit does not do yet.',
            self::GeomGuards => 'Geometry reads guard for NULL — needs source scanning devkit does not do yet.',
        };
    }

    /** The matrix column heading's second line. */
    public function subheader(): string
    {
        return match ($this) {
            self::PinsCore => '^current',
            self::NoDevMain => 'marker',
            self::RoutesStamped => 'stamped',
            self::ExtendsShell => 'shell',
            self::GeomGuards => 'guards',
        };
    }
}
