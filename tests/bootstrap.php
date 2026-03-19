<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap file for myadmin-swift-backups.
 *
 * Defines stub constants and functions required by the source classes
 * so that tests can run without the full MyAdmin environment.
 */

// Define Swift constants used by the Swift class property defaults
if (!defined('SWIFT_AUTH_URL')) {
    define('SWIFT_AUTH_URL', 'https://auth.example.com/auth/v2.0');
}
if (!defined('SWIFT_AUTH_V1_URL')) {
    define('SWIFT_AUTH_V1_URL', 'https://auth.example.com/auth/v1.0');
}
if (!defined('SWIFT_ADMIN_USER')) {
    define('SWIFT_ADMIN_USER', 'test_admin');
}
if (!defined('SWIFT_ADMIN_KEY')) {
    define('SWIFT_ADMIN_KEY', 'test_key_secret');
}

// Stub the getcurlpage function used by Swift methods
if (!function_exists('getcurlpage')) {
    /**
     * Stub for getcurlpage — returns empty string in test environment.
     *
     * @param string $url
     * @param mixed  $params
     * @param array  $options
     * @return string
     */
    function getcurlpage(string $url = '', $params = '', array $options = []): string
    {
        return '';
    }
}

// Stub the gettext function _ if not available
if (!function_exists('_')) {
    /**
     * Stub for gettext translation function.
     *
     * @param string $text
     * @return string
     */
    function _(string $text): string
    {
        return $text;
    }
}

// Autoload via Composer
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Manually require the Swift class since it is not namespaced / not PSR-4
require_once dirname(__DIR__) . '/src/Swift.php';
