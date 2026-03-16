# HestiaCP Custom Scripts

Custom scripts and templates for HestiaCP server provisioning in Astero.

---

## Server Provisioning (New Server Setup)

Use `provision-server.sh` to automatically set up a fresh HestiaCP server for Astero.

### Prerequisites

1. **Fresh HestiaCP installation** - Install HestiaCP first: https://hestiacp.com
2. **Root SSH access** - You need root access to run the provisioning script
3. **Release zip file** - Download the latest release from your Astero instance

### Quick Start

```bash
# SSH into your new server as root
ssh root@your-server-ip

# Download and extract the release
wget https://your-astero-domain/releases/latest.zip -O /tmp/release.zip
unzip -q /tmp/release.zip -d /tmp/astero-release

# Run the provisioning script
cd /tmp/astero-release/hestia
./provision-server.sh

# Optionally store the release for website provisioning
./provision-server.sh --release-zip /tmp/release.zip --version 1.0.0
```

### What It Does

1. Verifies HestiaCP installation
2. Deploys Hestia scripts from local `bin/` to `/usr/local/hestia/bin/`
3. Deploys nginx templates and PHP-FPM backend templates to `/usr/local/hestia/data/templates/`
4. Creates local releases repository at `/usr/local/hestia/data/astero/releases/`
5. Optionally stores the release zip for website provisioning (masters are built by `a-sync-releases` or on demand)

### Options

```bash
./provision-server.sh                                    # Deploy scripts only
./provision-server.sh --release-zip /path/to/release.zip # Also store release
./provision-server.sh --version 1.0.0                    # Specify version string
./provision-server.sh --force                            # Re-provision (overwrite)
./provision-server.sh --help                             # Show help message
```

### After Provisioning

1. If you didn't use `--release-zip`, sync a release:
    ```bash
    a-sync-releases application main --set-active
    ```
2. Create an access key (see section below)
3. Add the server in Astero Platform UI
4. Server is ready to provision websites!

---

## API Authentication Setup

Astero connects to HestiaCP servers using **Access Keys** (not username/password). You must create an access key with full permissions (`*`) for the admin user.

### Creating an Access Key (Run on Hestia server as root)

```bash
# Create access key with full permissions for admin user
# Replace 'admin' with your actual admin username
v-add-access-key adminxastero '*' astero json
```

**Output:**

```json
{
    "ACCESS_KEY_ID": "xxxxxxxxxxxxxxxxxxxx",
    "SECRET_ACCESS_KEY": "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

### Configuring in Astero

When adding a server in Astero:

- **Access Key ID**: The 20-character `ACCESS_KEY_ID` from above
- **Secret Key**: The 40-character `SECRET_ACCESS_KEY` from above

> **Important:** The `'*'` permission is required because Astero needs to execute commands across multiple users (creating users, domains, databases, etc.). Without full permissions, you'll get "Object cannot be accessed by this user" errors.

### Managing Access Keys

```bash
# List all access keys for a user
v-list-access-keys admin json

# Delete an access key
v-delete-access-key admin ACCESS_KEY_ID
```

---

## SSL Certificates

### Understanding HestiaCP SSL File Requirements

HestiaCP expects SSL certificate files in a specific format with **three separate files**:

| File         | Content                 | Description                               |
| ------------ | ----------------------- | ----------------------------------------- |
| `domain.crt` | Server certificate only | Just the leaf certificate for your domain |
| `domain.ca`  | CA bundle               | Intermediate + root certificates          |
| `domain.key` | Private key             | Your private key (RSA or EC)              |

> **Important:** HestiaCP requires the `domain.ca` file to exist. If you only provide the certificate and key without a CA bundle, SSL installation will fail with "Certificate Authority not found" error.

### ACME/Let's Encrypt Certificate Mapping

When using ACME clients (acme.sh, certbot, etc.), you'll get several files. Here's how to map them to Astero's SSL fields:

#### Option 1: Using Individual Files (Recommended)

| ACME File                     | Astero Field |
| ----------------------------- | ------------ |
| `domain.cer` or `cert.pem`    | Certificate  |
| `ca.cer` or `chain.pem`       | CA Bundle    |
| `domain.key` or `privkey.pem` | Private Key  |

#### Option 2: Using Fullchain

| ACME File                          | Astero Field                |
| ---------------------------------- | --------------------------- |
| `fullchain.cer` or `fullchain.pem` | Certificate                 |
| `ca.cer` or `chain.pem`            | CA Bundle (still required!) |
| `domain.key` or `privkey.pem`      | Private Key                 |

### Why `fullchain.cer` Alone Doesn't Work

ACME's `fullchain.cer` contains both your server certificate AND the CA/intermediate certificates combined in one file. However, HestiaCP still requires a separate `domain.ca` file to exist.

**What happens:**

- If you paste `fullchain.cer` into Certificate field only → ❌ Fails with "Certificate Authority not found"
- If you paste `fullchain.cer` into Certificate AND `ca.cer` into CA Bundle → ✅ Works

The CA certificates end up in both files (redundant but harmless), and HestiaCP is satisfied.

### Common ACME File Names by Client

| Client  | Server Cert      | CA Bundle   | Fullchain         | Private Key   |
| ------- | ---------------- | ----------- | ----------------- | ------------- |
| acme.sh | `domain.cer`     | `ca.cer`    | `fullchain.cer`   | `domain.key`  |
| certbot | `cert.pem`       | `chain.pem` | `fullchain.pem`   | `privkey.pem` |
| Caddy   | (uses fullchain) | (embedded)  | `certificate.crt` | `private.key` |

### EC vs RSA Keys

The `a-install-ssl-certificate` script supports both **RSA** and **EC (Elliptic Curve)** private keys. ACME clients typically generate EC keys by default (faster, more secure).

Both key types work identically - no special configuration needed.

---

## Directory Structure

```
hestia/
├── bin/                    # Custom executable scripts (a-* prefix)
│   ├── a-prepare-astero          # Create release with master symlinks
│   ├── a-create-environment-file # Create .env in shared
│   ├── a-install-astero          # Run Laravel install
│   ├── a-install-master          # Create immutable shared master installation
│   ├── a-install-ssl-certificate # Install SSL cert from base64 content
│   ├── a-update-astero           # Zero-downtime update (master symlinks)
│   ├── a-rollback-astero         # Instant rollback via symlink switch
│   ├── a-revert-astero-updates   # Legacy rollback (pre-master layout)
│   ├── a-generate-ssl-certificate
│   ├── a-get-server-info
│   ├── a-get-website-info
│   ├── a-list-releases
│   ├── a-revert-installation-step
│   ├── a-set-active-release
│   ├── a-sync-releases
│   ├── a-setup-queue-worker      # Create Supervisor config for queue workers
│   ├── a-manage-queue-worker     # Manage queue workers (start/stop/restart/remove)
│   ├── a-manage-cron             # Manage scheduler cron (suspend/unsuspend)
│   ├── a-update-geoip            # Download/update MaxMind GeoIP database
│   └── a-debug-functions.sh
└── data/
    └── templates/          # Nginx/PHP-FPM templates
```

## Website Directory Structure

After Astero installation, each website uses **symlinks to a shared master installation** to save storage (~90% reduction).

### Per-Website Structure

```
/home/{user}/web/{domain}/public_html/
├── releases/
│   └── v1.2.0/
│       │
│       │  ── SYMLINKS TO MASTER (read-only code) ──
│       ├── app -> /usr/local/hestia/data/astero/installed/v1.2.0/app
│       ├── vendor -> /usr/local/hestia/data/astero/installed/v1.2.0/vendor
│       ├── modules -> /usr/local/hestia/data/astero/installed/v1.2.0/modules
│       ├── config -> /usr/local/hestia/data/astero/installed/v1.2.0/config
│       ├── database -> .../installed/v1.2.0/database
│       ├── lang -> .../installed/v1.2.0/lang
│       ├── resources -> .../installed/v1.2.0/resources
│       ├── routes -> .../installed/v1.2.0/routes
│       ├── stubs -> .../installed/v1.2.0/stubs
│       ├── composer.json -> .../installed/v1.2.0/composer.json
│       ├── composer.lock -> .../installed/v1.2.0/composer.lock
│       │
│       │  ── COPIED FILES (use __DIR__, must be real files) ──
│       ├── artisan                      ← Copied from master
│       ├── bootstrap/
│       │   ├── app.php                  ← Copied from master (uses dirname(__DIR__))
│       │   ├── providers.php -> .../installed/v1.2.0/bootstrap/providers.php
│       │   └── cache/                   ← Per-website writable cache
│       │
│       │  ── SYMLINKS TO SHARED (per-website writable) ──
│       ├── storage -> ../../shared/storage
│       ├── .env -> ../../shared/.env
│       ├── themes -> ../../shared/themes          ← Nginx serves /themes from current/themes
│       ├── modules_statuses.json -> ../../shared/modules_statuses.json
│       │
│       └── public/
│           │  ── SYMLINKS TO MASTER (static assets) ──
│           ├── build -> /usr/local/hestia/data/astero/installed/v1.2.0/public/build
│           ├── vendor -> .../installed/v1.2.0/public/vendor
│           ├── images -> .../installed/v1.2.0/public/images
│           ├── assets -> .../installed/v1.2.0/public/assets
│           │
│           │  ── COPIED FILES (use __DIR__) ──
│           ├── index.php                ← Copied from master (Laravel entry point)
│           │
│           │  ── SYMLINKS TO SHARED (per-website) ──
│           ├── storage -> ../../../shared/storage/app/public
│           └── favicon.ico -> ../../../shared/public/favicon.ico
│
├── shared/
│   ├── .env                    # Environment config - persists across releases
│   ├── modules_statuses.json   # Module enable/disable state (nwidart/laravel-modules)
│   ├── storage/                # Laravel storage (logs, cache, uploads)
│   │   ├── app/public/         # Public uploads (linked to public/storage)
│   │   ├── framework/          # Views, sessions, cache
│   │   └── logs/               # Application logs
│   ├── themes/                 # Theme files (served via nginx from current/themes)
│   │   ├── default/
│   │   ├── default-child/
│   │   ├── .active_theme        # Preserved per-site
│   │   └── */config/options.json # Preserved per-site settings
│   └── public/                 # Per-website unique files
│       ├── favicon.ico
│       ├── robots.txt
│       ├── sitemap.xml
│       └── ...
│
└── current -> releases/v1.2.0   # Symlink to active release (atomic switch)
```

Theme assets are served by the nginx template from `current/themes` (symlinked to `shared/themes`). Themes are not placed in `public/`.

### Shared Master Installation

All websites share the same read-only code from a master installation:

```
/usr/local/hestia/data/astero/installed/
└── v1.2.0/                    ← ONE master per version (immutable, root-owned)
    ├── app/                   ← Shared by all sites
    ├── vendor/                ← ~100MB shared (biggest savings!)
    ├── modules/               ← CMS, Platform, etc.
    ├── config/
    ├── resources/
    ├── themes/                ← Default themes (copied to shared/ on first provision)
    ├── public/
    │   ├── build/             ← Vite compiled assets
    │   ├── assets/            ← Static images, fonts
    │   ├── vendor/            ← Published package assets
    │   └── index.php          ← Copied (not symlinked) to each site
    └── ...
```

During updates, shared themes are synced from the master while preserving per-site `.active_theme` and `config/options.json`.

### Why Some Files Are Copied (Not Symlinked)

These files use `__DIR__` or `dirname(__DIR__)` to determine Laravel's base path:

| File                | Reason                                          |
| ------------------- | ----------------------------------------------- |
| `artisan`           | Uses `__DIR__` to find `vendor/autoload.php`    |
| `bootstrap/app.php` | Uses `dirname(__DIR__)` for base path           |
| `public/index.php`  | Uses `__DIR__` to find `../vendor/autoload.php` |

If symlinked, these would resolve to the master installation path, causing Laravel to use the wrong `.env`, `storage`, and `bootstrap/cache`.

### Storage Savings

| Component        | Per-Site (Old) | Per-Site (New) |
| ---------------- | -------------- | -------------- |
| vendor/          | ~100MB         | 0 (symlink)    |
| app/ + modules/  | ~25MB          | 0 (symlink)    |
| public/build/    | ~30MB          | 0 (symlink)    |
| bootstrap/cache/ | ~1MB           | ~1MB (unique)  |
| shared/          | varies         | varies         |
| **TOTAL**        | **~200MB**     | **~15-25MB**   |

**100 websites: ~20GB → ~2GB (90% reduction)**

### Key Directories

| Directory                      | Purpose                                                                      |
| ------------------------------ | ---------------------------------------------------------------------------- |
| `shared/.env`                  | Environment configuration - persists across all releases                     |
| `shared/storage/`              | Laravel storage (logs, cache, uploads) - persists across releases            |
| `shared/themes/`               | Theme files and per-site settings (`.active_theme`, `*/config/options.json`) |
| `shared/modules_statuses.json` | Which modules are enabled/disabled for this website (preserved on update)    |
| `shared/public/`               | Per-website unique files (favicon, robots.txt, sitemap, etc.)                |
| `bootstrap/cache/`             | Per-website Laravel cache (config, routes, services)                         |

This structure enables:

- **90% storage reduction**: Shared read-only code across all websites
- **Zero-downtime updates**: Atomic symlink switch
- **Instant rollback**: Point `current` to previous release
- **Immutable masters**: Never modify deployed code in-place
- **Persistent data**: User data and configurations survive updates
- **Per-website module control**: Each site can enable/disable different modules
- **Theme settings preserved**: `.active_theme` and `themes/*/config/options.json` survive updates

---

## Local Releases Repository

The server maintains a local repository of Astero releases and master installations at `/usr/local/hestia/data/astero/`. This provides:

- **Full control** over which versions are deployed
- **Faster provisioning** (symlinks, no extraction per site)
- **Offline capability** (works even if central server is down)
- **Easy rollback** (set active to previous version)
- **Auto masters**: `a-sync-releases` builds immutable masters for each version

### Repository Structure

```
/usr/local/hestia/data/astero/
├── releases/                    # Downloaded release zips
│   └── application/
│       └── main/
│           ├── v1.0.0.zip
│           ├── v1.2.0.zip
│           └── current -> v1.2.0.zip
├── installed/                   # Master installations (shared code)
│   ├── v1.0.0/                  # Immutable - never modify
│   └── v1.2.0/                  # Immutable - never modify
└── releases.json                # Metadata cache
```

### Initial Setup (Run as root)

```bash
# 1. Create the releases directory
sudo mkdir -p /usr/local/hestia/data/astero/releases

# 2. Sync the latest release from central server
a-sync-releases application main --set-active

# 3. Verify
a-list-releases
```

### Managing Releases

```bash
# List all locally available releases
a-list-releases

# List releases for specific type/package
a-list-releases application main

# Sync latest release from central server
a-sync-releases application main

# Sync and immediately set as active
a-sync-releases application main --set-active

# Create a master installation manually (if needed)
a-install-master 1.2.0 application main

# Set a specific version as active for new deployments
a-set-active-release application main 1.1.0
```

### Workflow: Rolling Out New Versions

```bash
# 1. Sync new version from central server
a-sync-releases application main --set-active

# 2. Verify it's active
a-list-releases application main

# Output:
# TYPE            PACKAGE         VERSION      ACTIVE     SIZE
# application     main            v1.0.0                  45 MB
# application     main            v1.1.0                  46 MB
# application     main            v1.2.0       *          47 MB
```

### Workflow: Rolling Back

```bash
# Set previous version as active (new deployments will use this)
a-set-active-release application main 1.1.0
```

---

## Server Information Script

The `a-get-server-info` script provides comprehensive server information in JSON format, used by the "Sync Server" feature in the admin panel.

### Usage

```bash
# Get all server info in JSON format
a-get-server-info json

# Output example:
{
  "status": "success",
  "data": {
    "hostname": "server1.example.com",
    "ip_address": "10.0.0.1",
    "os": "Ubuntu 24.04",
    "cpu": "AMD EPYC 7B13",
    "cpu_cores": 4,
    "ram_mb": 8192,
    "storage_total_gb": 100,
    "storage_used_gb": 45,
    "storage_free_gb": 55,
    "hestia_version": "1.8.12",
    "astero_version": "1.2.0",
    "astero_releases": ["1.0.0", "1.1.0", "1.2.0"],
    "uptime": "45 days, 3 hours",
    "load_average": "0.15, 0.22, 0.18"
  }
}
```

### Information Gathered

| Field             | Source                                   |
| ----------------- | ---------------------------------------- |
| `hostname`        | System hostname                          |
| `ip_address`      | First non-localhost IPv4 address         |
| `os`              | `/etc/os-release` (NAME + VERSION_ID)    |
| `cpu`             | `/proc/cpuinfo` model name               |
| `cpu_cores`       | `nproc` command                          |
| `ram_mb`          | `/proc/meminfo` MemTotal                 |
| `storage_*_gb`    | `df` on root filesystem                  |
| `hestia_version`  | HestiaCP package version                 |
| `astero_version`  | Active release from local repository     |
| `astero_releases` | All versions in local releases directory |

---

## Server Locations

| Local Path                                 | Server Path                                           |
| ------------------------------------------ | ----------------------------------------------------- |
| `hestia/bin/`                              | `/usr/local/hestia/bin/`                              |
| `hestia/data/templates/web/nginx/php-fpm/` | `/usr/local/hestia/data/templates/web/nginx/php-fpm/` |
| `hestia/data/templates/web/php-fpm/`       | `/usr/local/hestia/data/templates/web/php-fpm/`       |

---

## PHP-FPM Backend Template

Astero uses a custom PHP-FPM backend template (`astero-php`) that extends the default HestiaCP `open_basedir` to allow access to the shared master installation directory.

### Why It's Needed

The master installation symlink architecture stores shared code at:

```
/usr/local/hestia/data/astero/installed/v1.x.x/
```

By default, HestiaCP's `open_basedir` restriction only allows access to paths under `/home/{user}/...`. Since our symlinks point to the master installation, PHP would be blocked from accessing the shared code.

### Template Location

| Local Path                                         | Server Path                                                   |
| -------------------------------------------------- | ------------------------------------------------------------- |
| `hestia/data/templates/web/php-fpm/astero-php.tpl` | `/usr/local/hestia/data/templates/web/php-fpm/astero-php.tpl` |

### Key Difference from Default

The template adds `/usr/local/hestia/data/astero` to the `open_basedir`:

```ini
php_admin_value[open_basedir] = /home/%user%/...:/usr/local/hestia/data/astero
```

### Usage

New domains created via `a-create-web-domain` automatically use this backend template. For existing domains:

```bash
# Change backend template
v-change-web-domain-backend-tpl USER DOMAIN astero-php

# Rebuild domain to apply
v-rebuild-web-domain USER DOMAIN yes
```

---

## Development Setup (Local Dev Server)

Run this as root to set up all symlinks:

```bash

# Set these to match your environment
USER="azadone"
DOMAIN="azadone.192.168.0.123.traefik.me"
BASEDIR="/home/${USER}/web/${DOMAIN}/public_html/current/hestia"

# Step 1: Make scripts executable
chmod +x "${BASEDIR}/bin/a-*"
echo "All scripts made executable!"

# Step 2: Create symlinks to Hestia bin
for file in "${BASEDIR}"/bin/a-*; do
    sudo ln -sf "$file" /usr/local/hestia/bin/
done && echo "All bin symlinks created!"

# Step 3: Create symlinks for nginx templates
for file in "${BASEDIR}"/data/templates/web/nginx/php-fpm/astero*.tpl "${BASEDIR}"/data/templates/web/nginx/php-fpm/astero*.stpl; do
    sudo ln -sf "$file" /usr/local/hestia/data/templates/web/nginx/php-fpm/
done && echo "All nginx template symlinks created!"

# Step 4: Create symlinks for php-fpm backend templates
for file in "${BASEDIR}"/data/templates/web/php-fpm/astero*.tpl; do
    sudo ln -sf "$file" /usr/local/hestia/data/templates/web/php-fpm/
done && echo "All backend template symlinks created!"

# Step 5: Verify symlinks
ls -la /usr/local/hestia/bin/a-*
ls -la /usr/local/hestia/data/templates/web/nginx/php-fpm/astero*
ls -la /usr/local/hestia/data/templates/web/php-fpm/astero*.tpl

```

> **Note:** With symlinks, any changes to scripts in this project are immediately available to Hestia. No syncing required!

---

## Production Deployment

### Upload and fix line endings

```bash
cd /usr/local/hestia/bin
dos2unix -k -o ./a-*
chmod 755 ./a-*
```

### Rebuild a domain after template changes

```bash
v-rebuild-web-domain USER DOMAIN yes
```

---

## Troubleshooting: Line Endings (CRLF → LF)

If scripts fail with `bad interpreter` errors, fix Windows line endings:

### Fix in Git (prevent future issues)

```bash
git config core.autocrlf false
git rm --cached -r .
git reset --hard
```

### Fix existing files on server

```bash
dos2unix -k -o /usr/local/hestia/bin/a-*
```

### Check hestiacp logs for astero api operations

```bash
micro /usr/local/hestia/data/astero/logs/astero-scripts.log
```

---

## Custom Scripts Reference

| Script                       | Description                                                                  |
| ---------------------------- | ---------------------------------------------------------------------------- |
| `a-create-web-domain`        | Creates web domain with Astero templates (uses `astero-php` backend)         |
| `a-install-master`           | Creates immutable shared master installation (saves ~90% storage)            |
| `a-prepare-astero`           | Creates release with master symlinks, sets up per-site dirs                  |
| `a-create-environment-file`  | Creates `.env` file in shared directory for Astero                           |
| `a-install-astero`           | Runs Laravel installation, sets up scheduler and queue worker                |
| `a-update-astero`            | Atomic upgrade: create release, run migrations, health check, switch         |
| `a-rollback-astero`          | Instant rollback via symlink switch to previous release                      |
| `a-revert-astero-updates`    | Reverts Astero to previous version (legacy)                                  |
| `a-generate-ssl-certificate` | Generates self-signed SSL certificate                                        |
| `a-setup-queue-worker`       | Creates Supervisor config for persistent queue workers                       |
| `a-manage-queue-worker`      | Manages queue workers (start/stop/restart/status/remove/scale)               |
| `a-get-server-info`          | Returns comprehensive server info in JSON format                             |
| `a-get-website-info`         | Returns website-specific info in JSON format                                 |
| `a-list-releases`            | Lists locally available releases                                             |
| `a-set-active-release`       | Sets which version to use for new deployments                                |
| `a-sync-releases`            | Downloads releases and auto-creates master installation                      |
| `a-revert-installation-step` | Reverts specific provisioning step                                           |
| `a-manage-cron`              | Manages scheduler cron job (suspend/unsuspend)                               |
| `a-update-geoip`             | Downloads/updates MaxMind GeoLite2 database (auto-called by a-sync-releases) |
| `a-debug-functions.sh`       | Shared debug/logging functions                                               |

---

## Queue Workers (Supervisor)

Each provisioned website gets its own Supervisor-managed queue workers for background job processing.

### Automatic Setup

Queue workers are automatically configured during website provisioning (`a-install-astero`). Default: 1 worker per website.

### Managing Workers

```bash
# Check worker status
a-manage-queue-worker USER DOMAIN status

# Restart workers (after code changes)
a-manage-queue-worker USER DOMAIN restart

# Stop workers
a-manage-queue-worker USER DOMAIN stop

# Start workers
a-manage-queue-worker USER DOMAIN start

# Scale workers (change number)
a-manage-queue-worker USER DOMAIN scale 2

# Remove worker configuration
a-manage-queue-worker USER DOMAIN remove
```

### Configuration Location

- Configs: `/etc/supervisor/conf.d/astero_{user}.conf`
- Logs: `/home/{user}/web/{domain}/logs/queue-worker.log`

### Supervisor Commands

```bash
# View all Astero queue workers
supervisorctl status | grep astero_

# Reload all configs
supervisorctl reread && supervisorctl update
```

### Website Status Integration

Queue workers and cron jobs are automatically managed based on website status:

| Status Change    | Queue Workers | Cron Job      |
| ---------------- | ------------- | ------------- |
| Suspend          | **stop**      | **suspend**   |
| Expire           | **stop**      | **suspend**   |
| Trash            | **stop**      | **suspend**   |
| Permanent Delete | **remove**    | -             |
| Unsuspend        | **start**     | **unsuspend** |
| UnExpire         | **start**     | **unsuspend** |
| Untrash          | **start**     | **unsuspend** |

---

## Cron Job Management

The scheduler cron job (`schedule:run`) can be suspended/unsuspended independently of queue workers.

### Managing Cron Jobs

```bash
# Suspend cron job (pauses scheduled tasks)
a-manage-cron USER DOMAIN suspend

# Unsuspend cron job (resumes scheduled tasks)
a-manage-cron USER DOMAIN unsuspend
```

This is automatically called when website status changes (suspend/expire/trash/restore).

---

## Provisioning Flow

### New Website Installation

1. `platform:hestia:create-user` - Create Hestia user
2. `platform:hestia:create-website` - Create web domain
3. `platform:hestia:create-database` - Create MySQL database
4. `platform:hestia:generate-ssl` - Generate SSL certificate
5. `platform:hestia:prepare-astero` - Create release with master symlinks, setup shared dirs
6. `platform:hestia:configure-env` - Create .env in shared folder
7. `platform:hestia:install-astero` - Run Laravel installation, setup scheduler + queue worker

### Website Update

1. `platform:hestia:update-astero` - Create new release with master symlinks, run migrations, health check, switch symlink, restart queue workers

### Version Rollback

1. `platform:hestia:rollback-astero` - Switch to previous release version (instant)
2. `platform:hestia:revert-astero-updates` - Legacy rollback for older layouts
