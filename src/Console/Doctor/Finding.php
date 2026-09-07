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
 * One doctor finding — a matrix cell turned into a sentence a builder can act on:
 * what is wrong (or right), what to do about it, and which file to open.
 */
final readonly class Finding
{
    public function __construct(
        public CheckState $state,
        public string $title,
        public string $message,
        public ?string $where = null,
    ) {
    }
}
