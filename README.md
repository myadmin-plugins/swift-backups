# MyAdmin Swift Backups

OpenStack Swift object storage backup plugin for the [MyAdmin](https://github.com/detain/myadmin) control panel. Provides Swift API integration for backup container management including authentication, uploads, downloads, directory listings, ACL configuration, and account administration.

[![Build Status](https://github.com/detain/myadmin-swift-backups/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-swift-backups/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-swift-backups/version)](https://packagist.org/packages/detain/myadmin-swift-backups)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-swift-backups/downloads)](https://packagist.org/packages/detain/myadmin-swift-backups)
[![License](https://poser.pugx.org/detain/myadmin-swift-backups/license)](https://packagist.org/packages/detain/myadmin-swift-backups)

## Features

- Swift v1 token-based authentication with configurable retry
- Container listing with automatic pagination (10 000-entry batches)
- File upload with automatic ETag and Content-Type detection
- File download (buffered and passthrough modes)
- Container ACL management (read/write permissions)
- Storage usage and account introspection
- Integrates with MyAdmin's plugin, settings, and event systems

## Requirements

- PHP 8.2 or later
- ext-curl
- ext-soap

## Installation

```sh
composer require detain/myadmin-swift-backups
```

## Running Tests

```sh
composer install
vendor/bin/phpunit
```

## License

This package is licensed under the [LGPL-2.1](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.en.html) license.
