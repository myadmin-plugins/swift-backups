---
name: phpunit-test
description: Writes PHPUnit 9.6 tests under `tests/` in the `Detain\MyAdminSwift\Tests` namespace. Use when user says 'write tests', 'add test coverage', 'PHPUnit', or creates files in `tests/`. Covers structural assertions via ReflectionClass and anonymous-class stubs for dependency injection. Do NOT trigger for production code changes in `src/` or `bin/`.
---
# phpunit-test

## Critical

- Every test file MUST begin with `declare(strict_types=1);` — never omit it.
- Namespace is always `Detain\MyAdminSwift\Tests` — never use a sub-namespace.
- Test class MUST extend `PHPUnit\Framework\TestCase`.
- The `Swift` class lives in global namespace (no `use Swift;` needed; import as `use Swift;` only if needed for clarity).
- `tests/bootstrap.php` stubs `getcurlpage()` and `_()` — never add real HTTP calls to tests. If you need `getcurlpage()` to return something specific, override the stub by reassigning or wrapping in a subclass; do NOT mock with `$this->createMock()`.
- For dependencies that accept method calls (`add_requirement`, `add_text_setting`, etc.), use **anonymous class stubs** — not Mockery or PHPUnit mocks.
- Run `composer test` to verify all tests pass before finishing.

## Instructions

1. **Identify what to test.** Read the target class in `src/`. Determine: public method signatures, static vs instance, private properties, constructor initialisation, and any early-return guards (e.g. empty-string checks).
   - Verify the class exists and is loadable before writing tests.

2. **Create the test file** at the appropriate path under `tests/` (e.g., `tests/SwiftTest.php` or `tests/PluginTest.php`). Use this exact file header:
   ```php
   <?php

   declare(strict_types=1);

   namespace Detain\MyAdminSwift\Tests;

   use Detain\MyAdminSwift\Plugin; // swap for the class under test
   use PHPUnit\Framework\TestCase;
   use ReflectionClass;
   // add ReflectionMethod, ReflectionProperty only when used
   ```
   For the global `Swift` class, replace the `use` line with `use Swift;`.

3. **Write structural tests first** (do not skip these):
   - `testClassExists()` — `$this->assertTrue(class_exists(ClassName::class))`
   - `testCanBeInstantiated()` — `new ClassName(); $this->assertInstanceOf(...)`
   - `testClassNamespace()` — use `ReflectionClass::getNamespaceName()`
   - `testStaticPropertiesExist()` — iterate expected property names via `$ref->getStaticProperties()`
   - `testPublicMethodList()` — collect `$ref->getMethods(IS_PUBLIC)` filtered to `getDeclaringClass()->getName() === ClassName::class`, sort, assert exact list

4. **Write method-signature tests** for every public method:
   ```php
   public function testFooMethodSignature(): void
   {
       $ref = new ReflectionClass(Swift::class);
       $method = $ref->getMethod('foo');
       $params = $method->getParameters();

       $this->assertCount(2, $params);
       $this->assertSame('container', $params[0]->getName());
       $this->assertFalse($params[0]->isDefaultValueAvailable()); // required param
       $this->assertSame('', $params[1]->getDefaultValue());       // optional param
   }
   ```
   Verify static/public/instance as appropriate with `isStatic()`, `isPublic()`.

5. **Write property-access tests** using reflection for private properties:
   ```php
   $swift = new Swift();
   $ref   = new ReflectionClass($swift);
   $prop  = $ref->getProperty('storage_url');
   $prop->setAccessible(true);

   // read
   $this->assertNull($prop->getValue($swift));

   // write, then assert public getter
   $prop->setValue($swift, 'https://storage.example.com/v1/AUTH_test');
   $this->assertSame('https://storage.example.com/v1/AUTH_test', $swift->get_url());
   ```

6. **Write behaviour tests** using anonymous class stubs when a method calls methods on an injected subject:
   ```php
   $loader = new class {
       /** @var array */
       public $requirements = [];

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
   ```

7. **Add a `@dataProvider`** for methods that need multiple input variants. Provider methods MUST be `public static`:
   ```php
   /** @dataProvider urlProvider */
   public function testSetV1AuthUrlWithVariousFormats(string $url): void { ... }

   public static function urlProvider(): array
   {
       return [
           'https url'    => ['https://auth.example.com/auth/v1.0'],
           'empty string' => [''],
       ];
   }
   ```

8. **Add `@covers` docblock** to the class:
   ```php
   /**
    * @covers \Detain\MyAdminSwift\Plugin
    */
   class PluginTest extends TestCase
   ```
   For global classes: `@covers \Swift`.

9. **Run tests** and fix any failures:
   ```bash
   ./vendor/bin/phpunit tests/SwiftTest.php
   ```
   Confirm zero failures and zero errors before finishing.

## Examples

**User says:** "Write tests for the Plugin class."

**Actions taken:**
1. Read `src/Plugin.php` — note static properties `$name`, `$description`, `$help`, `$type`; static methods `getHooks()`, `getSettings(GenericEvent)`, `getRequirements(GenericEvent)`, `getMenu(GenericEvent)`.
2. Create `tests/PluginTest.php` with namespace `Detain\MyAdminSwift\Tests`, imports `Plugin`, `TestCase`, `ReflectionClass`, `GenericEvent`.
3. Structural tests: `testClassExists`, `testCanBeInstantiated`, `testClassNamespace`, `testStaticPropertiesExist`, `testPublicMethodList`.
4. Property value tests: `testNameProperty` → `$this->assertSame('Swift Plugin', Plugin::$name)`.
5. Hook tests: `testGetHooksReturnsArray`, `testGetHooksContainsSystemSettings`, `testSystemSettingsHookCallable` (assert `[Plugin::class, 'getSettings']`), `testGetHooksCount`.
6. Signature tests via `ReflectionClass` for `getSettings`, `getRequirements`, `getMenu` — assert 1 param named `event` typed `GenericEvent`.
7. Behaviour tests with anonymous stubs for `testGetRequirementsCallsAddRequirement` and `testGetSettingsCallsSettingsMethods`.
8. Run `./vendor/bin/phpunit tests/PluginTest.php` — all pass.

**Result:** `tests/PluginTest.php` — ~430 lines, 100% structural coverage.

## Common Issues

- **`Class 'Swift' not found`** — `tests/bootstrap.php` manually requires `src/Swift.php` outside of Composer autoloading. If you create a new test file for `Swift`, ensure `phpunit.xml.dist` (or your run command) uses `tests/bootstrap.php` as the bootstrap. Run: `./vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/SwiftTest.php`.

- **`Call to undefined function getcurlpage()`** — the stub in `tests/bootstrap.php` only loads when bootstrap runs. Confirm `phpunit.xml.dist` has `bootstrap="tests/bootstrap.php"`. If running ad-hoc, pass `--bootstrap tests/bootstrap.php`.

- **`Cannot access private property`** — call `$prop->setAccessible(true)` before `getValue()`/`setValue()`. PHPUnit 9.6 on PHP 8.1+ still requires this; it is not deprecated until PHP 8.2 and not removed.

- **`ReflectionException: Method foo does not exist`** — you are testing a method name that differs from the actual source. Run `grep -n 'public function' src/Swift.php` to get the exact names.

- **`Failed asserting that array has the key 'class.Swift'`** when testing `getRequirements` — verify your anonymous stub's `add_requirement` signature matches `(string $name, string $path)` exactly; a variadic signature will store args differently.

- **Data provider not found** — provider method must be `public static function` (not instance method). PHPUnit 9 will throw `InvalidArgumentException: Data provider ... is not static` otherwise.
