---
name: bin-script
description: Creates a new CLI script in `bin/` following the bootstrap + `function_requirements('class.Swift')` + `new Swift()` pattern. Handles auth via `$sw->authenticate()`, argument parsing via `$_SERVER['argv']`, and output via `echo`/`print_r`. Use when user says 'add bin script', 'new CLI tool', 'command line utility', or adds files to `bin/`. Do NOT use for src/ class changes or Plugin.php modifications.
---
# bin-script

## Critical

- **Always** start with the exact 4-line bootstrap — never skip or alter the `require_once` path or `function_requirements` call.
- **Never** use `namespace` declarations in `bin/` scripts — they are procedural, not class-based.
- **Never** use `new Swift()` before `function_requirements('class.Swift')` — the class won't exist yet.
- When calling `$sw->authenticate()`, always check for `=== false` and `exit` on failure before any Swift API calls that need a storage token.
- All argument parsing uses `$_SERVER['argc']` and `$_SERVER['argv']` — never `$argv`/`$argc` directly.
- Output goes to `echo` or `print_r()` — no return values, no JSON responses.

## Instructions

1. **Create the file** in the `bin/` directory (e.g., `bin/ls.php`) using the mandatory shebang + bootstrap header:
   ```php
   #!/usr/bin/env php
   <?php
   require_once __DIR__.'/../../../../include/functions.inc.php';
   function_requirements('class.Swift');
   $sw = new Swift();
   ```
   Verify the four lines are present and unmodified before proceeding.

2. **Parse arguments** (if the script takes parameters) immediately after `new Swift()`, before any auth call:
   - For a required single arg:
     ```php
     if ($_SERVER['argc'] < 2) {
         die("Syntax {$_SERVER['argv'][0]} <name>\n");
     }
     ```
   - For multiple required args:
     ```php
     if ($_SERVER['argc'] < 3) {
         die("Syntax {$_SERVER['argv'][0]} <account> <user>\n");
     }
     ```
   - For optional flags (e.g. `-v`):
     ```php
     $verbose = 0;
     for ($x = 1; $x < $_SERVER['argc']; $x++) {
         if (in_array($_SERVER['argv'][$x], ['-v', '-vv', '-vvv'])) {
             $verbose += strlen($_SERVER['argv'][$x]) - 1;
         } elseif ($_SERVER['argv'][$x] === '-h') {
             die("Syntax {$_SERVER['argv'][0]} [-v] <arg>\n");
         }
     }
     ```
   Verify `$_SERVER['argc']` threshold matches the number of required positional args.

3. **Authenticate** when the operation needs a storage token (i.e. calls `ls()`, `usage()`, `acl()`, or object-level methods). Use one of the named credential constant pairs:
   ```php
   $response = $sw->authenticate(SWIFT_OPENVZ_USER, SWIFT_OPENVZ_PASS);
   if ($response === false) {
       echo "Problems\n";
       exit;
   }
   ```
   Available credential constant pairs: `SWIFT_OPENVZ_USER`/`SWIFT_OPENVZ_PASS`, `SWIFT_KVM_USER`/`SWIFT_KVM_PASS`, `SWIFT_MY_USER`/`SWIFT_MY_PASS`.
   Skip this step for admin-API-only scripts (e.g. `list_accounts()`, `list_account()`, `list_user()`) — those use the admin credentials baked into `Swift::__construct()`.

4. **Implement the operation** using Swift methods. Common patterns:
   - List containers: `echo $sw->ls();`
   - List objects in container: `echo $sw->ls($_SERVER['argv'][1]);`
   - Show account/container/object usage: `print_r($sw->usage($container));`
   - Admin listing: `print_r($sw->list_accounts());`
   - Admin account detail: `print_r($sw->list_account($_SERVER['argv'][1]));`
   - Admin user detail: `print_r($sw->list_user($_SERVER['argv'][1], $_SERVER['argv'][2]));`
   - Loop multiple repos:
     ```php
     $repos = [
         ['username' => SWIFT_OPENVZ_USER, 'password' => SWIFT_OPENVZ_PASS],
         ['username' => SWIFT_KVM_USER,    'password' => SWIFT_KVM_PASS],
     ];
     foreach ($repos as $repo) {
         $response = $sw->authenticate($repo['username'], $repo['password']);
         // ... per-repo logic
     }
     ```

5. **Add DB queries** only if the script cross-references MyAdmin service data. Use the standard pattern:
   ```php
   $module = 'vps';
   $settings = \get_module_settings($module);
   $db = get_module_db($module);
   $db->query("SELECT {$settings['PREFIX']}_id, {$settings['PREFIX']}_hostname FROM "
       . $settings['TABLE']
       . " WHERE {$settings['PREFIX']}_status != 'pending' AND {$settings['PREFIX']}_server_status != 'deleted'",
       __LINE__, __FILE__);
   while ($db->next_record(MYSQL_ASSOC)) {
       $data[$db->Record[$settings['PREFIX'].'_id']] = $db->Record;
   }
   ```
   Always pass `__LINE__, __FILE__` to `$db->query()`. Never use PDO.

6. **Log unexpected data** with `myadmin_log()` — never use `error_log()` or exceptions:
   ```php
   myadmin_log('scripts', 'info', var_export($usage, true), __LINE__, __FILE__, $module);
   ```

7. **Make the file executable:**
   ```bash
   chmod +x bin/ls.php
   ```
   Verify with `ls -la bin/` — confirm the new script shows `-rwxr-xr-x`.

8. **Run the script** from the repo root to confirm no parse errors:
   ```bash
   vendor/bin/phpunit tests/SwiftTest.php
   ```
   For a quick syntax check without a live Swift instance: `php -l bin/ls.php`.

## Examples

**User says:** "Add a bin script to show usage for a single container passed as an argument"

**Actions taken:**
1. Create `bin/usage.php`
2. Add bootstrap header
3. Parse one required arg (`$_SERVER['argc'] < 2`)
4. Authenticate with `SWIFT_OPENVZ_USER`/`SWIFT_OPENVZ_PASS`, check `=== false`
5. Call `print_r($sw->usage($_SERVER['argv'][1]))`
6. `chmod +x bin/usage.php`

**Result:**
```php
#!/usr/bin/env php
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$sw = new Swift();
if ($_SERVER['argc'] < 2) {
    die("Syntax {$_SERVER['argv'][0]} <container>\n");
}
$response = $sw->authenticate(SWIFT_OPENVZ_USER, SWIFT_OPENVZ_PASS);
if ($response === false) {
    echo "Problems\n";
    exit;
}
print_r($sw->usage($_SERVER['argv'][1]));
```

---

**User says:** "Add a bin script that lists all accounts without auth"

**Result** (`bin/list_accounts.php` pattern — no authenticate call needed):
```php
#!/usr/bin/env php
<?php
require_once __DIR__.'/../../../../include/functions.inc.php';
function_requirements('class.Swift');
$sw = new Swift();
print_r($sw->list_accounts());
```

## Common Issues

- **`Fatal error: Class 'Swift' not found`** — `function_requirements('class.Swift')` is missing or placed after `new Swift()`. Move it to line 4, before instantiation.

- **`require_once(...): failed to open stream`** — the script is not inside `bin/` at depth 4 under the MyAdmin root. Verify the path `__DIR__.'/../../../../include/functions.inc.php'` resolves correctly: `realpath(__DIR__.'/../../../../include/functions.inc.php')` should point to the MyAdmin `include/` directory.

- **`Undefined constant SWIFT_OPENVZ_USER`** — constants are loaded by `include/functions.inc.php` from settings. Running outside MyAdmin context (e.g. bare `php bin/ls.php`) will fail. In tests, these are stubbed in `tests/bootstrap.php`.

- **`$sw->ls()` returns empty / auth errors after looping repos** — `authenticate()` mutates `$sw->storage_url` and `$sw->storage_token`. Each repo iteration must call `$sw->authenticate(...)` before any `ls()`/`usage()` calls for that repo.

- **`Permission denied` when running script**  — run `chmod +x` on the script file. The shebang line (`#!/usr/bin/env php`) only works if the file is executable.

- **`Undefined index: Content-Length` in usage loop** — `$sw->usage()` filters out some headers but `Content-Length` may be absent for pseudo-directories. Guard with `isset($usage['Content-Length'])` before arithmetic operations.
