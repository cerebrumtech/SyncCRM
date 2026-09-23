# PHP 7.4 compatibility checker

The production server runs PHP 7.4.33 and the version is fixed server-wide, so a single
PHP 8 construct anywhere in the codebase takes the whole site down with a parse error.
This parses every file against the 7.4 grammar and reports what would not run.

```bash
cd tools/compat
composer install          # once; pulls nikic/php-parser
php check74.php ../../app ../../kernel ../../public ../../schema
```

It reports throw expressions, `match()`, `?->`, constructor promotion, enums, attributes,
union and `mixed`/`never` types, `$obj::class`, catch-without-variable, first-class
callables, named arguments, and calls to functions that only exist in PHP 8 — except the
three polyfilled in `kernel/polyfill.php`.

`fixture/` holds one small file per construct, so a change to the checker can be proved
against a known answer rather than against the real codebase.

This runs on your machine, not on the server. The server does its own check: `deploy/cloudways.sh`
compile-checks every file with the server's own `php -l` before it installs anything, and
rolls back to the previous commit if any file fails.
