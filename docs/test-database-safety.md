# Test database safety

## Incident record

During the Phase 3 verification, `php artisan migrate:fresh --env=testing --force` was run before the repository had a `.env.testing` file. The command was intended for a test database, but Laravel's `--env=testing` selects an environment file; it does not import the environment variables declared in `phpunit.xml`. With no `.env.testing`, the process could retain the normal `.env` database configuration.

The subsequent read-only audit found the local CLI configured for MySQL and all inspected application tables empty. The `migrations` table contained all migrations in a single batch. This is consistent with a fresh migration, but the repository and database do not contain enough historical evidence to prove what data existed beforehand or conclusively attribute its absence to that command.

No credentials or passwords are recorded in this document.

## Isolation now enforced

- `.env.testing` selects SQLite with `DB_DATABASE=:memory:` and contains no real credentials.
- `phpunit.xml` independently selects the same in-memory database for PHPUnit.
- `config/database.php` explicitly defines the SQLite connection.
- `TestingDatabaseSafetyServiceProvider` runs only when `APP_ENV=testing` and refuses to boot unless the active database is SQLite `:memory:` or its name contains `_testing`.
- The base PHPUnit `TestCase` also refuses to run unless the application environment is exactly `testing`.
- In production, Laravel's built-in prohibition is enabled for `migrate:fresh`, `migrate:refresh`, `migrate:reset`, migration rollback, and `db:wipe`. Normal forward migrations remain available.

The provider deliberately does not block normal forward migrations. Its test guard fails fast when a process claims to be a test process but points at a non-test database.

## Operational rule

Never use destructive schema commands as a way to prepare a shared development or production database. Run automated tests through PHPUnit with the committed test configuration. Before any exceptional destructive database operation, independently verify the environment, driver, host, and database name without displaying credentials.
