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

namespace Uhifadhi\Devkit\Console\Module;

/**
 * Whether a package pins the core the installation is actually running.
 *
 * `OnCore` — its `uhifadhi/uhifadhi` constraint admits the installed
 * core version. `BehindCore` — it pins an older core and would need
 * widening and re-tagging. `NotApplicable` — it declares no constraint on the
 * contracts at all: the contracts package itself (it IS the core), and the
 * infrastructure packages that render the fleet without pinning the core.
 */
enum CoreState
{
    case OnCore;
    case BehindCore;
    case NotApplicable;
}
