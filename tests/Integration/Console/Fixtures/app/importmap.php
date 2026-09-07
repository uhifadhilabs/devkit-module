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

/*
 * THE STAND-IN HOST'S IMPORTMAP — the one file an application owns that the shell
 * frame the console renders in reads. It has the entrypoint every Flex-installed
 * application has, `app`, and nothing else.
 */

return [
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
];
