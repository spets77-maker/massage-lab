#!/usr/bin/env bash
# Local dev: serves the site and routes /api/* to PHP (POST login, etc.).
# Do NOT use: python -m http.server — it returns 501 for POST and breaks admin login.
cd "$(dirname "$0")"
exec php -S "${HOST:-localhost}:${PORT:-8080}" router.php
