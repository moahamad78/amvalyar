# AmvalYar Demo Launch Checklist

This checklist is the release gate for the temporary public demo at `amvalyar.ir`.
It does not qualify the free Render stack for storage of real customer data.

## Domain and TLS

- Cloudflare zone status is `Active` and the authoritative nameservers match the two assigned by Cloudflare.
- Root and `www` CNAME records point to `amvalyar.onrender.com` and remain `DNS only` until Render verifies both domains and issues certificates.
- Render Custom Domains shows `Verified` and a valid certificate for the root domain and `www` redirect.
- `APP_URL` is set to `https://amvalyar.ir` in Render after certificate issuance.
- Cloudflare SSL/TLS mode is `Full`; proxying may be enabled only after direct HTTPS works.
- `/`, `/login`, and `/up` load over HTTPS without a certificate warning or redirect loop.

## Security

- `APP_ENV=production`, `APP_DEBUG=false`, secure/encrypted/HTTP-only session cookies are enabled.
- The login endpoint is rate limited and successful authentication rotates the session identifier.
- Baseline clickjacking, MIME sniffing, referrer, browser-permission, and HSTS headers are present.
- The bootstrap administrator password is unique, stored in a password manager, and tested once.
- `DEMO_ADMIN_USERNAME`, `DEMO_ADMIN_EMAIL`, and `DEMO_ADMIN_PASSWORD` are removed from Render after the first successful login. `APP_KEY` must remain configured and backed up securely.
- No secrets, personal identifiers, environment values, or access tokens appear in screenshots or logs.

## Functional smoke test

- Sign in and sign out from desktop and mobile layouts.
- Open the dashboard and confirm counts, navigation, empty states, and Persian RTL layout.
- Create a disposable company/user/employee/category and one test asset.
- Complete the asset coding and plate-print path.
- Complete one approved initial delivery and one movement/return request.
- Create, start, complete, cancel, and reopen disposable repair requests; verify repair reports and SLA indicators.
- Export a report and verify its content and Persian text.
- Upload and download a disposable attachment, then delete the test data.

## Monitoring and rollback

- Render health check for `/up` remains green and application warnings/errors are visible on stderr.
- Record the deployed commit SHA and keep the previous successful Render deploy available for rollback.
- Review failed requests, HTTP 5xx responses, and database errors after each deployment.
- Run `tools/release-preflight.ps1 -SkipEnvironmentChecks` before push and the readiness commands on a production-capable host.

## Data durability gate

- Never enter real customer data on the free demo database: it expires and has no managed backups.
- Never rely on Render's free ephemeral filesystem for attachments, plate output, or exports.
- Before production, move PostgreSQL to a persistent supported plan and configure verified off-platform backups with restore drills.
- Before production, configure the existing S3-compatible filesystem support for durable uploads and use a private bucket with lifecycle and backup policies.
- Before production, provision a queue worker, scheduler, and HTTPS-capable mail provider.
