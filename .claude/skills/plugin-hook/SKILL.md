---
name: plugin-hook
description: Adds or modifies event hooks in `src/Plugin.php` using Symfony `GenericEvent` and the `getHooks()` registry. Covers `system.settings` text/password settings and `function.requirements` loader patterns. Use when user says 'add setting', 'register hook', 'new plugin event', or edits `src/Plugin.php`. Do NOT use for `src/Swift.php` or `bin/` changes.
---
# Plugin Hook

## Critical

- All hook handler methods MUST be `public static function methodName(GenericEvent $event): void` — never instance methods
- Every new event key in `getHooks()` MUST have a corresponding handler method in the class
- Always use `__CLASS__` (not the literal class name string) in `getHooks()` callables
- Wrap all i18n strings in `_()`: `_('Backups')`, `_('Swift Auth URL')`, etc.
- `getHooks()` count must stay in sync — `PluginTest::testGetHooksCount()` asserts the exact count; update the test when adding hooks

## Instructions

1. **Open `src/Plugin.php`** and confirm the file header:
   ```php
   <?php
   namespace Detain\MyAdminSwift;
   use Symfony\Component\EventDispatcher\GenericEvent;
   ```
   Verify the `use` statement is present before proceeding.

2. **Register the new hook in `getHooks()`** by adding an entry to the returned array:
   ```php
   public static function getHooks()
   {
       return [
           'system.settings'       => [__CLASS__, 'getSettings'],
           'function.requirements' => [__CLASS__, 'getRequirements'],
           'your.event.name'       => [__CLASS__, 'yourHandlerMethod'], // add here
       ];
   }
   ```
   Verify the key follows the `noun.verb` dot-notation convention used by existing hooks.

3. **Add the handler method** immediately after the last handler in the file, before the closing `}`:
   ```php
   /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
   public static function yourHandlerMethod(GenericEvent $event)
   {
       $subject = $event->getSubject();
       // handler body here
   }
   ```
   The parameter type hint must be `GenericEvent` (imported via `use` at the top).

4. **For `system.settings` additions** — call methods on `$settings = $event->getSubject()`:
   - Text field: `$settings->add_text_setting(_('Group'), _('Subgroup'), 'setting_key', _('Label'), _('Description'), CONSTANT_DEFAULT);`
   - Password field: `$settings->add_password_setting(_('Group'), _('Subgroup'), 'setting_key', _('Label'), _('Description'), CONSTANT_DEFAULT);`

   Full example matching the existing pattern:
   ```php
   public static function getSettings(GenericEvent $event)
   {
       /** @var \MyAdmin\Settings $settings **/
       $settings = $event->getSubject();
       $settings->add_text_setting(_('Backups'), _('Swift'), 'swift_auth_url', _('Swift Auth URL'), _('Swift Auth URL'), SWIFT_AUTH_URL);
       $settings->add_text_setting(_('Backups'), _('Swift'), 'swift_auth_v1_url', _('Swift Auth v1 URL'), _('Swift Auth v1 URL'), SWIFT_AUTH_V1_URL);
       $settings->add_text_setting(_('Backups'), _('Swift'), 'swift_admin_user', _('Swift Admin User'), _('Swift Admin User'), SWIFT_ADMIN_USER);
       $settings->add_password_setting(_('Backups'), _('Swift'), 'swift_admin_key', _('Swift Admin Key'), _('Swift Admin Key'), SWIFT_ADMIN_KEY);
   }
   ```
   Verify: 3 text settings + 1 password setting = `testGetSettingsCallsSettingsMethods()` asserts exactly these counts.

5. **For `function.requirements` additions** — call `add_requirement` on `$loader = $event->getSubject()`:
   ```php
   public static function getRequirements(GenericEvent $event)
   {
       /** @var \MyAdmin\Plugins\Loader $this->loader */
       $loader = $event->getSubject();
       $loader->add_requirement('class.Swift', '');
   }
   ```
   Path format: `'/../vendor/detain/PACKAGE-NAME/src/ClassName.php'`. Key format: `'class.ClassName'`.

6. **Update `tests/PluginTest.php`** when the hook count or setting counts change:
   - Hook count: `$this->assertCount(N, $hooks)` in `testGetHooksCount()`
   - Settings count: `$this->assertCount(N, $settings->textSettings)` / `$this->assertCount(N, $settings->passwordSettings)` in `testGetSettingsCallsSettingsMethods()`
   - Add a `testGetHooksContainsYourEvent()` test asserting `assertArrayHasKey('your.event.name', $hooks)`
   - Add a `testYourHandlerMethodIsStatic()` test using `ReflectionClass`

7. **Run tests** to confirm nothing is broken:
   ```bash
   vendor/bin/phpunit tests/PluginTest.php
   ```
   All tests must pass before committing.

## Examples

**User says:** "Add a new text setting for swift timeout"

**Actions taken:**
1. `getSettings()` already exists — add one line inside it:
   ```php
   $settings->add_text_setting(_('Backups'), _('Swift'), 'swift_timeout', _('Swift Timeout'), _('Swift connection timeout in seconds'), SWIFT_TIMEOUT);
   ```
2. Update `testGetSettingsCallsSettingsMethods()` count: `assertCount(4, $settings->textSettings)`
3. Define `SWIFT_TIMEOUT` in `tests/bootstrap.php` if not already defined
4. Run `vendor/bin/phpunit tests/PluginTest.php`

**User says:** "Register a new hook for system.startup"

**Actions taken:**
1. Add to `getHooks()`:
   ```php
   'system.startup' => [__CLASS__, 'onStartup'],
   ```
2. Add handler:
   ```php
   public static function onStartup(GenericEvent $event)
   {
       $subject = $event->getSubject();
       // startup logic
   }
   ```
3. Update `testGetHooksCount()`: `assertCount(3, $hooks)`
4. Add `testGetHooksContainsSystemStartup()` and `testOnStartupMethodIsStatic()` to `tests/PluginTest.php`
5. Run `vendor/bin/phpunit tests/PluginTest.php`

## Common Issues

**`testGetHooksCount` fails with "Failed asserting that 3 matches expected 2":**
You added a hook to `getHooks()` but did not update the assertion in `PluginTest::testGetHooksCount()`. Change `assertCount(2, $hooks)` to match the new total.

**`testGetSettingsCallsSettingsMethods` fails with wrong count:**
You added or removed a setting call in `getSettings()` without updating `assertCount(N, $settings->textSettings)` or `assertCount(N, $settings->passwordSettings)` in the test.

**`Call to undefined function _()`  during tests:**
The `_()` stub must be defined in `tests/bootstrap.php`. Verify it contains:
```php
function _($str) { return $str; }
```

**`Class 'Symfony\Component\EventDispatcher\GenericEvent' not found`:**
Run `composer install` — the Symfony EventDispatcher dependency is missing from `vendor/`.

**Handler method not called at runtime (hook registered but ignored):**
Verify the event name in `getHooks()` exactly matches the string passed to `run_event()` in the MyAdmin core. Dot-notation is case-sensitive (`system.settings` ≠ `System.Settings`).

**`ReflectionMethod` test fails with "Method does not exist":**
You registered `[__CLASS__, 'methodName']` in `getHooks()` but forgot to add the actual `methodName` method to the class body.
