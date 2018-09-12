[![Build Status](https://travis-ci.com/sserbin/twig-linter.svg?branch=master)](https://travis-ci.com/sserbin/twig-linter)

# Intro
Standalone cli twig linter (heavily based on twig lint command from symfony-bridge), for those who don't use Symfony (if you do, you are better of using Symfony native `lint:twig`)

# Installation
```
composer require --dev sserbin/twig-linter:@dev
```

# Usage
```
vendor/bin/twig-linter lint /path/to/your/templates
```
By default `*.twig` files are searched. Pass in `--ext=?` (e.g. `--ext=html`) to override it.

# Limitations/known issues
Any non-standard twig's functions/filters/tests are ignored during linting. I.e. if there's invocations of undefined filter this will *not* be reported by linter as it doesn't know about your specific twig environment.

If, however, you want it to, you can manually add `LintCommand` to your console application's command set instantiating it with *your* environment.
