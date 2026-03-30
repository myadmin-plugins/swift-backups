---
name: swift-api-method
description: Adds a new method to `src/Swift.php` following the cURL options pattern. Sets up `CURLOPT_HTTPHEADER` with `X-Auth-Token`, disables SSL verify, calls `getcurlpage()`, and parses the response. Use when user says 'add Swift method', 'new container operation', 'add endpoint to Swift', or modifies `src/Swift.php`. Do NOT use for bin scripts or Plugin hook changes.
---
# swift-api-method

## Critical

- `Swift` is a **global class** (no namespace) in `src/Swift.php` — never add a namespace declaration
- **Never** call `curl_*` directly — always use `getcurlpage($url, $params, $options)`
- **Always** set both `CURLOPT_SSL_VERIFYHOST => false` and `CURLOPT_SSL_VERIFYPEER => false` in every `$options` array
- **Always** format the auth header as `'X-Auth-Token:'.$this->storage_token` — no space after the colon
- All methods must be `public` and non-`static`
- `authenticate()` must be called before any method that uses `$this->storage_token` or `$this->storage_url`

## Instructions

1. **Identify the HTTP verb and response shape** for the new operation:
   - GET (read body): use `CURLOPT_HTTPGET => true`
   - GET (headers only): add `CURLOPT_HEADER => true, CURLOPT_NOBODY => true`
   - PUT (create/update): use `CURLOPT_CUSTOMREQUEST => 'PUT'`
   - DELETE: use `CURLOPT_CUSTOMREQUEST => 'DELETE'`
   - Response types seen in the codebase: raw string, header-parsed array, or `json_decode()`

   Verify: confirm which verb and response shape match the OpenStack Swift API docs for this operation.

2. **Build the `$options` array** using this exact structure — always include all three required keys:

   ```php
   $options = [
       CURLOPT_HTTPHEADER     => ['X-Auth-Token:'.$this->storage_token],
       CURLOPT_SSL_VERIFYHOST => false,
       CURLOPT_SSL_VERIFYPEER => false,
   ];
   ```

   Add verb-specific keys after the three required ones:
   - For GET body: prepend `CURLOPT_HTTPGET => true`
   - For GET headers: prepend `CURLOPT_HEADER => true, CURLOPT_NOBODY => true`
   - For PUT/DELETE: append `CURLOPT_CUSTOMREQUEST => 'PUT'` or `'DELETE'`
   - For extra headers (e.g. ACL, ETag): append to the `CURLOPT_HTTPHEADER` array: `$options[CURLOPT_HTTPHEADER][] = 'Header-Name: '.$value;`

   Verify: every `$options` array in the method has `CURLOPT_HTTPHEADER`, `CURLOPT_SSL_VERIFYHOST`, and `CURLOPT_SSL_VERIFYPEER`.

3. **Build the URL** by concatenating `$this->storage_url` and the path components:

   ```php
   // Container only
   $url = $this->storage_url.'/'.$container;
   // Container + object
   $url = $this->storage_url.'/'.$container.'/'.$object;
   // With query param (e.g. pagination marker)
   $url = $this->storage_url.'/'.$container.'?marker='.$marker;
   ```

   Verify: no trailing `/` is added unless the Swift API requires it.

4. **Call `getcurlpage()` and handle the response** based on step 1's response shape:

   **Raw string** (e.g. `download`, `ls`, `upload`, `delete`):
   ```php
   $response = getcurlpage($url, '', $options);
   return $response;
   ```

   **Header-parsed array** (e.g. `usage`, `acl`) — filter out noise headers:
   ```php
   $response = getcurlpage($url, '', $options);
   preg_match_all('/^(.*): (.*)$/m', $response, $matches);
   $response = [];
   foreach ($matches[1] as $idx => $key) {
       if (!in_array($key, ['Accept-Ranges', 'X-Trans-Id', 'Date'])) {
           $response[$key] = trim($matches[2][$idx]);
       }
   }
   return $response;
   ```

   **JSON** (e.g. `list_accounts`, `list_account`, `list_user`) — uses `$this->auth_url` not `$this->storage_url`:
   ```php
   $response = getcurlpage($this->auth_url.$path, $this->args, $this->options);
   return json_decode($response, true);
   ```

   **Paginated listing** (e.g. `ls`) — loop while response has 10 000 entries:
   ```php
   $response = trim(getcurlpage($url, '', $options));
   $return = $response;
   $lines = explode("\n", $response);
   while (count($lines) == 10000) {
       $response = trim(getcurlpage($url.'?marker='.$lines[9999], '', $options));
       $return .= "\n".$response;
       $lines = explode("\n", $response);
   }
   return $return;
   ```

5. **Add a PHPDoc block** above the method using the style from existing methods:

   ```php
   /**
    * @param string $container
    * @param string $object
    * @return array|string
    */
   ```

   Verify: `@param` types match PHP primitives only (`string`, `bool`, `int`); `@return` matches actual return type.

6. **Add a method-signature test** in `tests/SwiftTest.php` following the reflection pattern used for existing methods:

   ```php
   public function testMyNewMethodSignature(): void
   {
       $ref = new ReflectionClass(Swift::class);
       $method = $ref->getMethod('my_new_method');
       $params = $method->getParameters();

       $this->assertTrue($method->isPublic());
       $this->assertFalse($method->isStatic());
       $this->assertCount(2, $params);  // adjust to actual param count
       $this->assertSame('container', $params[0]->getName());
       $this->assertFalse($params[0]->isDefaultValueAvailable());
   }
   ```

   Also add the new method name to the `$expected` array in `testExpectedPublicMethodsExist()`.

   Verify: run `vendor/bin/phpunit tests/SwiftTest.php` — all tests pass.

## Examples

**User says:** "Add a method to copy an object from one container to another using a Swift server-side COPY."

**Actions taken:**
1. HTTP verb is `COPY` (custom request), response is a raw string.
2. Build `$options` with `CURLOPT_CUSTOMREQUEST => 'COPY'` and a `Destination` header.
3. URL is `$this->storage_url.'/'.$src_container.'/'.$src_object`.
4. Return raw response string.

**Result added to `src/Swift.php`** (before the closing `}`):

```php
/**
 * @param string $src_container
 * @param string $src_object
 * @param string $dest_container
 * @param string $dest_object
 * @return string
 */
public function copy($src_container, $src_object, $dest_container, $dest_object)
{
    $options = [
        CURLOPT_HTTPHEADER => [
            'X-Auth-Token:'.$this->storage_token,
            'Destination: '.$dest_container.'/'.$dest_object,
        ],
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'COPY',
    ];
    $response = getcurlpage($this->storage_url.'/'.$src_container.'/'.$src_object, '', $options);
    return $response;
}
```

**Test added to `tests/SwiftTest.php`:**

```php
public function testCopyMethodSignature(): void
{
    $ref = new ReflectionClass(Swift::class);
    $method = $ref->getMethod('copy');
    $params = $method->getParameters();

    $this->assertTrue($method->isPublic());
    $this->assertFalse($method->isStatic());
    $this->assertCount(4, $params);
    $this->assertSame('src_container', $params[0]->getName());
    $this->assertSame('src_object', $params[1]->getName());
    $this->assertSame('dest_container', $params[2]->getName());
    $this->assertSame('dest_object', $params[3]->getName());
}
```

Run `vendor/bin/phpunit tests/SwiftTest.php` to confirm.

## Common Issues

- **`getcurlpage()` is undefined at runtime:** You are running the method outside the MyAdmin environment. The function is defined in `include/functions.inc.php`. In tests, it is stubbed in `tests/bootstrap.php` — ensure `vendor/bin/phpunit` is run with the `phpunit.xml.dist` config (it sets the bootstrap automatically).

- **SSL errors / empty response from `getcurlpage()`:** Missing `CURLOPT_SSL_VERIFYHOST => false` or `CURLOPT_SSL_VERIFYPEER => false` in `$options`. Check every key in the array — both must be present.

- **`X-Auth-Token` rejected (401):** `authenticate()` was not called before the method, so `$this->storage_token` is `null`. Call `$sw->authenticate()` first and verify it returns a non-`false` value.

- **Header-parsed response returns empty array:** The `preg_match_all` pattern `/^(.*): (.*)$/m` requires the response to include headers (`CURLOPT_HEADER => true`). If you omitted that flag and only get a body, add it to the `$options` array.

- **`testExpectedPublicMethodsExist` fails after adding your method:** You added the method to `src/Swift.php` but forgot to add its name to the `$expected` array in `tests/SwiftTest.php:452`. Add the method name string there.

- **`testPrivatePropertyCount` fails:** You added a new property to the class. Update the assertion in `tests/SwiftTest.php:517` from `assertCount(8, ...)` to the new count.