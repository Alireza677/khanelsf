# Backup Phase 3: automatic scheduling and retention

> Superseded: this document describes a historical automatic-backup implementation. The current product contract is documented in `docs/current-backup-architecture.md`; automatic schedules are no longer part of the runtime product.

Phase 3 adds an optional structured automatic schedule to the existing Phase 2 pipeline. Admins can select full/database/files backups, hourly/daily/weekly/monthly intervals, an interval value, preferred time where applicable, an IANA timezone, and retention from 1 to 90 successful automatic backups. Automatic backups are disabled by default and require a connected Google Drive account.

Laravel Scheduler runs `backup:dispatch-due` every minute. The command atomically claims due schedule rows, assigns a deterministic occurrence idempotency key, and dispatches the existing `CreateBackupJob` on the `backups` queue. It does not build archives itself. A running or queued backup causes the occurrence to be skipped and audited instead of creating an unbounded backlog.

After a verified automatic backup succeeds, `PruneBackupRetentionJob` deletes oldest successful automatic backups through the Phase 2 `BackupDeletionService`. Manual backups are never selected by schedule retention, at least one successful automatic recovery point is retained, and remote deletion failures remain auditable and retryable.

Temporary orphan cleanup runs daily using `backup:cleanup-orphans`; its TTL is configured by `BACKUP_TEMP_TTL_HOURS` (default 24). Referenced retry/active artifacts are preserved.

Production needs both a scheduler trigger and a persistent queue worker:

```shell
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
php artisan queue:work --queue=backups,default --timeout=3600
```

For larger sites, a dedicated `backups` worker is preferred to prevent long archives from delaying the default queue. The deployment path/process manager is infrastructure-owned and is not hard-coded in the application.

Implementation is complete, but production external-integration readiness still requires HTTPS Google OAuth/Drive upload/download/delete/refresh/revoke QA, a real queue worker and scheduler smoke test, `mysqldump`, writable private storage and capacity verification, PHP `ext-exif`, and a successful Composer security audit from a healthy advisory registry.

Still planned: Restore, archive encryption with disaster key recovery, persistent failure notifications, and a central OAuth broker/control plane.
