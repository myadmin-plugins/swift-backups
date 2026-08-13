<?php

declare(strict_types=1);

namespace Detain\MyAdminSwift\Tests;

use Detain\MyAdminSwift\Plugin;
use MyAdmin\Plugins\Testing\PluginContractTestCase;

/**
 * Shared contract assertions for this plugin, plus the identity pin the shared
 * harness cannot provide.
 *
 * ---------------------------------------------------------------------------------
 * WHY THIS FILE IS ADDITIVE
 * ---------------------------------------------------------------------------------
 * This is a new file, not a replacement. Every pre-existing test in this package is
 * kept exactly as it was: the catalogue below runs *alongside* them, so the package
 * gains the 18 fleet-wide contract inspectors without giving up a single assertion it
 * already had. Some coverage is therefore duplicated -- deliberately, because losing
 * an assertion nobody has re-read is the more expensive mistake.
 *
 * ---------------------------------------------------------------------------------
 * WHAT THE CATALOGUE ADDS
 * ---------------------------------------------------------------------------------
 * {@see PluginContractTestCase} executes this plugin rather than reading it: it primes
 * the bare constants the class body references, resolves every requirement path it
 * registers against the filesystem, checks each hook key is one core actually
 * dispatches, and runs getSettings()/getMenu()/apiRegister() for real. A dangling
 * registration or an undispatched hook key fails here even though it is invisible to
 * an assertion that only reads the registration table.
 *
 * ---------------------------------------------------------------------------------
 * WHAT THE IDENTITY PIN ADDS
 * ---------------------------------------------------------------------------------
 * Every catalogue assertion is conditional on the registration existing, so an
 * emptied getHooks() would leave the shared suite green. The pin below is the part
 * only this repo can state: which hooks this plugin is supposed to register, and that
 * $type still selects the assertions intended for it.
 */
class ContractTest extends PluginContractTestCase
{
    /**
     * The class under contract.
     *
     * @return string
     */
    protected function pluginClass()
    {
        return Plugin::class;
    }

    /**
     * Pins this plugin's identity and the shape of its hook table.
     *
     * @return void
     */
    public function testRegistersItsIdentityAndHooks(): void
    {
        $this->assertSame(
            'plugin',
            Plugin::$type,
            'changing $type silently changes which contract assertions apply'
        );

        $this->primeConstants();

        $this->assertSame(
            [
            'system.settings',
            'function.requirements',
        ],
            array_keys(Plugin::getHooks()),
            'the hook table changed shape -- a key was added, removed or renamed'
        );

        foreach (Plugin::getHooks() as $key => $handler) {
            $this->assertIsCallable($handler, $key.' no longer resolves to anything callable');
        }
    }
}
