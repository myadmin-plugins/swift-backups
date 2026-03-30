# MyAdmin Swift Backups Plugin

OpenStack Swift object storage plugin for the [MyAdmin](https://github.com/detain/myadmin) control panel. Package: `detain/myadmin-swift-backups`.

## Commands

```bash
composer install                          # install deps
vendor/bin/phpunit                        # run all tests
vendor/bin/phpunit tests/SwiftTest.php   # run Swift class tests only
vendor/bin/phpunit tests/PluginTest.php  # run Plugin tests only
```

## Architecture

**Core classes** (`src/`):
- `src/Swift.php` — `Swift` class (no namespace); wraps all Swift API calls via `getcurlpage()`
- `src/Plugin.php` — `Detain\MyAdminSwift\Plugin`; registers hooks + settings with MyAdmin

**Bin scripts** (`bin/`) — standalone CLI tools, all start with:
```php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$sw = new Swift();
```

**Tests** (`tests/`):
- `tests/bootstrap.php` — defines constants (`SWIFT_AUTH_URL`, `SWIFT_AUTH_V1_URL`, `SWIFT_ADMIN_USER`, `SWIFT_ADMIN_KEY`) and stubs `getcurlpage()` and `_()`
- `tests/PluginTest.php` — full reflection-based coverage of `Plugin`
- `tests/SwiftTest.php` — Swift class tests
- `phpunit.xml.dist` — PHPUnit 9.6 config

**Autoloading** (`composer.json`):
- `Detain\MyAdminSwift\` → `src/`
- `Detain\MyAdminSwift\Tests\` → `tests/`

**CI/CD** (`.github/`): GitHub Actions workflows for automated testing and deployment pipelines — see `.github/workflows/tests.yml`.

**IDE** (`.idea/`): PhpStorm project configuration — `inspectionProfiles/` for code inspection rules, `deployment.xml` for remote server sync, `encodings.xml` for file encoding settings.

## Swift Class Patterns

All HTTP operations use `getcurlpage($url, $params, $options)` with cURL option arrays:

```php
$options = [
    CURLOPT_HTTPGET    => true,
    CURLOPT_HTTPHEADER => ['X-Auth-Token:' . $this->storage_token],
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
];
$response = getcurlpage($this->storage_url . '/' . $container, '', $options);
```

- Always set `CURLOPT_SSL_VERIFYHOST => false` and `CURLOPT_SSL_VERIFYPEER => false`
- Auth token passed as `'X-Auth-Token:' . $this->storage_token` (no space after colon)
- `ls()` paginates at 10 000 entries using `?marker=` query param
- `authenticate()` retries up to `$retry` times (default 10), parses `X-Storage-Url` and `X-Storage-Token` from response headers
- `usage()` and `acl()` filter out `Accept-Ranges`, `X-Trans-Id`, `Date` from response headers

## Plugin Hook Pattern

`Plugin::getHooks()` returns event → `[ClassName, 'methodName']` pairs:

```php
public static function getHooks() {
    return [
        'system.settings'      => [__CLASS__, 'getSettings'],
        'function.requirements' => [__CLASS__, 'getRequirements'],
    ];
}
```

Settings registration in `getSettings(GenericEvent $event)`:
```php
$settings = $event->getSubject();
$settings->add_text_setting(_('Backups'), _('Swift'), 'swift_auth_url', _('Swift Auth URL'), _('Swift Auth URL'), SWIFT_AUTH_URL);
$settings->add_password_setting(_('Backups'), _('Swift'), 'swift_admin_key', _('Swift Admin Key'), _('Swift Admin Key'), SWIFT_ADMIN_KEY);
```

Plugin requirements in `getRequirements(GenericEvent $event)`:
```php
$loader = $event->getSubject();
$loader->add_requirement('class.Swift', '');
```

## Constants

Defined by MyAdmin host app; stubbed in `tests/bootstrap.php` for testing:
- `SWIFT_AUTH_URL` — v2 auth endpoint
- `SWIFT_AUTH_V1_URL` — v1 auth endpoint (used by `authenticate()`)
- `SWIFT_ADMIN_USER` / `SWIFT_ADMIN_KEY` — admin credentials
- `SWIFT_OPENVZ_USER` / `SWIFT_OPENVZ_PASS` — per-repo credentials used in `bin/` scripts
- `SWIFT_KVM_USER` / `SWIFT_KVM_PASS`
- `SWIFT_MY_USER` / `SWIFT_MY_PASS`
- `SWIFT_WEBHOSTING_USER` / `SWIFT_WEBHOSTING_PASS`

## Test Conventions

- Namespace: `Detain\MyAdminSwift\Tests`
- Extend `PHPUnit\Framework\TestCase`
- Test methods named `test<What><Condition>()` — e.g. `testGetHooksReturnsArray()`
- Use anonymous classes to stub dependencies (see `testGetRequirementsCallsAddRequirement()` in `tests/PluginTest.php`)
- `declare(strict_types=1)` at top of every test file
- `tests/bootstrap.php` must stub all constants and `getcurlpage()` before class load

## Coding Conventions

- Tabs for indentation (enforced by `.scrutinizer.yml`)
- `camelCase` for properties and parameters
- No closing `?>` tags
- Commit messages: lowercase, descriptive (`fix authentication retry`, `add download passthrough`)
- `_('string')` for all user-visible strings (gettext)
- Never commit credentials — `SWIFT_*` constants come from host app config

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md .agents/ .opencode/ 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->

<!-- caliber:managed:sync -->
## Context Sync

This project uses [Caliber](https://github.com/caliber-ai-org/ai-setup) to keep AI agent configs in sync across Claude Code, Cursor, Copilot, and Codex.
Configs update automatically before each commit via `caliber refresh`.
If the pre-commit hook is not set up, run `/setup-caliber` to configure everything automatically.
<!-- /caliber:managed:sync -->
