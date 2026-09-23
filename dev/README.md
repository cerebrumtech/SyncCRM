# Local development helpers

`router.php` reproduces what nginx does in production:

```
try_files $uri $uri/ /index.php?$query_string
```

PHP's built-in server does not do this on its own, so without the router a URL such as
`/contacts/12` returns 404 locally while working fine on the server. Always start the dev
server through `serve.sh` (or pass `dev/router.php` yourself), never with a bare
`php -S ... -t public`.

Neither file is used in production. Cloudways serves the app through nginx and PHP-FPM.
