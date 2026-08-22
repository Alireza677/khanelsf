# Current Backup Product Contract

The current CMS provides server-local backup only:

```text
CMS Server
  -> Private Backup Storage
  -> Last 3 completed and available recovery points
  -> Authorized administrator download
```

Administrators can create a queued full backup, upload a valid archive previously produced by this CMS, and download one of the three latest available archives. Restore, Google Drive, OAuth, automatic backup schedules, remote retention, public URLs, and archive extraction are not part of the current product.

Generated backups reuse the existing database producer, persistent-file allowlist, ZIP archive, manifest, and SHA-256 checksum pipeline. The final archive is moved from `storage/app/private/backups/tmp` to `storage/app/private/backups/files`. Uploaded archives are first stored privately, opened without extraction, checked for unsafe paths and a supported root `manifest.json`, hashed with SHA-256, and moved to the same final storage.

Retention runs only after a generated or uploaded backup reaches `completed`. It keeps the newest three local recovery points regardless of whether their source is `manual` or `uploaded`. A failed creation or rejected upload never removes an existing recovery point. Manual deletion is intentionally absent from the user interface.

The `backups` queue worker remains required for generated backups. The scheduler no longer dispatches backups; it only runs `backup:cleanup-orphans` as internal temporary-file maintenance.

## Deployment boundary

Current CMS implementation temporarily provides server-local backup only. Off-site managed backup remains the production/commercial target architecture:

```text
CMS
  -> Managed Backup Server
  -> Plan-based Off-site Backup
  -> Automatic Backup
  -> Subscription Retention
```

The managed server, automatic triggers, restore, disaster recovery, and subscription logic are future capabilities and are not implemented here. For long-term protection, an administrator must download an archive and store it outside the CMS server.
