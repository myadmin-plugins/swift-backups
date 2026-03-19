<?php

declare(strict_types=1);

namespace Detain\MyAdminSwift\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Swift;

/**
 * Tests for the Swift class.
 *
 * Covers class structure, property definitions, method signatures,
 * and behaviour that can be verified without external dependencies.
 * Methods that call getcurlpage() are tested via static analysis only.
 *
 * @covers \Swift
 */
class SwiftTest extends TestCase
{
    /**
     * Test that the Swift class exists.
     *
     * @return void
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Swift::class));
    }

    /**
     * Test that the Swift class is not in any namespace (global class).
     *
     * @return void
     */
    public function testClassIsInGlobalNamespace(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $this->assertSame('', $ref->getNamespaceName());
    }

    /**
     * Test that all expected private properties exist.
     *
     * @return void
     */
    public function testExpectedPrivatePropertiesExist(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $expected = [
            'auth_url',
            'v1_auth_url',
            'admin_user',
            'auth_key',
            'storage_url',
            'storage_token',
            'options',
            'args',
        ];

        foreach ($expected as $prop) {
            $this->assertTrue(
                $ref->hasProperty($prop),
                "Expected private property \${$prop} not found"
            );
            $this->assertTrue(
                $ref->getProperty($prop)->isPrivate(),
                "Property \${$prop} should be private"
            );
        }
    }

    /**
     * Test that the constructor initializes options and args via reflection.
     *
     * @return void
     */
    public function testConstructorInitializesOptionsAndArgs(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $optionsProp = $ref->getProperty('options');
        $optionsProp->setAccessible(true);
        $options = $optionsProp->getValue($swift);

        $argsProp = $ref->getProperty('args');
        $argsProp->setAccessible(true);
        $args = $argsProp->getValue($swift);

        $this->assertIsArray($options);
        $this->assertIsArray($args);
        $this->assertEmpty($args);
        $this->assertArrayHasKey(CURLOPT_HTTPGET, $options);
        $this->assertTrue($options[CURLOPT_HTTPGET]);
        $this->assertArrayHasKey(CURLOPT_SSL_VERIFYHOST, $options);
        $this->assertFalse($options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertArrayHasKey(CURLOPT_SSL_VERIFYPEER, $options);
        $this->assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
    }

    /**
     * Test that constructor sets CURLOPT_HTTPHEADER with admin credentials.
     *
     * @return void
     */
    public function testConstructorSetsAuthHeaders(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $optionsProp = $ref->getProperty('options');
        $optionsProp->setAccessible(true);
        $options = $optionsProp->getValue($swift);

        $this->assertArrayHasKey(CURLOPT_HTTPHEADER, $options);
        $this->assertIsArray($options[CURLOPT_HTTPHEADER]);
        $this->assertCount(2, $options[CURLOPT_HTTPHEADER]);

        $this->assertStringContainsString('X-Auth-Admin-User:', $options[CURLOPT_HTTPHEADER][0]);
        $this->assertStringContainsString('X-Auth-Admin-Key:', $options[CURLOPT_HTTPHEADER][1]);
    }

    /**
     * Test that get_url returns null/false before authentication.
     *
     * @return void
     */
    public function testGetUrlBeforeAuthentication(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $prop = $ref->getProperty('storage_url');
        $prop->setAccessible(true);

        // storage_url is not initialized in constructor, so it should be null
        $this->assertNull($prop->getValue($swift));
        $this->assertNull($swift->get_url());
    }

    /**
     * Test that get_token returns null before authentication.
     *
     * @return void
     */
    public function testGetTokenBeforeAuthentication(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $prop = $ref->getProperty('storage_token');
        $prop->setAccessible(true);

        $this->assertNull($prop->getValue($swift));
        $this->assertNull($swift->get_token());
    }

    /**
     * Test that get_url returns the storage_url value via reflection.
     *
     * @return void
     */
    public function testGetUrlReturnsStorageUrl(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $prop = $ref->getProperty('storage_url');
        $prop->setAccessible(true);
        $prop->setValue($swift, 'https://storage.example.com/v1/AUTH_test');

        $this->assertSame('https://storage.example.com/v1/AUTH_test', $swift->get_url());
    }

    /**
     * Test that get_token returns the storage_token value via reflection.
     *
     * @return void
     */
    public function testGetTokenReturnsStorageToken(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $prop = $ref->getProperty('storage_token');
        $prop->setAccessible(true);
        $prop->setValue($swift, 'AUTH_tk12345abcde');

        $this->assertSame('AUTH_tk12345abcde', $swift->get_token());
    }

    /**
     * Test that set_v1_auth_url updates the v1_auth_url property.
     *
     * @return void
     */
    public function testSetV1AuthUrlUpdatesProperty(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $prop = $ref->getProperty('v1_auth_url');
        $prop->setAccessible(true);

        $newUrl = 'https://new-auth.example.com/auth/v1.0';
        $swift->set_v1_auth_url($newUrl);

        $this->assertSame($newUrl, $prop->getValue($swift));
    }

    /**
     * Test that acl returns false when container is empty string.
     *
     * @return void
     */
    public function testAclReturnsFalseForEmptyContainer(): void
    {
        $swift = new Swift();
        $this->assertFalse($swift->acl(''));
    }

    /**
     * Test that acl returns false when container is whitespace only.
     *
     * @return void
     */
    public function testAclReturnsFalseForWhitespaceContainer(): void
    {
        $swift = new Swift();
        $this->assertFalse($swift->acl('   '));
    }

    /**
     * Test that the authenticate method exists with correct signature.
     *
     * @return void
     */
    public function testAuthenticateMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('authenticate');

        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());

        $params = $method->getParameters();
        $this->assertCount(3, $params);

        $this->assertSame('username', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertFalse($params[0]->getDefaultValue());

        $this->assertSame('password', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertFalse($params[1]->getDefaultValue());

        $this->assertSame('retry', $params[2]->getName());
        $this->assertTrue($params[2]->isDefaultValueAvailable());
        $this->assertSame(10, $params[2]->getDefaultValue());
    }

    /**
     * Test that the acl method has the correct signature.
     *
     * @return void
     */
    public function testAclMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('acl');
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('container', $params[0]->getName());
        $this->assertSame('', $params[0]->getDefaultValue());
        $this->assertSame('read', $params[1]->getName());
        $this->assertSame('', $params[1]->getDefaultValue());
        $this->assertSame('write', $params[2]->getName());
        $this->assertSame('', $params[2]->getDefaultValue());
    }

    /**
     * Test that the usage method has a container parameter with default empty string.
     *
     * @return void
     */
    public function testUsageMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('usage');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('container', $params[0]->getName());
        $this->assertSame('', $params[0]->getDefaultValue());
    }

    /**
     * Test that the download method has a required container parameter.
     *
     * @return void
     */
    public function testDownloadMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('download');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('container', $params[0]->getName());
        $this->assertFalse($params[0]->isDefaultValueAvailable());
    }

    /**
     * Test that the download_passthrough method has a container parameter with default.
     *
     * @return void
     */
    public function testDownloadPassthroughMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('download_passthrough');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('container', $params[0]->getName());
        $this->assertSame('', $params[0]->getDefaultValue());
    }

    /**
     * Test that the upload method requires container and filename.
     *
     * @return void
     */
    public function testUploadMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('upload');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame('container', $params[0]->getName());
        $this->assertFalse($params[0]->isDefaultValueAvailable());
        $this->assertSame('filename', $params[1]->getName());
        $this->assertFalse($params[1]->isDefaultValueAvailable());
    }

    /**
     * Test that the delete method requires a container parameter.
     *
     * @return void
     */
    public function testDeleteMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('delete');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('container', $params[0]->getName());
        $this->assertFalse($params[0]->isDefaultValueAvailable());
    }

    /**
     * Test that the ls method has a container parameter with default empty string.
     *
     * @return void
     */
    public function testLsMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('ls');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('container', $params[0]->getName());
        $this->assertSame('', $params[0]->getDefaultValue());
    }

    /**
     * Test that the ls_header method has a container parameter with default empty string.
     *
     * @return void
     */
    public function testLsHeaderMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('ls_header');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('container', $params[0]->getName());
        $this->assertSame('', $params[0]->getDefaultValue());
    }

    /**
     * Test that list_accounts takes no parameters.
     *
     * @return void
     */
    public function testListAccountsMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('list_accounts');
        $this->assertCount(0, $method->getParameters());
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test that list_account requires an account parameter.
     *
     * @return void
     */
    public function testListAccountMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('list_account');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('account', $params[0]->getName());
        $this->assertFalse($params[0]->isDefaultValueAvailable());
    }

    /**
     * Test that list_user requires account and user parameters.
     *
     * @return void
     */
    public function testListUserMethodSignature(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $method = $ref->getMethod('list_user');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame('account', $params[0]->getName());
        $this->assertSame('user', $params[1]->getName());
    }

    /**
     * Test that all public methods exist on the class.
     *
     * @return void
     */
    public function testExpectedPublicMethodsExist(): void
    {
        $expected = [
            '__construct',
            'authenticate',
            'get_url',
            'get_token',
            'set_v1_auth_url',
            'acl',
            'usage',
            'download',
            'download_passthrough',
            'upload',
            'delete',
            'ls',
            'ls_header',
            'list_accounts',
            'list_account',
            'list_user',
        ];

        $ref = new ReflectionClass(Swift::class);
        foreach ($expected as $methodName) {
            $this->assertTrue(
                $ref->hasMethod($methodName),
                "Expected public method {$methodName} not found"
            );
            if ($methodName !== '__construct') {
                $this->assertTrue(
                    $ref->getMethod($methodName)->isPublic(),
                    "Method {$methodName} should be public"
                );
            }
        }
    }

    /**
     * Test that none of the public methods are static.
     *
     * @return void
     */
    public function testNoPublicMethodsAreStatic(): void
    {
        $ref = new ReflectionClass(Swift::class);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === Swift::class) {
                $this->assertFalse(
                    $method->isStatic(),
                    "Method {$method->getName()} should not be static"
                );
            }
        }
    }

    /**
     * Test that the class has exactly 8 private properties.
     *
     * @return void
     */
    public function testPrivatePropertyCount(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $privateProps = array_filter(
            $ref->getProperties(ReflectionProperty::IS_PRIVATE),
            fn (ReflectionProperty $p) => $p->getDeclaringClass()->getName() === Swift::class
        );

        $this->assertCount(8, $privateProps);
    }

    /**
     * Test that the class does not extend any base class.
     *
     * @return void
     */
    public function testClassHasNoParent(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $this->assertFalse($ref->getParentClass());
    }

    /**
     * Test that the class does not implement any interfaces.
     *
     * @return void
     */
    public function testClassImplementsNoInterfaces(): void
    {
        $ref = new ReflectionClass(Swift::class);
        $this->assertEmpty($ref->getInterfaceNames());
    }

    /**
     * Test that the constructor initializes auth_url from the SWIFT_AUTH_URL constant.
     *
     * @return void
     */
    public function testAuthUrlMatchesConstant(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $prop = $ref->getProperty('auth_url');
        $prop->setAccessible(true);

        $this->assertSame(SWIFT_AUTH_URL, $prop->getValue($swift));
    }

    /**
     * Test that admin_user property matches the SWIFT_ADMIN_USER constant.
     *
     * @return void
     */
    public function testAdminUserMatchesConstant(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $prop = $ref->getProperty('admin_user');
        $prop->setAccessible(true);

        $this->assertSame(SWIFT_ADMIN_USER, $prop->getValue($swift));
    }

    /**
     * Test that auth_key property matches the SWIFT_ADMIN_KEY constant.
     *
     * @return void
     */
    public function testAuthKeyMatchesConstant(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $prop = $ref->getProperty('auth_key');
        $prop->setAccessible(true);

        $this->assertSame(SWIFT_ADMIN_KEY, $prop->getValue($swift));
    }

    /**
     * Test that the constructor header includes admin_user from constant.
     *
     * @return void
     */
    public function testConstructorHeaderContainsAdminUser(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $optionsProp = $ref->getProperty('options');
        $optionsProp->setAccessible(true);
        $options = $optionsProp->getValue($swift);

        $userHeader = $options[CURLOPT_HTTPHEADER][0];
        $this->assertSame('X-Auth-Admin-User:' . SWIFT_ADMIN_USER, $userHeader);
    }

    /**
     * Test that the constructor header includes auth_key from constant.
     *
     * @return void
     */
    public function testConstructorHeaderContainsAuthKey(): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $optionsProp = $ref->getProperty('options');
        $optionsProp->setAccessible(true);
        $options = $optionsProp->getValue($swift);

        $keyHeader = $options[CURLOPT_HTTPHEADER][1];
        $this->assertSame('X-Auth-Admin-Key:' . SWIFT_ADMIN_KEY, $keyHeader);
    }

    /**
     * Test that set_v1_auth_url works with various URL formats.
     *
     * @dataProvider urlProvider
     * @param string $url
     * @return void
     */
    public function testSetV1AuthUrlWithVariousFormats(string $url): void
    {
        $swift = new Swift();
        $ref = new ReflectionClass($swift);

        $prop = $ref->getProperty('v1_auth_url');
        $prop->setAccessible(true);

        $swift->set_v1_auth_url($url);
        $this->assertSame($url, $prop->getValue($swift));
    }

    /**
     * Data provider for URL format tests.
     *
     * @return array<string, array{string}>
     */
    public static function urlProvider(): array
    {
        return [
            'https url' => ['https://auth.example.com/auth/v1.0'],
            'http url' => ['http://auth.example.com/auth/v1.0'],
            'with port' => ['https://auth.example.com:8080/auth/v1.0'],
            'ip address' => ['https://192.168.1.1/auth/v1.0'],
            'empty string' => [''],
        ];
    }

    /**
     * Test that acl method trims the container argument.
     *
     * @return void
     */
    public function testAclTrimsContainer(): void
    {
        $swift = new Swift();
        // A container that is only whitespace should be trimmed to empty and return false
        $this->assertFalse($swift->acl("\t  \n"));
    }
}
