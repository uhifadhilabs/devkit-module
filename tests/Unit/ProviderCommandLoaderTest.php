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

namespace Uhifadhi\Devkit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Tester\CommandTester;
use Uhifadhi\Devkit\Command\DescriptorCommand;
use Uhifadhi\Devkit\Command\ProviderCommandLoader;
use Uhifadhi\Devkit\Tests\Fixtures\FakeCommandProvider;
use Uhifadhi\ModuleContracts\Devkit\CommandDescriptor;

/**
 * The loader that turns the modules' descriptors into console commands: it
 * answers for every descriptor name, becomes a runnable DescriptorCommand on
 * get(), delegates everything else to the inner (framework) loader, and refuses
 * two providers that claim the same command name.
 */
final class ProviderCommandLoaderTest extends TestCase
{
    /**
     * @param list<FakeCommandProvider> $providers
     */
    private function loader(FactoryCommandLoader $inner, array $providers): ProviderCommandLoader
    {
        return new ProviderCommandLoader($inner, $providers);
    }

    private function inner(): FactoryCommandLoader
    {
        return new FactoryCommandLoader([
            'fixtures:demo' => static fn (): Command => new Command('fixtures:demo'),
        ]);
    }

    public function testADescriptorBecomesAKnownRunnableCommand(): void
    {
        $ran = false;
        $loader = $this->loader($this->inner(), [
            new FakeCommandProvider([
                new CommandDescriptor('patrol:demo:reset', 'Reset patrol demo.', static function (array $arguments) use (&$ran): int {
                    $ran = true;

                    return 0;
                }),
            ]),
        ]);

        self::assertTrue($loader->has('patrol:demo:reset'));

        $command = $loader->get('patrol:demo:reset');
        self::assertInstanceOf(DescriptorCommand::class, $command);

        // And it actually runs the descriptor's handler.
        self::assertSame(0, new CommandTester($command)->execute([]));
        self::assertTrue($ran);
    }

    public function testItDelegatesUnknownNamesToTheInnerLoader(): void
    {
        $loader = $this->loader($this->inner(), []);

        self::assertTrue($loader->has('fixtures:demo'));
        self::assertSame('fixtures:demo', $loader->get('fixtures:demo')->getName());
    }

    public function testGetNamesMergesDescriptorAndInnerNames(): void
    {
        $loader = $this->loader($this->inner(), [
            new FakeCommandProvider([
                new CommandDescriptor('patrol:demo:reset', 'Reset patrol demo.', static fn (array $arguments): int => 0),
            ]),
        ]);

        $names = $loader->getNames();

        self::assertContains('patrol:demo:reset', $names);
        self::assertContains('fixtures:demo', $names);
    }

    public function testAnUnknownNameStillThrowsFromTheInnerLoader(): void
    {
        $loader = $this->loader($this->inner(), []);

        $this->expectException(CommandNotFoundException::class);
        $loader->get('does:not:exist');
    }

    public function testTwoProvidersClaimingTheSameNameAreRefused(): void
    {
        $loader = $this->loader($this->inner(), [
            new FakeCommandProvider([new CommandDescriptor('demo:clash', 'One.', static fn (array $arguments): int => 0)]),
            new FakeCommandProvider([new CommandDescriptor('demo:clash', 'Two.', static fn (array $arguments): int => 0)]),
        ]);

        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('both declare the command "demo:clash"');

        $loader->has('demo:clash');
    }
}
