# SQLite → PostgreSQL Migration — Detailed Implementation Plan

## Overview

This document provides the **complete, file-by-file implementation plan** for migrating Print Hub from SQLite to PostgreSQL. It includes every code change needed, ordered by execution sequence.

---

## Audit Summary

### Raw SQL Patterns Found (Need Conversion)

| File | Line | Pattern | SQLite/MySQL? | PostgreSQL Fix |
|------|------|---------|---------------|----------------|
| [`app/Http/Controllers/Admin/WebhookController.php`](../app/Http/Controllers/Admin/WebhookController.php:82) | 82 | `TIMESTAMPDIFF(SECOND, created_at, COALESCE(updated_at, created_at)) as delivery_duration` | MySQL-specific | `EXTRACT(EPOCH FROM COALESCE(updated_at, created_at) - created_at)::integer as delivery_duration` |
| [`app/Http/Controllers/Admin/MonitoringController.php`](../app/Http/Controllers/Admin/MonitoringController.php:213) | 213 | `DATE_FORMAT(created_at, '{$groupFormat}') as time_group` | MySQL-specific | `TO_CHAR(created_at, '{$pgFormat}') as time_group` |
| [`app/Http/Controllers/Admin/WebhookController.php`](../app/Http/Controllers/Admin/WebhookController.php:21) | 21 | `COALESCE((SELECT status FROM webhook_deliveries ... LIMIT 1), 'none')` | Works on both | No change needed (standard SQL) |
| [`app/Http/Controllers/Admin/MonitoringController.php`](../app/Http/Controllers/Admin/MonitoringController.php:55) | 55 | `DB::raw('count(*) as total')` | Works on both | No change needed |
| [`app/Http/Controllers/AdminController.php`](../app/Http/Controllers/AdminController.php:35) | 35 | `selectRaw("status, COUNT(*) as count")` | Works on both | No change needed |
| [`app/Traits/BranchScopeable.php`](../app/Traits/BranchScopeable.php:24) | 24 | `whereRaw('1 = 0')` | Works on both | No change needed |
| [`database/migrations/2026_05_06_000007_rename_watermark_copy_texts_to_watermark_copies.php`](../database/migrations/2026_05_06_000007_rename_watermark_copy_texts_to_watermark_copies.php:23) | 23 | `DB::raw('watermark_copy_texts')` | Works on both (column ref) | No change needed |

### SQLite-Specific Functions Check

| Function | Searched | Found? |
|----------|----------|--------|
| `strftime()` | All `.php` files | ❌ Not found |
| `datetime()` | All `.php` files | ❌ Not found |
| `json_extract()` | All `.php` files | ❌ Not found |
| `json_set()` | All `.php` files | ❌ Not found |
| `julianday()` | All `.php` files | ❌ Not found |

### Migration Column Types Check

All 50+ migrations use Laravel schema builder methods that are database-agnostic:

- `$table->json(...)` → Maps to `json` in PostgreSQL, `text` in SQLite ✅
- `$table->boolean(...)` → Maps to `boolean` in PostgreSQL, `integer` in SQLite ✅
- `$table->text(...)` → Maps to `text` in both ✅
- `$table->longText(...)` → Maps to `text` in PostgreSQL, `text` in SQLite ✅
- `$table->mediumText(...)` → Maps to `text` in PostgreSQL, `text` in SQLite ✅
- `$table->timestamp(...)` → Maps to `timestamp` in both ✅
- `$table->softDeletes()` → Maps to `timestamp` in both ✅
- `$table->rememberToken()` → Maps to `varchar(100)` in PostgreSQL ✅
- `$table->unique(['name', 'version'])` → Works in both ✅
- `$table->foreignId(...)->constrained(...)` → Works in both ✅

**No migration changes needed** — Laravel's schema builder handles all type differences.

### Model `$casts` Check

All 21 models with `$casts` use standard Laravel cast types (`'array'`, `'boolean'`, `'integer'`, `'datetime'`, `'string'`) which are database-agnostic. No changes needed.

### Test Setup Check

[`phpunit.xml`](../phpunit.xml:26-27) uses:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

This is fine — tests should continue using SQLite in-memory for speed. The test environment is separate from production. No change needed.

---

## Implementation Steps

### Step 1: Fix Raw SQL in Controllers

#### 1a. Fix [`WebhookController.php`](../app/Http/Controllers/Admin/WebhookController.php:82) — `TIMESTAMPDIFF` → `EXTRACT(EPOCH ...)`

**Current code (line 82):**
```php
->selectRaw('TIMESTAMPDIFF(SECOND, created_at, COALESCE(updated_at, created_at)) as delivery_duration')
```

**Replace with:**
```php
->selectRaw('EXTRACT(EPOCH FROM COALESCE(updated_at, created_at) - created_at)::integer as delivery_duration')
```

**Why:** `TIMESTAMPDIFF` is MySQL-specific. PostgreSQL uses `EXTRACT(EPOCH FROM ...)` to get the difference in seconds between two timestamps.

#### 1b. Fix [`MonitoringController.php`](../app/Http/Controllers/Admin/MonitoringController.php:213) — `DATE_FORMAT` → `TO_CHAR`

**Current code (lines 197-213):**
```php
if ($period === '7d') {
    $start = $now->copy()->subDays(7);
    $groupFormat = "Y-m-d";
    $labelFormat = "D";
} elseif ($period === '30d') {
    $start = $now->copy()->subDays(30);
    $groupFormat = "Y-m-d";
    $labelFormat = "M j";
} else {
    $start = $now->copy()->subHours(24);
    $groupFormat = "Y-m-d H:00";
    $labelFormat = "H:00";
}

$jobs = PrintJob::where('created_at', '>=', $start)
    ->selectRaw("DATE_FORMAT(created_at, '{$groupFormat}') as time_group, COUNT(*) as count")
    ->groupBy('time_group')
    ->orderBy('time_group')
    ->get();
```

**Replace with:**
```php
if ($period === '7d') {
    $start = $now->copy()->subDays(7);
    $pgFormat = "YYYY-MM-DD";
    $phpFormat = "Y-m-d";
    $labelFormat = "D";
} elseif ($period === '30d') {
    $start = $now->copy()->subDays(30);
    $pgFormat = "YYYY-MM-DD";
    $phpFormat = "Y-m-d";
    $labelFormat = "M j";
} else {
    $start = $now->copy()->subHours(24);
    $pgFormat = "YYYY-MM-DD HH24:00";
    $phpFormat = "Y-m-d H:00";
    $labelFormat = "H:00";
}

$jobs = PrintJob::where('created_at', '>=', $start)
    ->selectRaw("TO_CHAR(created_at, '{$pgFormat}') as time_group, COUNT(*) as count")
    ->groupBy('time_group')
    ->orderBy('time_group')
    ->get();
```

**Why:** `DATE_FORMAT` is MySQL-specific. PostgreSQL uses `TO_CHAR` with different format specifiers (`YYYY-MM-DD` instead of `Y-m-d`, `HH24` instead of `H`).

---

### Step 2: Docker & Infrastructure Changes

#### 2a. Modify [`Dockerfile`](../Dockerfile:13-22)

**Current:**
```dockerfile
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libsqlite3-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install -j$(nproc) pdo pdo_sqlite mbstring xml zip bcmath \
    && a2enmod rewrite
```

**Replace with:**
```dockerfile
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libsqlite3-dev \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pdo_sqlite mbstring xml zip bcmath \
    && a2enmod rewrite
```

**Changes:**
- Add `libpq-dev` (PostgreSQL client library for PHP)
- Add `pdo_pgsql` to the `docker-php-ext-install` command
- Keep `libsqlite3-dev` and `pdo_sqlite` for test environment compatibility

#### 2b. Modify [`docker-compose.yml`](../docker-compose.yml:1-27)

**Current:**
```yaml
version: '3.8'

services:
  print-hub:
    build: .
    container_name: print-hub
    restart: unless-stopped
    ports:
      - "8082:80"
    environment:
      - APP_NAME=PrintHub
      - APP_ENV=local
      - APP_KEY=${APP_KEY:-base64:19hjIjrPouxH0sSc7OKp+Z7wjIYlDEWP5kfbYpKC7fY=}
      - APP_DEBUG=true
      - APP_URL=http://print-hub.hartonomotor-group.com
      - DB_DATABASE=/var/www/html/database/data/database.sqlite
      - SESSION_DRIVER=database
      - CACHE_STORE=database
      - QUEUE_CONNECTION=sync
    volumes:
      - print-hub-storage:/var/www/html/storage
      - print-hub-database:/var/www/html/database/data

volumes:
  print-hub-storage:
  print-hub-database:
```

**Replace with:**
```yaml
version: '3.8'

services:
  print-hub:
    build: .
    container_name: print-hub
    restart: unless-stopped
    ports:
      - "8082:80"
    depends_on:
      print-hub-db:
        condition: service_healthy
    environment:
      - APP_NAME=PrintHub
      - APP_ENV=local
      - APP_KEY=${APP_KEY:-base64:19hjIjrPouxH0sSc7OKp+Z7wjIYlDEWP5kfbYpKC7fY=}
      - APP_DEBUG=true
      - APP_URL=http://print-hub.hartonomotor-group.com
      - DB_CONNECTION=pgsql
      - DB_HOST=print-hub-db
      - DB_PORT=5432
      - DB_DATABASE=printhub
      - DB_USERNAME=printhub
      - DB_PASSWORD=${DB_PASSWORD:-secret}
      - SESSION_DRIVER=database
      - CACHE_STORE=database
      - QUEUE_CONNECTION=database
    volumes:
      - print-hub-storage:/var/www/html/storage

  print-hub-db:
    image: postgres:16-alpine
    container_name: print-hub-db
    restart: unless-stopped
    ports:
      - "5432:5432"
    environment:
      - POSTGRES_DB=printhub
      - POSTGRES_USER=printhub
      - POSTGRES_PASSWORD=${DB_PASSWORD:-secret}
    volumes:
      - print-hub-pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U printhub"]
      interval: 5s
      timeout: 5s
      retries: 5

volumes:
  print-hub-storage:
  print-hub-pgdata:
```

**Changes:**
- Add `depends_on` with healthcheck condition for `print-hub-db`
- Replace SQLite env vars with PostgreSQL connection vars
- Change `QUEUE_CONNECTION` from `sync` to `database` (PostgreSQL handles queues well)
- Remove `print-hub-database` volume (no longer needed)
- Add `print-hub-pgdata` volume for PostgreSQL data persistence
- Add PostgreSQL 16 Alpine service with healthcheck

#### 2c. Update [`.env.example`](../.env.example:23-28)

**Current:**
```
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

**Replace with:**
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=printhub
DB_USERNAME=printhub
DB_PASSWORD=secret
DB_SSLMODE=prefer
```

#### 2d. Update [`docker-compose.override.yml`](../docker-compose.override.yml) (if exists)

Check if this file exists and update similarly. If it doesn't exist, no action needed.

---

### Step 3: Data Migration Script

Create a new artisan command [`app/Console/Commands/MigrateSqliteToPostgres.php`](../app/Console/Commands/MigrateSqliteToPostgres.php) that:

1. Connects to both SQLite (source) and PostgreSQL (target) using Laravel's multiple database connections
2. Iterates through all tables and copies data row-by-row
3. Handles:
   - Boolean conversion (`0`/`1` → `false`/`true`) — Laravel's Eloquent handles this automatically
   - JSON serialization — Laravel's `$casts` handles this
   - Timestamp format normalization
   - Auto-increment ID reset via PostgreSQL sequences
4. Reports progress and errors

**Command signature:**
```php
protected $signature = 'db:migrate-to-pgsql
    {--source=sqlite : Source connection name}
    {--target=pgsql : Target connection name}
    {--tables= : Comma-separated list of tables to migrate (default: all)}
    {--drop-target : Drop target tables before migration}
    {--dry-run : Preview without executing}';
```

**Key implementation details:**
```php
public function handle()
{
    $source = $this->option('source');
    $target = $this->option('target');
    
    // Get all tables from source
    $tables = $this->getSourceTables($source);
    
    foreach ($tables as $table) {
        $this->info("Migrating table: {$table}");
        
        // Fetch all rows from source
        $rows = DB::connection($source)->table($table)->get();
        
        // Insert into target in chunks
        foreach ($rows->chunk(100) as $chunk) {
            DB::connection($target)->table($table)->insert(
                $chunk->map(fn($row) => (array) $row)->toArray()
            );
        }
        
        // Reset sequence for auto-increment
        $this->resetSequence($target, $table);
    }
}
```

**Note:** This command is optional — you can also use `pgloader` (a dedicated SQLite-to-PostgreSQL migration tool) or manual SQL dump/transform. The artisan command approach gives you more control and integrates with your existing Laravel setup.

---

### Step 4: Configuration Verification

#### 4a. Verify [`config/database.php`](../config/database.php:87-100)

The PostgreSQL config already exists and is correct. No changes needed.

#### 4b. Verify [`config/cache.php`](../config/cache.php:42-48)

The database cache store uses `env('DB_CACHE_CONNECTION')` which defaults to the default DB connection. When `DB_CONNECTION=pgsql`, the cache will automatically use PostgreSQL. No changes needed.

#### 4c. Verify [`config/queue.php`](../config/queue.php:38-45)

The database queue connection uses `env('DB_QUEUE_CONNECTION')` which defaults to the default DB connection. When `DB_CONNECTION=pgsql`, the queue will automatically use PostgreSQL. No changes needed.

---

### Step 5: Migration Execution Order

When deploying, run migrations in this order:

```bash
# 1. Ensure PostgreSQL is running
docker-compose up -d print-hub-db

# 2. Wait for PostgreSQL to be healthy
docker-compose exec print-hub-db pg_isready -U printhub

# 3. Run all migrations on PostgreSQL
php artisan migrate --force

# 4. Migrate data from SQLite to PostgreSQL
php artisan db:migrate-to-pgsql

# 5. Verify data integrity
php artisan tinker --execute="echo \App\Models\User::count();"
php artisan tinker --execute="echo \App\Models\PrintJob::count();"

# 6. Run test suite to verify
php artisan test --parallel
```

---

## Complete File Change Summary

| # | File | Change Type | Description |
|---|------|-------------|-------------|
| 1 | [`app/Http/Controllers/Admin/WebhookController.php`](../app/Http/Controllers/Admin/WebhookController.php:82) | **Edit** | Replace `TIMESTAMPDIFF(SECOND, ...)` with `EXTRACT(EPOCH FROM ...)::integer` |
| 2 | [`app/Http/Controllers/Admin/MonitoringController.php`](../app/Http/Controllers/Admin/MonitoringController.php:197-213) | **Edit** | Replace `DATE_FORMAT(...)` with `TO_CHAR(...)` and update format strings |
| 3 | [`Dockerfile`](../Dockerfile:13-22) | **Edit** | Add `libpq-dev` and `pdo_pgsql` |
| 4 | [`docker-compose.yml`](../docker-compose.yml:1-27) | **Edit** | Add PostgreSQL service, update env vars, change volumes |
| 5 | [`.env.example`](../.env.example:23-28) | **Edit** | Update DB connection defaults to PostgreSQL |
| 6 | [`app/Console/Commands/MigrateSqliteToPostgres.php`](../app/Console/Commands/MigrateSqliteToPostgres.php) | **Create** | New artisan command for data migration |
| 7 | [`phpunit.xml`](../phpunit.xml:26-27) | **No change** | Keep SQLite in-memory for tests |

---

## Rollback Plan

If issues arise after migration:

1. **Revert `.env`** — Set `DB_CONNECTION=sqlite` back
2. **Revert `docker-compose.yml`** — Remove PostgreSQL service, restore SQLite volume
3. **Revert `Dockerfile`** — Remove `libpq-dev` and `pdo_pgsql` (optional, harmless to keep)
4. **Restore SQLite backup** — The original `database.sqlite` file should be backed up before migration

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Raw SQL incompatibility | Low (2 patterns found) | High | Fixed in Step 1 |
| Migration script data loss | Low | High | Test on staging first, keep SQLite backup |
| Queue jobs lost during cutover | Medium | Medium | Drain queue before migration, set `QUEUE_CONNECTION=sync` temporarily |
| Performance regression | Low | Medium | Run `ANALYZE` after migration, add missing indexes |
| Test environment broken | Low | High | Keep `phpunit.xml` using SQLite in-memory |

---

## Recommendation

**Proceed with migration.** The codebase is well-abstracted through Laravel's ORM — only 2 raw SQL patterns need conversion. The PostgreSQL config already exists in `config/database.php`. Docker changes are straightforward. The migration command provides a clean data transfer path.

**Suggested timing:** Do this before deploying the v3 improvements to production, since it's easier to migrate a clean SQLite database than one with production data.
