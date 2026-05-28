# ZAP Server-Level Findings

The Laravel app can set headers on responses that pass through the framework. ZAP findings on local server-owned responses still need web server or PHP runtime configuration.

For local Herd, Valet, Nginx, or production Nginx equivalents, verify these controls outside the Laravel codebase:

- Disable Nginx version disclosure with `server_tokens off`.
- Hide PHP framework/runtime disclosure with `fastcgi_hide_header X-Powered-By` and `expose_php = Off`.
- Add security headers to static files with `add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always` and `add_header X-Content-Type-Options "nosniff" always`.
- Re-run API scans against authenticated `/api/v1/*` JSON endpoints with `Accept: application/json` and bearer-token headers, not only the host root.
