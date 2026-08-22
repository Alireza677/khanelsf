# Backup Phase 2

> Superseded: this document describes a historical Google Drive implementation. The current product contract is documented in `docs/current-backup-architecture.md`; Google Drive is no longer part of the runtime product.

Implemented: encrypted Google OAuth connection, manual database/files/full backups, queued archive creation, resumable Google Drive upload, verification, history, authorized streaming download, retry, and coordinated deletion.

The application requests only `drive.file`, `openid`, and `email`. OAuth application credentials remain in environment configuration. Backup archives are staged under private storage and removed after verified upload. Persistent files come from the allowlist in `config/backup.php`; transient Livewire uploads and backup artifacts are excluded.

Production requires PHP `zip`, `openssl`, `pdo_mysql`, and `exif`, a compatible `mysqldump`, HTTPS, stable `APP_KEY`, writable private storage, sufficient temporary disk capacity, Google Drive API/OAuth configuration, and a persistent worker processing the `backups` queue.

Run the worker with the deployment process manager:

```shell
php artisan queue:work --queue=backups,default --timeout=3600
```

Planned, not implemented: automatic scheduling, retention, restore/disaster-recovery UI, and a central OAuth broker.
