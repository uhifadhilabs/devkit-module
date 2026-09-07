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
 * One cell's meaning in the compatibility matrix — one colour, one meaning, never
 * decorative.
 *
 * `Pass`, `Warn`, `Fail` and `NotApplicable` are the four the design draws.
 * `Deferred` is the fifth and the honest one: a check devkit has not yet learned
 * to read at runtime (does a module's templates extend the shell? do its geometry
 * reads guard for NULL?). It is NOT a pass — faking those green was the one thing
 * the brief forbade — so it renders as its own state and is flagged as deferred
 * wherever it appears.
 */
enum CheckState: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
    case NotApplicable = 'na';
    case Deferred = 'deferred';
}
