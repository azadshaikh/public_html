#!/bin/bash
#
# Script: a-common.sh
#
# Description: Shared functions for Astero Hestia scripts.
#              Source this file at the beginning of other a-* scripts.
#
# Usage: source "$HESTIA/bin/a-common.sh"
#

# --- Configuration ---
HESTIA=${HESTIA:-/usr/local/hestia}
BIN=${BIN:-/usr/local/hestia/bin}
ASTERO_DATA_DIR="${ASTERO_DATA_DIR:-/usr/local/hestia/data/astero}"
RELEASES_REPO="$ASTERO_DATA_DIR/releases"
INSTALLED_DIR="$ASTERO_DATA_DIR/installed"
LOG_FILE="${ASTERO_DATA_DIR}/logs/astero-scripts.log"
RELEASE_API_KEY_FILE="${ASTERO_DATA_DIR}/release_api_key"

# --- Logging Functions ---
# Usage: log_info "message"
log_info() {
    local script_name="${SCRIPT_NAME:-a-common}"
    local msg="$1"
    mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$script_name] INFO: $msg" >> "$LOG_FILE"
    echo "$msg"
}

# Usage: log_error "message"
log_error() {
    local script_name="${SCRIPT_NAME:-a-common}"
    local msg="$1"
    mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$script_name] ERROR: $msg" >> "$LOG_FILE"
    echo "Error: $msg" >&2
}

# Usage: handle_error exit_code "message"
handle_error() {
    log_error "$2"
    exit "$1"
}

# --- Shared Directory Setup ---
# Creates the shared directory structure for a website
# Usage: setup_shared_dirs "$SHARED_DIR" "$MASTER_DIR" "$hestia_user" "$EXISTING_DIR"
setup_shared_dirs() {
    local shared_dir="$1"
    local master_dir="$2"
    local hestia_user="$3"
    local existing_dir="${4:-}"

    # Create shared storage structure
    mkdir -p "$shared_dir/storage/app/public"
    mkdir -p "$shared_dir/storage/framework/cache/data"
    mkdir -p "$shared_dir/storage/framework/sessions"
    mkdir -p "$shared_dir/storage/framework/views"
    mkdir -p "$shared_dir/storage/logs"
    mkdir -p "$shared_dir/public"
    mkdir -p "$shared_dir/themes"

    # Per-website module status (nwidart/laravel-modules)
    local module_statuses_shared="$shared_dir/modules_statuses.json"
    if [ ! -f "$module_statuses_shared" ]; then
        if [ -n "$existing_dir" ] && [ -f "$existing_dir/modules_statuses.json" ] && [ ! -L "$existing_dir/modules_statuses.json" ]; then
            cp "$existing_dir/modules_statuses.json" "$module_statuses_shared" 2>/dev/null || true
        elif [ -f "$master_dir/modules_statuses.json" ]; then
            cp "$master_dir/modules_statuses.json" "$module_statuses_shared" 2>/dev/null || true
        else
            echo '{}' > "$module_statuses_shared"
        fi
    fi

    # Set ownership
    chown -R "$hestia_user:$hestia_user" "$shared_dir"
}

# --- Theme Sync ---
# Updates shared themes from master while preserving per-site settings.
# Usage: sync_shared_themes "$SHARED_DIR" "$MASTER_DIR" "$EXISTING_DIR" "$hestia_user"
sync_shared_themes() {
    local shared_dir="$1"
    local master_dir="$2"
    local existing_dir="${3:-}"
    local hestia_user="${4:-}"
    local existing_themes_dir=""

    mkdir -p "$shared_dir/themes"

    if [ -n "$existing_dir" ] && [ -d "$existing_dir/themes" ] && [ ! -L "$existing_dir/themes" ]; then
        existing_themes_dir="$existing_dir/themes"
    fi

    # Seed shared themes if empty (prefer existing release)
    local theme_count
    theme_count=$(find "$shared_dir/themes" -mindepth 1 -maxdepth 1 2>/dev/null | wc -l)
    if [ "$theme_count" -eq 0 ]; then
        if [ -n "$existing_themes_dir" ]; then
            log_info "Seeding themes from existing release..."
            cp -a "$existing_themes_dir/." "$shared_dir/themes/"
        elif [ -d "$master_dir/themes" ]; then
            log_info "Seeding themes from master installation..."
            cp -a "$master_dir/themes/." "$shared_dir/themes/"
        fi
    fi

    [ -d "$master_dir/themes" ] || return 0

    local backup_dir
    backup_dir=$(mktemp -d)
    local backup_failed=false

    if [ -f "$shared_dir/themes/.active_theme" ]; then
        if ! cp -a "$shared_dir/themes/.active_theme" "$backup_dir/.active_theme" 2>/dev/null; then
            log_error "Failed to backup .active_theme - per-site theme selection may be lost"
            backup_failed=true
        fi
    fi

    if [ -d "$shared_dir/themes" ]; then
        while IFS= read -r file; do
            local rel="${file#"$shared_dir/themes/"}"
            if ! mkdir -p "$backup_dir/$(dirname "$rel")" 2>/dev/null; then
                log_error "Failed to create backup dir for $rel"
                backup_failed=true
                continue
            fi
            if ! cp -a "$file" "$backup_dir/$rel" 2>/dev/null; then
                log_error "Failed to backup theme config: $rel - per-site settings may be lost"
                backup_failed=true
            fi
        done < <(find "$shared_dir/themes" -type f -path "*/config/options.json" 2>/dev/null)
    fi

    if [ "$backup_failed" = true ]; then
        log_error "Some theme settings could not be backed up - proceeding with caution"
    fi

    if command -v rsync >/dev/null 2>&1; then
        rsync -a "$master_dir/themes/" "$shared_dir/themes/"
    else
        cp -a "$master_dir/themes/." "$shared_dir/themes/"
    fi

    if [ -f "$backup_dir/.active_theme" ]; then
        cp -a "$backup_dir/.active_theme" "$shared_dir/themes/.active_theme"
    fi

    if [ -d "$backup_dir" ]; then
        while IFS= read -r file; do
            local rel="${file#"$backup_dir/"}"
            mkdir -p "$shared_dir/themes/$(dirname "$rel")"
            cp -a "$file" "$shared_dir/themes/$rel"
        done < <(find "$backup_dir" -type f -path "*/config/options.json" 2>/dev/null)
    fi

    rm -rf "$backup_dir"

    if [ -n "$hestia_user" ]; then
        chown -R "$hestia_user:$hestia_user" "$shared_dir/themes"
    fi
}

# --- Master Installation ---
# Ensures master installation exists for a given version, creating if needed.
# Usage: ensure_master_exists "$version" "$release_type" "$package_id"
# Returns: 0 on success, exits with error on failure
ensure_master_exists() {
    local version="$1"
    local release_type="${2:-application}"
    local package_id="${3:-main}"

    local master_dir="$INSTALLED_DIR/v$version"

    if [ -d "$master_dir" ]; then
        return 0
    fi

    log_info "Creating master installation for v$version..."

    if [ ! -x "$BIN/a-install-master" ]; then
        handle_error 207 "a-install-master script not found"
    fi

    if ! "$BIN/a-install-master" "$version" "$release_type" "$package_id"; then
        handle_error 218 "Failed to create master installation for v$version"
    fi

    if [ ! -d "$master_dir" ]; then
        handle_error 218 "Master installation not found at $master_dir after creation"
    fi

    return 0
}

# --- Symlink Creation ---
# Creates symlinks from release to master for read-only code directories
# Usage: create_master_symlinks "$RELEASE_PATH" "$MASTER_DIR"
create_master_symlinks() {
    local release_path="$1"
    local master_dir="$2"

    # Shared read-only directories
    local shared_dirs="app config database lang modules resources routes stubs vendor"
    for dir in $shared_dirs; do
        if [ -d "$master_dir/$dir" ]; then
            ln -sfn "$master_dir/$dir" "$release_path/$dir"
        fi
    done

    # Individual files (safe to symlink)
    [ -f "$master_dir/composer.json" ] && ln -sfn "$master_dir/composer.json" "$release_path/composer.json"
    [ -f "$master_dir/composer.lock" ] && ln -sfn "$master_dir/composer.lock" "$release_path/composer.lock"
    [ -f "$master_dir/phpunit.xml" ] && ln -sfn "$master_dir/phpunit.xml" "$release_path/phpunit.xml"
    [ -f "$master_dir/bootstrap/providers.php" ] && ln -sfn "$master_dir/bootstrap/providers.php" "$release_path/bootstrap/providers.php"

    # Public heavy directories
    [ -d "$master_dir/public/build" ] && ln -sfn "$master_dir/public/build" "$release_path/public/build"
    [ -d "$master_dir/public/css" ] && ln -sfn "$master_dir/public/css" "$release_path/public/css"
    [ -d "$master_dir/public/fonts" ] && ln -sfn "$master_dir/public/fonts" "$release_path/public/fonts"
    [ -d "$master_dir/public/vendor" ] && ln -sfn "$master_dir/public/vendor" "$release_path/public/vendor"
    [ -d "$master_dir/public/images" ] && ln -sfn "$master_dir/public/images" "$release_path/public/images"
    [ -d "$master_dir/public/assets" ] && ln -sfn "$master_dir/public/assets" "$release_path/public/assets"
}

# --- Per-Site File Copies ---
# Copies files that use __DIR__ (cannot be symlinked)
# Usage: copy_dir_dependent_files "$RELEASE_PATH" "$MASTER_DIR"
copy_dir_dependent_files() {
    local release_path="$1"
    local master_dir="$2"

    # Artisan uses __DIR__ to bootstrap Laravel
    if [ -f "$master_dir/artisan" ]; then
        cp "$master_dir/artisan" "$release_path/artisan"
        chmod 755 "$release_path/artisan"
    fi

    # bootstrap/app.php uses dirname(__DIR__) for base path
    if [ -f "$master_dir/bootstrap/app.php" ]; then
        cp "$master_dir/bootstrap/app.php" "$release_path/bootstrap/app.php"
    fi

    # index.php uses __DIR__ to locate vendor/ and bootstrap/
    if [ -f "$master_dir/public/index.php" ]; then
        cp "$master_dir/public/index.php" "$release_path/public/index.php"
        chmod 644 "$release_path/public/index.php" 2>/dev/null || true
    fi
}

# --- Shared Symlinks ---
# Creates symlinks to shared directories (storage, .env, etc.)
# Usage: create_shared_symlinks "$RELEASE_PATH"
create_shared_symlinks() {
    local release_path="$1"

    # Path: releases/vX.X.X/ needs ../../ to reach public_html/shared/
    ln -sfn "../../shared/storage" "$release_path/storage"
    ln -sfn "../../shared/.env" "$release_path/.env"
    ln -sfn "../../shared/modules_statuses.json" "$release_path/modules_statuses.json"
    ln -sfn "../../shared/themes" "$release_path/themes"

    # Per-website storage link (Laravel storage:link equivalent)
    # Path from releases/vX.X.X/public/ needs ../../../ to reach public_html/shared/
    ln -sfn "../../../shared/storage/app/public" "$release_path/public/storage"
    ln -sfn "../../../shared/themes" "$release_path/public/themes"
}

# --- Unique Public Files ---
# Handles per-website unique public files (favicon, robots.txt, etc.)
# Usage: setup_unique_public_files "$RELEASE_PATH" "$SHARED_DIR" "$MASTER_DIR" "$EXISTING_DIR"
setup_unique_public_files() {
    local release_path="$1"
    local shared_dir="$2"
    local master_dir="$3"
    local existing_dir="${4:-}"

    local unique_files="favicon.ico favicon.png favicon.svg favicon-96x96.png apple-touch-icon.png web-app-manifest-192x192.png web-app-manifest-512x512.png robots.txt ads.txt sitemap.xml site.webmanifest sw.js"

    for f in $unique_files; do
        local shared_file="$shared_dir/public/$f"
        local release_file="$release_path/public/$f"
        local master_file="$master_dir/public/$f"

        # Seed from existing release or master if doesn't exist in shared
        if [ ! -f "$shared_file" ]; then
            if [ -n "$existing_dir" ] && [ -f "$existing_dir/public/$f" ] && [ ! -L "$existing_dir/public/$f" ]; then
                cp "$existing_dir/public/$f" "$shared_file" 2>/dev/null || true
            elif [ -f "$master_file" ]; then
                cp "$master_file" "$shared_file" 2>/dev/null || true
            fi
        fi

        # Remove any existing file/symlink and create proper symlink (idempotent)
        [ -e "$release_file" ] && [ ! -L "$release_file" ] && rm -f "$release_file"

        # Only create symlink if the shared file exists
        if [ -f "$shared_file" ]; then
            ln -sfn "../../../shared/public/$f" "$release_file"
        fi
    done
}

# --- Health Check ---
# Verifies critical symlinks and writable paths
# Usage: run_health_check "$RELEASE_PATH" "$SHARED_DIR"
run_health_check() {
    local release_path="$1"
    local shared_dir="$2"
    local health_check_failed=false

    # Verify critical symlinks
    if [ ! -L "$release_path/vendor" ]; then
        log_error "Health check: vendor symlink missing"
        health_check_failed=true
    fi

    if [ ! -L "$release_path/storage" ]; then
        log_error "Health check: storage symlink missing"
        health_check_failed=true
    fi

    if [ ! -L "$release_path/public/storage" ]; then
        log_error "Health check: public/storage symlink missing"
        health_check_failed=true
    fi

    # Verify writable paths
    local writable_paths=(
        "$shared_dir/storage/logs"
        "$shared_dir/storage/framework/cache"
        "$shared_dir/storage/framework/sessions"
        "$shared_dir/storage/framework/views"
        "$release_path/bootstrap/cache"
    )

    for path in "${writable_paths[@]}"; do
        if [ ! -d "$path" ]; then
            log_error "Health check: $path does not exist"
            health_check_failed=true
        fi
    done

    if [ "$health_check_failed" = true ]; then
        return 1
    fi

    log_info "Health check passed"
    return 0
}

# --- Cron Management ---
# Suspends Laravel scheduler cron job
# Usage: suspend_cron "$hestia_user" "$current_dir"
# Returns: cron job ID to resume later (empty if none)
suspend_cron() {
    local hestia_user="$1"
    local current_dir="$2"
    local existing_cron=""

    if command -v jq >/dev/null 2>&1; then
        existing_cron=$("$BIN/v-list-cron-jobs" "$hestia_user" json 2>/dev/null | jq -r ".[] | select(.command != null and (.command | contains(\"$current_dir/artisan schedule:run\"))) | .id" 2>/dev/null)
        [ -n "$existing_cron" ] && "$BIN/v-suspend-cron-job" "$hestia_user" "$existing_cron" >/dev/null 2>&1
    fi

    echo "$existing_cron"
}

# Usage: resume_cron "$hestia_user" "$cron_id"
resume_cron() {
    local hestia_user="$1"
    local cron_id="$2"

    [ -n "$cron_id" ] && "$BIN/v-unsuspend-cron-job" "$hestia_user" "$cron_id" >/dev/null 2>&1
}

# --- Queue Worker Management ---
# Restarts queue workers if managed by supervisor
# Usage: restart_queue_workers "$hestia_user" "$web_domain"
restart_queue_workers() {
    local hestia_user="$1"
    local web_domain="$2"

    if [ -x "$BIN/a-manage-queue-worker" ]; then
        "$BIN/a-manage-queue-worker" "$hestia_user" "$web_domain" restart >/dev/null 2>&1 || true
    fi
}

# --- PHP-FPM Reload ---
# Reloads PHP-FPM to clear OPcache after a release switch.
# Attempts domain-specific PHP version first, falls back to reloading all FPM services.
# Usage: restart_php_fpm "$hestia_user" "$web_domain"
restart_php_fpm() {
    local hestia_user="$1"
    local web_domain="$2"
    local php_version=""

    # Try to detect the PHP version for this domain via Hestia
    if command -v jq >/dev/null 2>&1; then
        php_version=$("$BIN/v-list-web-domain" "$hestia_user" "$web_domain" json 2>/dev/null | jq -r '.[].BACKEND // empty' 2>/dev/null)
    fi

    if [ -n "$php_version" ] && [[ "$php_version" =~ ^[0-9]+\.[0-9]+$ ]]; then
        local fpm_service="php${php_version}-fpm"
        if systemctl is-active --quiet "$fpm_service" 2>/dev/null; then
            log_info "Reloading $fpm_service to clear OPcache..."
            systemctl reload "$fpm_service" 2>/dev/null || systemctl restart "$fpm_service" 2>/dev/null || true
            return 0
        fi
    fi

    # Fallback: reload all active PHP-FPM services
    log_info "Reloading all PHP-FPM services to clear OPcache..."
    for fpm_conf in /etc/php/*/fpm/php-fpm.conf; do
        [ -f "$fpm_conf" ] || continue
        local ver
        ver=$(echo "$fpm_conf" | grep -oP '/php/\K[0-9]+\.[0-9]+')
        [ -n "$ver" ] && systemctl reload "php${ver}-fpm" 2>/dev/null || true
    done

    return 0
}

# --- Release API Key ---
# Retrieves release API key from local secure file first, then env fallback.
# Usage: get_release_api_key
get_release_api_key() {
    if [ -f "$RELEASE_API_KEY_FILE" ]; then
        local file_key=""
        file_key="$(tr -d '\r\n' < "$RELEASE_API_KEY_FILE" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
        if [ -n "$file_key" ]; then
            printf '%s' "$file_key"
            return 0
        fi
    fi

    if [ -n "${RELEASE_API_KEY:-}" ]; then
        local env_key=""
        env_key="$(printf '%s' "$RELEASE_API_KEY" | tr -d '\r\n' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
        if [ -n "$env_key" ]; then
            printf '%s' "$env_key"
            return 0
        fi
    fi

    echo ""
    return 1
}

# Stores release API key in a secure local file for script use.
# Usage: set_release_api_key "your_key"
set_release_api_key() {
    local release_api_key="$1"

    [ -z "$release_api_key" ] && return 1

    mkdir -p "$ASTERO_DATA_DIR" || return 1
    printf '%s' "$release_api_key" > "$RELEASE_API_KEY_FILE" || return 1
    chmod 600 "$RELEASE_API_KEY_FILE" 2>/dev/null || true
    chown root:root "$RELEASE_API_KEY_FILE" 2>/dev/null || true

    return 0
}
