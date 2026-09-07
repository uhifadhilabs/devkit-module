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
 * The whole conformance read: the compatibility matrix, the findings that turn
 * it into sentences, and the checks devkit has deferred.
 *
 * The counts head the Doctor surface. They count the COMPUTED checks honestly:
 * the deferred checks are reported as deferred, never folded into "passing".
 */
final readonly class DoctorReport
{
    /**
     * @param list<MatrixRow>        $matrix
     * @param list<Finding>          $attention needs-attention findings, worst first
     * @param list<Finding>          $passing   the clean checks, summarised
     * @param list<ConformanceCheck> $deferred  checks devkit does not read yet
     */
    public function __construct(
        public array $matrix,
        public array $attention,
        public array $passing,
        public array $deferred,
    ) {
    }

    /**
     * The matrix columns, in order — every check, computed and deferred alike.
     *
     * @return list<ConformanceCheck>
     */
    public function columns(): array
    {
        return ConformanceCheck::all();
    }

    public function computedCheckCount(): int
    {
        return \count(ConformanceCheck::computed());
    }

    public function deferredCheckCount(): int
    {
        return \count($this->deferred);
    }

    public function warnCount(): int
    {
        return $this->cellsInState(CheckState::Warn);
    }

    public function failCount(): int
    {
        return $this->cellsInState(CheckState::Fail);
    }

    /**
     * How many COMPUTED checks are clean across the whole fleet — no warn, no
     * fail in any module's cell for that check.
     */
    public function passingCheckCount(): int
    {
        $passing = 0;
        foreach (ConformanceCheck::computed() as $check) {
            $clean = true;
            foreach ($this->matrix as $row) {
                if (\in_array($row->cell($check), [CheckState::Warn, CheckState::Fail], true)) {
                    $clean = false;
                    break;
                }
            }
            if ($clean) {
                ++$passing;
            }
        }

        return $passing;
    }

    private function cellsInState(CheckState $state): int
    {
        $count = 0;
        foreach ($this->matrix as $row) {
            foreach ($row->cells as $cell) {
                if ($cell === $state) {
                    ++$count;
                }
            }
        }

        return $count;
    }
}
