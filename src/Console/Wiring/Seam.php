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

namespace Uhifadhi\Devkit\Console\Wiring;

/**
 * One contribution seam — a tag the platform defines — and everyone registered
 * on it.
 *
 * A seam with no contributors is still a seam: the platform defined the tag, and
 * "nothing has wired into this yet" is a true and useful reading, not an empty
 * one to hide.
 */
final readonly class Seam
{
    /**
     * @param list<SeamContributor> $contributors
     */
    public function __construct(
        public string $tag,
        public string $description,
        public array $contributors,
    ) {
    }

    public function count(): int
    {
        return \count($this->contributors);
    }
}
