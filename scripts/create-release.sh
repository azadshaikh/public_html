#!/bin/bash
set -euo pipefail

# =============================================================================
# Release Builder Script
# Creates a production-ready release zip with only the CMS module enabled
# Auto-increments patch version from composer.json by default
# Optionally uploads to Bunny Storage CDN
# =============================================================================

# -----------------------------------------------------------------------------
# Configuration
# -----------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSER_FILE="$PROJECT_ROOT/composer.json"
ENV_FILE="$PROJECT_ROOT/.env"
LOCAL_RELEASE_DIR="$PROJECT_ROOT/storage/app/releases"
MODULES_DIR="$PROJECT_ROOT/modules"
MODULES_MANIFEST_FILE="$PROJECT_ROOT/modules.json"
VITE_MANIFEST_FILE="$PROJECT_ROOT/public/build/manifest.json"

# Enabled modules for release builds
RELEASE_ENABLED_MODULES=(CMS)

# Required tools
REQUIRED_TOOLS=(pnpm composer zip rsync jq sha256sum curl)

# -----------------------------------------------------------------------------
# Colors & Logging
# -----------------------------------------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

log()         { echo -e "${BLUE}[release]${NC} $1"; }
log_success() { echo -e "${GREEN}[release]${NC} ✓ $1"; }
log_warn()    { echo -e "${YELLOW}[release]${NC} ⚠ $1"; }
log_error()   { echo -e "${RED}[release]${NC} ✗ $1"; }

# -----------------------------------------------------------------------------
# Parse Arguments
# -----------------------------------------------------------------------------
VERSION=""
DRY_RUN=false
LOCAL_ONLY=false

usage() {
    echo ""
    echo "Usage: $0 [OPTIONS] [VERSION]"
    echo ""
    echo "Creates a production-ready release zip with the CMS module."
    echo ""
    echo "Version Behavior:"
    echo "  • No version arg: Auto-increments patch (1.0.0 → 1.0.1)"
    echo "  • With version:   Uses specified version (updates composer.json)"
    echo ""
    echo "Options:"
    echo "  -l, --local      Build locally only, save to storage/app/releases, skip Bunny upload"
    echo "  -d, --dry-run    Show what would be done without executing"
    echo "  -h, --help       Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                # Build and upload to Bunny"
    echo "  $0 --local        # Build locally only (no upload)"
    echo "  $0 v1.2.0         # Use specific version, upload to Bunny"
    echo "  $0 --local v1.2.0 # Use specific version, local only"
    echo "  $0 --dry-run      # Preview without building"
    echo ""
    exit 0
}

while [[ $# -gt 0 ]]; do
    case $1 in
        -l|--local) LOCAL_ONLY=true; shift ;;
        -d|--dry-run) DRY_RUN=true; shift ;;
        -h|--help) usage ;;
        -*) log_error "Unknown option: $1"; usage ;;
        *) VERSION="$1"; shift ;;
    esac
done

# -----------------------------------------------------------------------------
# Environment Variables (for Bunny upload)
# -----------------------------------------------------------------------------
load_env_var() {
    local var_name="$1"
    local value=""

    if [[ -f "$ENV_FILE" ]]; then
        value=$(grep "^${var_name}=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '"' | tr -d "'" || true)
    fi

    echo "$value"
}

BUNNY_STORAGE_ZONE=$(load_env_var "RELEASE_BUNNY_STORAGE_ZONE")
BUNNY_STORAGE_API_KEY=$(load_env_var "RELEASE_BUNNY_STORAGE_API_KEY")
BUNNY_STORAGE_REGION=$(load_env_var "RELEASE_BUNNY_STORAGE_REGION")
BUNNY_CDN_HOSTNAME=$(load_env_var "RELEASE_BUNNY_CDN_HOSTNAME")

# Default region if not set
BUNNY_STORAGE_REGION="${BUNNY_STORAGE_REGION:-ny}"

# Bunny Storage API endpoint based on region
get_bunny_endpoint() {
    case "$BUNNY_STORAGE_REGION" in
        ny|NY) echo "https://ny.storage.bunnycdn.com" ;;
        la|LA) echo "https://la.storage.bunnycdn.com" ;;
        sg|SG) echo "https://sg.storage.bunnycdn.com" ;;
        syd|SYD) echo "https://syd.storage.bunnycdn.com" ;;
        uk|UK) echo "https://uk.storage.bunnycdn.com" ;;
        de|DE) echo "https://storage.bunnycdn.com" ;;
        *) echo "https://storage.bunnycdn.com" ;;
    esac
}

# -----------------------------------------------------------------------------
# Version Management
# -----------------------------------------------------------------------------
get_current_version() {
    jq -r '.version // "0.0.0"' "$COMPOSER_FILE"
}

increment_patch() {
    local version="$1"
    local major minor patch

    # Remove 'v' prefix if present
    version="${version#v}"

    IFS='.' read -r major minor patch <<< "$version"
    patch=$((patch + 1))
    echo "${major}.${minor}.${patch}"
}

update_composer_version() {
    local new_version="$1"
    local tmp_file
    tmp_file=$(mktemp)

    jq --arg v "$new_version" '.version = $v' "$COMPOSER_FILE" > "$tmp_file"
    mv "$tmp_file" "$COMPOSER_FILE"

    log_success "Updated composer.json version to $new_version"
}

# Determine version to use
CURRENT_VERSION=$(get_current_version)
TIMESTAMP="$(date +'%Y%m%d_%H%M%S')"

if [[ -n "$VERSION" ]]; then
    # User specified version - remove 'v' prefix for consistency
    NEW_VERSION="${VERSION#v}"
else
    # Auto-increment patch version
    NEW_VERSION=$(increment_patch "$CURRENT_VERSION")
fi

# Include timestamp in filename for security (harder to guess when hosted online)
RELEASE_NAME="${TIMESTAMP}_v${NEW_VERSION}_release"
RELEASE_BASENAME="${RELEASE_NAME}.zip"
META_BASENAME="${RELEASE_NAME}.zip.meta.json"
RELEASE_FILE=""
META_FILE=""
TEMP_DIR=""
declare -a DISCOVERED_MODULES=()
declare -a DISABLED_RELEASE_MODULES=()

# Store results for final display
FINAL_CDN_URL=""
FINAL_CHECKSUM=""
FINAL_FILE_SIZE=0
FINAL_LOCAL_PATH=""

# -----------------------------------------------------------------------------
# Cleanup Handler
# -----------------------------------------------------------------------------
cleanup() {
    if [[ -n "$TEMP_DIR" && -d "$TEMP_DIR" ]]; then
        log "Cleaning up temp dir..."
        rm -rf "$TEMP_DIR"
    fi
}
trap cleanup EXIT

# -----------------------------------------------------------------------------
# Pre-flight Checks
# -----------------------------------------------------------------------------
check_requirements() {
    log "Checking requirements..."
    local missing=()

    # If not local-only, we need curl for upload
    local tools_to_check=("${REQUIRED_TOOLS[@]}")
    if [[ "$LOCAL_ONLY" == true ]]; then
        # Remove curl from requirements for local builds
        tools_to_check=(pnpm composer zip rsync jq sha256sum)
    fi

    for tool in "${tools_to_check[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            missing+=("$tool")
        fi
    done

    if [[ ${#missing[@]} -gt 0 ]]; then
        log_error "Missing required tools: ${missing[*]}"
        echo ""
        echo "Install missing tools:"
        for tool in "${missing[@]}"; do
            case $tool in
                jq) echo "  sudo apt install jq" ;;
                sha256sum) echo "  sudo apt install coreutils" ;;
                *) echo "  Install $tool via your package manager" ;;
            esac
        done
        exit 1
    fi

    log_success "All required tools available"
}

check_bunny_credentials() {
    if [[ "$LOCAL_ONLY" == true ]]; then
        return 0
    fi

    if [[ -z "$BUNNY_STORAGE_ZONE" || -z "$BUNNY_STORAGE_API_KEY" ]]; then
        log_error "Bunny Storage credentials not configured"
        echo ""
        echo "Add the following to your .env file:"
        echo "  RELEASE_BUNNY_STORAGE_ZONE=your-storage-zone"
        echo "  RELEASE_BUNNY_STORAGE_API_KEY=your-api-key"
        echo "  RELEASE_BUNNY_STORAGE_REGION=ny"
        echo "  RELEASE_BUNNY_CDN_HOSTNAME=releases.yourdomain.com"
        echo ""
        echo "Or use --local flag to build without uploading"
        exit 1
    fi

    log_success "Bunny Storage credentials configured"
}

# -----------------------------------------------------------------------------
# Build Functions
# -----------------------------------------------------------------------------
prepare_temp_dir() {
    TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/release_build_XXXX")"
    RELEASE_FILE="$TEMP_DIR/$RELEASE_BASENAME"
    META_FILE="$TEMP_DIR/$META_BASENAME"
    log "Created temp directory: $TEMP_DIR"
}

discover_modules() {
    if [[ ! -d "$MODULES_DIR" ]]; then
        log_error "Modules directory not found: $MODULES_DIR"
        exit 1
    fi

    mapfile -t DISCOVERED_MODULES < <(
        find "$MODULES_DIR" -mindepth 2 -maxdepth 2 -name module.json -print \
            | sort \
            | while read -r manifest_path; do
                jq -r '.name // empty' "$manifest_path"
            done
    )

    if [[ ${#DISCOVERED_MODULES[@]} -eq 0 ]]; then
        log_error "No modules were discovered from $MODULES_DIR"
        exit 1
    fi
}

module_is_enabled_for_release() {
    local candidate="$1"

    for enabled_module in "${RELEASE_ENABLED_MODULES[@]}"; do
        if [[ "$enabled_module" == "$candidate" ]]; then
            return 0
        fi
    done

    return 1
}

validate_release_modules() {
    local missing=()

    for enabled_module in "${RELEASE_ENABLED_MODULES[@]}"; do
        local found=false

        for discovered_module in "${DISCOVERED_MODULES[@]}"; do
            if [[ "$discovered_module" == "$enabled_module" ]]; then
                found=true
                break
            fi
        done

        if [[ "$found" == false ]]; then
            missing+=("$enabled_module")
        fi
    done

    if [[ ${#missing[@]} -gt 0 ]]; then
        log_error "Release-enabled modules not found: ${missing[*]}"
        exit 1
    fi
}

build_release_module_manifest() {
    local manifest_json='{}'
    DISABLED_RELEASE_MODULES=()

    for discovered_module in "${DISCOVERED_MODULES[@]}"; do
        local status="disabled"

        if module_is_enabled_for_release "$discovered_module"; then
            status="enabled"
        else
            DISABLED_RELEASE_MODULES+=("$discovered_module")
        fi

        manifest_json=$(jq \
            --arg module "$discovered_module" \
            --arg status "$status" \
            '. + {($module): $status}' <<< "$manifest_json")
    done

    printf '%s\n' "$manifest_json"
}

# Run a command quietly, only showing output on failure
run_quiet() {
    local label="$1"
    shift
    local output_file
    output_file=$(mktemp)

    if "$@" > "$output_file" 2>&1; then
        rm -f "$output_file"
        return 0
    else
        local exit_code=$?
        log_error "$label failed with exit code $exit_code"
        echo ""
        echo -e "${YELLOW}─── Command Output ───${NC}"
        cat "$output_file"
        echo -e "${YELLOW}──────────────────────${NC}"
        echo ""
        rm -f "$output_file"
        return $exit_code
    fi
}

# Build assets in project root BEFORE copying (faster - no need to copy node_modules)
build_assets() {
    log "Installing node dependencies from lockfile (pnpm install --frozen-lockfile)..."
    (cd "$PROJECT_ROOT" && run_quiet "pnpm install --frozen-lockfile" pnpm install --frozen-lockfile)
    log_success "Node dependencies installed"

    log "Running pnpm build:prod with CMS-only module pages..."
    (
        cd "$PROJECT_ROOT" && run_quiet \
            "pnpm build:prod" \
            env VITE_RELEASE_ENABLED_MODULES=CMS pnpm run build:prod
    )

    if [[ ! -f "$VITE_MANIFEST_FILE" ]]; then
        log_error "Vite build manifest not found after build: $VITE_MANIFEST_FILE"
        exit 1
    fi

    for discovered_module in "${DISCOVERED_MODULES[@]}"; do
        if module_is_enabled_for_release "$discovered_module"; then
            continue
        fi

        if grep -q "modules/${discovered_module}/resources/js/pages/" "$VITE_MANIFEST_FILE"; then
            log_error "Vite manifest still contains pages for excluded module: ${discovered_module}"
            exit 1
        fi
    done

    log_success "Assets built"
}

copy_project() {
    log "Copying project files..."

    local rsync_args=(
        -a
        --exclude ".git"
        --exclude "node_modules"
        --exclude "public/release"
        --exclude "public/favicon.ico"
        --exclude "public/favicon.svg"
        --exclude "public/favicon-*.png"
        --exclude "public/apple-touch-icon.png"
        --exclude "public/web-app-manifest-*.png"
        --exclude "public/site.webmanifest"
        --exclude "release_build_*"
    )

    for discovered_module in "${DISCOVERED_MODULES[@]}"; do
        if ! module_is_enabled_for_release "$discovered_module"; then
            rsync_args+=(--exclude "modules/${discovered_module}")
        fi
    done

    rsync \
        "${rsync_args[@]}" \
        "$PROJECT_ROOT"/ "$TEMP_DIR"/
    log_success "Project copied"
}

configure_modules() {
    log "Writing release modules.json manifest..."
    build_release_module_manifest > "$TEMP_DIR/modules.json"
    log_success "Module configuration updated"
}

install_dependencies() {
    log "Installing composer dependencies (no-dev)..."
    (cd "$TEMP_DIR" && run_quiet "composer install" composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist)

    log "Clearing cached Laravel bootstrap artifacts in release copy..."
    (cd "$TEMP_DIR" && run_quiet "php artisan optimize:clear" php artisan optimize:clear --ansi)

    log_success "Dependencies installed"
}

# Note: vendor:publish is NOT run during release creation.
# Published assets (public/vendor/, config files, etc.) should already exist
# in the project directory from development. They get copied during rsync.
# Run `php artisan vendor:publish --all` in your dev environment if needed.

create_exclude_file() {
    cat > "$TEMP_DIR/.zip-excludes" << 'EOF'
node_modules/*
.git/*
public/release/*
tests/*
storage/*
.env*
*.log
*.tmp
*.swp
*.swo
.idea/*
.vscode/*
.DS_Store
public/hot
public/storage/*
public/sitemaps/*
public/favicon.ico
public/favicon.svg
public/favicon-*.png
public/apple-touch-icon.png
public/web-app-manifest-*.png
public/site.webmanifest
.ai/*
.agent/*
.clinerules/*
.cursor/*
.github/*
docs/*
examples/*
memory-bank/*
scripts/*
ssl/*
stubs/*
tasks/*
.editorconfig
.gitattributes
.gitignore
.gitmessage
.nvmrc
.prettierignore
.mcp.json
.cursorignore
.cline-mcp-settings.json
prettier.config.mjs
phpstan.neon
commitlint.config.js
package-lock.json
pnpm-lock.yaml
package.json
phpunit.xml
postcss.config.js
vite-module-loader.js
vite.config.js
vite.config.ts
tsconfig.json
eslint.config.js
skills-lock.json
*.md
tools/*
hestia/*
.gemini/*
.kiro/*
.zip-excludes
EOF

    for disabled_module in "${DISABLED_RELEASE_MODULES[@]}"; do
        printf 'modules/%s/*\n' "$disabled_module" >> "$TEMP_DIR/.zip-excludes"
    done
}

create_release_zip() {
    log "Creating release zip..."

    create_exclude_file

    (cd "$TEMP_DIR" && zip -rq "$RELEASE_FILE" . -x@.zip-excludes)

    # Verify archive integrity
    if ! unzip -t "$RELEASE_FILE" >/dev/null 2>&1; then
        log_error "Archive verification failed - zip may be corrupted"
        log_error "Temp directory preserved for debugging: $TEMP_DIR"
        # Disable cleanup trap so temp dir is preserved for debugging
        trap - EXIT
        exit 1
    fi

    # Get file size in human-readable format
    local size
    if command -v numfmt &> /dev/null; then
        size=$(stat -c%s "$RELEASE_FILE" | numfmt --to=iec-i --suffix=B)
    else
        size=$(du -h "$RELEASE_FILE" | cut -f1)
    fi

    log_success "Release created: $RELEASE_FILE ($size)"
}

generate_checksum() {
    log "Generating SHA256 checksum..."

    local checksum
    checksum=$(sha256sum "$RELEASE_FILE" | cut -d' ' -f1)

    # Get file size in bytes
    FINAL_FILE_SIZE=$(stat -c%s "$RELEASE_FILE")

    FINAL_CHECKSUM="$checksum"
    log_success "Checksum: $checksum"
}

generate_meta_json() {
    log "Generating meta.json..."

    local created_at
    created_at=$(date -Iseconds)

    cat > "$META_FILE" << EOF
{
  "file_name": "${RELEASE_NAME}.zip",
  "checksum": "sha256:${FINAL_CHECKSUM}",
  "file_size": ${FINAL_FILE_SIZE},
  "version": "${NEW_VERSION}",
  "created_at": "${created_at}"
}
EOF

    log_success "Meta file created"
}

persist_local_artifacts() {
    if [[ "$LOCAL_ONLY" != true ]]; then
        return 0
    fi

    mkdir -p "$LOCAL_RELEASE_DIR"
    cp "$RELEASE_FILE" "$LOCAL_RELEASE_DIR/$RELEASE_BASENAME"
    cp "$META_FILE" "$LOCAL_RELEASE_DIR/$META_BASENAME"

    FINAL_LOCAL_PATH="$LOCAL_RELEASE_DIR/$RELEASE_BASENAME"
    log_success "Local artifacts saved to: $LOCAL_RELEASE_DIR"
}

upload_to_bunny() {
    if [[ "$LOCAL_ONLY" == true ]]; then
        log_warn "Skipping upload (--local flag)"
        return 0
    fi

    log "Uploading to Bunny Storage..."

    local endpoint
    endpoint=$(get_bunny_endpoint)
    local upload_url="${endpoint}/${BUNNY_STORAGE_ZONE}/application/main/${RELEASE_NAME}.zip"

    local http_code
    http_code=$(curl -s -o /dev/null -w "%{http_code}" \
        --request PUT \
        --header "AccessKey: ${BUNNY_STORAGE_API_KEY}" \
        --header "Content-Type: application/octet-stream" \
        --data-binary @"$RELEASE_FILE" \
        "$upload_url")

    if [[ "$http_code" == "201" || "$http_code" == "200" ]]; then
        log_success "Uploaded successfully"

        # Upload the meta.json sidecar file
        local meta_url="${endpoint}/${BUNNY_STORAGE_ZONE}/application/main/${RELEASE_NAME}.zip.meta.json"
        curl -s -o /dev/null \
            --request PUT \
            --header "AccessKey: ${BUNNY_STORAGE_API_KEY}" \
            --header "Content-Type: application/json" \
            --data-binary @"$META_FILE" \
            "$meta_url"

        # Set CDN URL
        if [[ -n "$BUNNY_CDN_HOSTNAME" ]]; then
            FINAL_CDN_URL="https://${BUNNY_CDN_HOSTNAME}/application/main/${RELEASE_NAME}.zip"
        else
            FINAL_CDN_URL="${endpoint}/${BUNNY_STORAGE_ZONE}/application/main/${RELEASE_NAME}.zip"
        fi
    else
        log_error "Upload failed with HTTP status: $http_code"
        exit 1
    fi
}

# -----------------------------------------------------------------------------
# Main
# -----------------------------------------------------------------------------
main() {
    local start_time
    start_time=$(date +%s)

    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  Release Builder${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""

    cd "$PROJECT_ROOT"

    echo -e "  Current version: ${CYAN}${CURRENT_VERSION}${NC}"
    echo -e "  New version:     ${GREEN}${NEW_VERSION}${NC}"
    echo -e "  Release file:    ${CYAN}${RELEASE_NAME}.zip${NC}"
    if [[ "$LOCAL_ONLY" == true ]]; then
        echo -e "  Upload:          ${YELLOW}Disabled (--local)${NC}"
    else
        echo -e "  Upload:          ${GREEN}Bunny Storage${NC}"
    fi
    echo ""

    discover_modules
    validate_release_modules

    if [[ "$DRY_RUN" == true ]]; then
        log_warn "DRY RUN MODE - No changes will be made"
        echo ""
        echo "Would create temporary artifact: /tmp/.../$RELEASE_BASENAME"
        if [[ "$LOCAL_ONLY" == true ]]; then
            echo "Would save local copy to: $LOCAL_RELEASE_DIR/$RELEASE_BASENAME"
        fi
        echo "Would update composer.json version: $CURRENT_VERSION → $NEW_VERSION"
        echo "Would include modules: ${RELEASE_ENABLED_MODULES[*]}"
        echo "Would build frontend pages only for modules: ${RELEASE_ENABLED_MODULES[*]}"

        local excluded_modules=()
        for discovered_module in "${DISCOVERED_MODULES[@]}"; do
            if ! module_is_enabled_for_release "$discovered_module"; then
                excluded_modules+=("$discovered_module")
            fi
        done

        if [[ ${#excluded_modules[@]} -gt 0 ]]; then
            echo "Would exclude modules: ${excluded_modules[*]}"
        fi

        if [[ "$LOCAL_ONLY" != true ]]; then
            echo "Would upload to: Bunny Storage (${BUNNY_STORAGE_ZONE:-not configured})"
        fi
        echo ""
        echo "Steps that would execute:"
        echo "  1. Check requirements"
        echo "  2. Update composer.json version to $NEW_VERSION"
        echo "  3. Run pnpm install --frozen-lockfile"
        echo "  4. Run pnpm build:prod"
        echo "  5. Copy project to temp directory"
        echo "  6. Configure CMS-only modules"
        echo "  7. Install composer dependencies (no-dev)"
        echo "  8. Create release zip"
        echo "  9. Generate SHA256 checksum"
        if [[ "$LOCAL_ONLY" != true ]]; then
            echo "  10. Upload to Bunny Storage"
        fi
        echo ""
        exit 0
    fi

    check_requirements
    check_bunny_credentials

    # Update version in composer.json before building
    log "Updating version: $CURRENT_VERSION → $NEW_VERSION"
    update_composer_version "$NEW_VERSION"

    # Build assets in project root first (faster - no need to copy node_modules)
    build_assets

    prepare_temp_dir
    copy_project
    configure_modules
    install_dependencies
    create_release_zip
    generate_checksum
    generate_meta_json
    persist_local_artifacts
    upload_to_bunny
    cleanup
    TEMP_DIR=""

    local end_time elapsed_time
    end_time=$(date +%s)
    elapsed_time=$((end_time - start_time))

    echo ""
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}  ✓ Release v${NEW_VERSION} completed successfully in ${elapsed_time}s${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""

    # Show final details
    if [[ -n "$FINAL_CDN_URL" ]]; then
        echo -e "  ${CYAN}CDN URL:${NC}  $FINAL_CDN_URL"
    fi
    if [[ -n "$FINAL_CHECKSUM" ]]; then
        echo -e "  ${CYAN}Checksum:${NC} sha256:${FINAL_CHECKSUM:0:16}..."
    fi
    if [[ -n "$FINAL_LOCAL_PATH" ]]; then
        echo -e "  ${CYAN}Local file:${NC} $FINAL_LOCAL_PATH"
    fi
    echo ""
}

main
