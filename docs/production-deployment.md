# Production Deployment

## Required runtime
- PHP 8.2+ with Composer platform requirements satisfied.
- PostgreSQL is the recommended production database. MySQL/MariaDB is also supported by Laravel configuration.
- Install the matching native database backup client on the application host: `pg_dump` for PostgreSQL or `mysqldump` for MySQL/MariaDB.
- Never place database passwords in deployment scripts, command arguments, logs, or source control.
- Node/npm are build-time dependencies only when frontend assets must be built.

## Environment
Copy `.env.production.example` to the server secret-management workflow; never commit the real `.env`.
Generate `APP_KEY` securely and escrow it separately because encrypted tenant storage credentials depend on it.
Use HTTPS and secure session cookies. Production must not use SQLite or debug mode.

## Release sequence
Run `powershell -ExecutionPolicy Bypass -File tools/release-preflight.ps1 -SkipEnvironmentChecks` on the release candidate before uploading it. On the configured staging/production host, run the same command without `-SkipEnvironmentChecks`.

1. Install production Composer dependencies.
2. Build frontend assets if required.
3. Run database migrations with an approved deployment procedure.
4. Run `php artisan app:production-readiness`.
5. Run `php artisan app:deployment-readiness`.
6. Verify `GET /up`.
7. Start/reload the queue worker.
8. Ensure Laravel scheduler runs every minute.

## Release acceptance
- The full isolated test suite and production frontend build must pass.
- `app:production-readiness` and `app:deployment-readiness` must both pass on the destination host.
- Deployment readiness verifies the `/up` route, current migrations, writable runtime directories, repair schema, and repair routes.
- Perform a staging smoke test for login, asset creation, repair lifecycle, reports, file storage, queue processing, and backup creation.
- Keep the previous release and a verified pre-deployment database backup available for rollback.

## Long-running processes
Scheduler host cron:
`* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1`

Queue worker example:
`php artisan queue:work --sleep=3 --tries=3 --timeout=90`

Use Supervisor/systemd or the hosting platform process manager. Restart workers after each deployment with:
`php artisan queue:restart`

## Database backups
The application schedules `app:scheduled-database-backup` daily at 02:00 and retention pruning is part of that workflow.
Production PostgreSQL/MySQL backups must use native database dump tooling before this scheduled workflow can be considered production-complete.
Keep backup storage credentials and database credentials outside source control.
Test restore procedures periodically in an isolated non-production environment.

## Files and permissions
The web server needs write access to `storage/` and `bootstrap/cache/`.
The web root must point to `public/`.
Do not expose `.env`, storage credentials, logs, database files, or backup directories through the web server.

## Company-owned asset storage
Company BYOS/S3 originals are not part of the platform database backup. Database backups contain their metadata and encrypted credentials only.
The platform `APP_KEY` must be protected and recoverable.
# Temporary Render Demo

The repository includes `Dockerfile`, `render.yaml`, and the files under
`docker/` for a temporary Render deployment. Create the stack from the Render
Blueprint after pushing the intended branch to a Git provider.

The Blueprint provisions a free web service and a free PostgreSQL database.
During Blueprint creation, Render prompts for `APP_KEY` and the demo administrator
values. Generate `APP_KEY` with `php artisan key:generate --show`, use a unique
administrator username and email, and use a random password of at least 12
characters. The bootstrap command creates only the first super administrator and
never overwrites an existing one. Remove the demo password environment variable
after the first successful deployment.

This setup is for demonstration only:

- the free PostgreSQL database expires after 30 days and has no backups;
- the web service filesystem is ephemeral, so local uploads and generated
  files can disappear after a restart, spin-down, or redeploy;
- the free web service spins down while idle;
- queued work runs synchronously because a background worker is not included;
- SMTP delivery is not available on the free web service's blocked SMTP ports.

Before real production use, select paid persistent services, configure object
storage for uploaded files, enable a queue worker and scheduler, configure an
HTTPS mail provider, and verify database backups and restoration.

Use [`docs/demo-launch-checklist.md`](demo-launch-checklist.md) as the acceptance
gate for the temporary public demo and before every public redeployment.
