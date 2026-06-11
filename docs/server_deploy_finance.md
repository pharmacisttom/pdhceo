# Finance/CFO Server Deployment

## Problem

The production web database user, for example `webtomdb@localhost`, should not run `CREATE TABLE` during normal page requests. If the finance/CFO tables are missing and the application tries to create them at runtime, MySQL returns:

```text
SQLSTATE[42000]: Syntax error or access violation: 1142 CREATE command denied
```

## One-Time Migration

Run this SQL once with a database user that has `CREATE` and `ALTER` privileges, such as root, DBA, or the Navicat maintenance user:

```bash
mysql -u root -p pdhceo_db < migrations/2026_06_05_finance_governance.sql
```

Or open `migrations/2026_06_05_finance_governance.sql` in Navicat/phpMyAdmin and execute it against the application database.

## Runtime User Permissions

After the migration, the web user only needs normal application permissions:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON pdhceo_db.* TO 'webtomdb'@'localhost';
FLUSH PRIVILEGES;
```

## Database Connection

`config/database.php` now chooses the connection automatically:

- Server: `localhost`, user `webtomdb`
- Development/XAMPP: `192.168.111.240`, user `tomwebdbnavicat`

Environment variables can override the defaults:

- `PDH_DB_HOST`
- `PDH_DB_NAME`
- `PDH_DB_USER`
- `PDH_DB_PASS`

## Auto Migration

Runtime auto-migration is disabled by default for safety. To allow auto-create only in a development environment, set:

```text
PDH_AUTO_MIGRATE=1
```

Do not enable this on the production web server unless the database user is intentionally allowed to run DDL.
