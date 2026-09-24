---
id: installation
title: Installation
sidebar_position: 2
description: Requirements and installation of Dirthara Container.
---

## Requirements

PHP 8.5 or later within the PHP 8 series is required. Composer installs its one
runtime dependency, the PSR interface package it implements:

| Package | Provides |
| --- | --- |
| `psr/container` `^2.0` | The PSR-11 container interfaces. |

## Package installation

Install the package with Composer:

```sh
composer require dirthara/container
```

The package declares that it provides `psr/container-implementation`, so a library that requires a PSR-11 container
implementation, rather than a specific container, can be installed together with it.

For development, follow the Docker and Composer setup in the repository's
[README](https://github.com/dirthara/container#readme). Development tooling
includes PHPUnit, Mago, and Xdebug.
