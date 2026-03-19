<?php

declare(strict_types=1);

namespace Detain\MyAdminSwift\Tests;

use Detain\MyAdminSwift\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Tests for the Plugin class.
 *
 * Verifies class structure, static properties, hook registration,
 * and event handler method signatures.
 *
 * @covers \Detain\MyAdminSwift\Plugin
 */
class PluginTest extends TestCase
{
    /**
     * Test that the Plugin class exists and can be loaded.
     *
     * @return void
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Plugin::class));
    }

    /**
     * Test that the Plugin class can be instantiated.
     *
     * @return void
     */
    public function testCanBeInstantiated(): void
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * Test that the $name static property is set correctly.
     *
     * @return void
     */
    public function testNameProperty(): void
    {
        $this->assertSame('Swift Plugin', Plugin::$name);
    }

    /**
     * Test that the $description static property is set correctly.
     *
     * @return void
     */
    public function testDescriptionProperty(): void
    {
        $this->assertSame('Allows handling of Swift based Backups', Plugin::$description);
    }

    /**
     * Test that the $help static property is an empty string.
     *
     * @return void
     */
    public function testHelpProperty(): void
    {
        $this->assertSame('', Plugin::$help);
    }

    /**
     * Test that the $type static property is set to 'plugin'.
     *
     * @return void
     */
    public function testTypeProperty(): void
    {
        $this->assertSame('plugin', Plugin::$type);
    }

    /**
     * Test that all expected static properties exist on the class.
     *
     * @return void
     */
    public function testStaticPropertiesExist(): void
    {
        $ref = new ReflectionClass(Plugin::class);
        $staticProps = $ref->getStaticProperties();

        $this->assertArrayHasKey('name', $staticProps);
        $this->assertArrayHasKey('description', $staticProps);
        $this->assertArrayHasKey('help', $staticProps);
        $this->assertArrayHasKey('type', $staticProps);
    }

    /**
     * Test that all static properties are strings.
     *
     * @return void
     */
    public function testStaticPropertiesAreStrings(): void
    {
        $this->assertIsString(Plugin::$name);
        $this->assertIsString(Plugin::$description);
        $this->assertIsString(Plugin::$help);
        $this->assertIsString(Plugin::$type);
    }

    /**
     * Test that getHooks returns an array.
     *
     * @return void
     */
    public function testGetHooksReturnsArray(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertIsArray($hooks);
    }

    /**
     * Test that getHooks registers the system.settings hook.
     *
     * @return void
     */
    public function testGetHooksContainsSystemSettings(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertArrayHasKey('system.settings', $hooks);
    }

    /**
     * Test that getHooks registers the function.requirements hook.
     *
     * @return void
     */
    public function testGetHooksContainsFunctionRequirements(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertArrayHasKey('function.requirements', $hooks);
    }

    /**
     * Test that getHooks does not register the ui.menu hook (it is commented out).
     *
     * @return void
     */
    public function testGetHooksDoesNotContainUiMenu(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertArrayNotHasKey('ui.menu', $hooks);
    }

    /**
     * Test that the system.settings hook points to getSettings method.
     *
     * @return void
     */
    public function testSystemSettingsHookCallable(): void
    {
        $hooks = Plugin::getHooks();
        $callable = $hooks['system.settings'];

        $this->assertIsArray($callable);
        $this->assertCount(2, $callable);
        $this->assertSame(Plugin::class, $callable[0]);
        $this->assertSame('getSettings', $callable[1]);
        $this->assertTrue(is_callable($callable));
    }

    /**
     * Test that the function.requirements hook points to getRequirements method.
     *
     * @return void
     */
    public function testFunctionRequirementsHookCallable(): void
    {
        $hooks = Plugin::getHooks();
        $callable = $hooks['function.requirements'];

        $this->assertIsArray($callable);
        $this->assertCount(2, $callable);
        $this->assertSame(Plugin::class, $callable[0]);
        $this->assertSame('getRequirements', $callable[1]);
        $this->assertTrue(is_callable($callable));
    }

    /**
     * Test that getHooks returns exactly two hooks.
     *
     * @return void
     */
    public function testGetHooksCount(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertCount(2, $hooks);
    }

    /**
     * Test that getSettings method exists and is static.
     *
     * @return void
     */
    public function testGetSettingsMethodIsStatic(): void
    {
        $ref = new ReflectionClass(Plugin::class);
        $method = $ref->getMethod('getSettings');

        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test that getRequirements method exists and is static.
     *
     * @return void
     */
    public function testGetRequirementsMethodIsStatic(): void
    {
        $ref = new ReflectionClass(Plugin::class);
        $method = $ref->getMethod('getRequirements');

        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test that getMenu method exists and is static.
     *
     * @return void
     */
    public function testGetMenuMethodIsStatic(): void
    {
        $ref = new ReflectionClass(Plugin::class);
        $method = $ref->getMethod('getMenu');

        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test that getSettings accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetSettingsAcceptsGenericEvent(): void
    {
        $ref = new ReflectionClass(Plugin::class);
        $method = $ref->getMethod('getSettings');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());

        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame(GenericEvent::class, $type->getName());
    }

    /**
     * Test that getRequirements accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetRequirementsAcceptsGenericEvent(): void
    {
        $ref = new ReflectionClass(Plugin::class);
        $method = $ref->getMethod('getRequirements');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());

        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame(GenericEvent::class, $type->getName());
    }

    /**
     * Test that getMenu accepts a GenericEvent parameter.
     *
     * @return void
     */
    public function testGetMenuAcceptsGenericEvent(): void
    {
        $ref = new ReflectionClass(Plugin::class);
        $method = $ref->getMethod('getMenu');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('event', $params[0]->getName());

        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame(GenericEvent::class, $type->getName());
    }

    /**
     * Test that getRequirements calls add_requirement on the loader subject.
     *
     * @return void
     */
    public function testGetRequirementsCallsAddRequirement(): void
    {
        $loader = new class {
            /** @var array */
            public $requirements = [];

            /**
             * @param string $name
             * @param string $path
             * @return void
             */
            public function add_requirement(string $name, string $path): void
            {
                $this->requirements[$name] = $path;
            }
        };

        $event = new GenericEvent($loader);
        Plugin::getRequirements($event);

        $this->assertArrayHasKey('class.Swift', $loader->requirements);
        $this->assertSame(
            '/../vendor/detain/myadmin-swift-backups/src/Swift.php',
            $loader->requirements['class.Swift']
        );
    }

    /**
     * Test that getSettings calls add_text_setting and add_password_setting on the settings subject.
     *
     * @return void
     */
    public function testGetSettingsCallsSettingsMethods(): void
    {
        $settings = new class {
            /** @var array */
            public $textSettings = [];
            /** @var array */
            public $passwordSettings = [];

            /**
             * @param mixed ...$args
             * @return void
             */
            public function add_text_setting(...$args): void
            {
                $this->textSettings[] = $args;
            }

            /**
             * @param mixed ...$args
             * @return void
             */
            public function add_password_setting(...$args): void
            {
                $this->passwordSettings[] = $args;
            }
        };

        $event = new GenericEvent($settings);
        Plugin::getSettings($event);

        $this->assertCount(3, $settings->textSettings, 'Expected 3 text settings to be added');
        $this->assertCount(1, $settings->passwordSettings, 'Expected 1 password setting to be added');
    }

    /**
     * Test that the Plugin constructor has no required parameters.
     *
     * @return void
     */
    public function testConstructorHasNoRequiredParameters(): void
    {
        $ref = new ReflectionClass(Plugin::class);
        $constructor = $ref->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertCount(0, $constructor->getParameters());
    }

    /**
     * Test that the class resides in the correct namespace.
     *
     * @return void
     */
    public function testClassNamespace(): void
    {
        $ref = new ReflectionClass(Plugin::class);
        $this->assertSame('Detain\\MyAdminSwift', $ref->getNamespaceName());
    }

    /**
     * Test that the class has exactly the expected public methods.
     *
     * @return void
     */
    public function testPublicMethodList(): void
    {
        $ref = new ReflectionClass(Plugin::class);
        $methods = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === Plugin::class) {
                $methods[] = $method->getName();
            }
        }
        sort($methods);

        $expected = ['__construct', 'getHooks', 'getMenu', 'getRequirements', 'getSettings'];
        $this->assertSame($expected, $methods);
    }

    /**
     * Test that hook callables all point to existing methods.
     *
     * @return void
     */
    public function testAllHookMethodsExist(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $eventName => $callable) {
            $this->assertTrue(
                method_exists($callable[0], $callable[1]),
                "Method {$callable[1]} does not exist for hook {$eventName}"
            );
        }
    }
}
