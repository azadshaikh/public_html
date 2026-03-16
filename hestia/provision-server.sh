#!/bin/bash
#
# Script: provision-server.sh
#
# Description: Provisions a fresh HestiaCP server for Astero website hosting.
#              Deploys Hestia scripts from the extracted release, initializes the local
#              releases repository, and prepares the server for website provisioning.
#
# Prerequisites:
#   - Fresh HestiaCP installation (https://hestiacp.com)
#   - Root SSH access to the server
#   - Release zip downloaded and extracted
#
# Usage:
#   1. Download the latest release zip to your server
#   2. Extract it to a temporary folder:
#      unzip release.zip -d /tmp/astero-release
#   3. Run this script from the hestia folder:
#      cd /tmp/astero-release/hestia
#      sudo ./provision-server.sh [options]
#
# Options:
#   --force           Re-provision even if scripts already exist
#   --release-zip     Path to release zip file to store in local repository
#   --version         Version string for the release (e.g., 1.0.0)
#   --help            Show this help message
#
# What This Script Does:
#   1. Verifies root access and HestiaCP installation
#   2. Deploys bin/* scripts to /usr/local/hestia/bin/
#   3. Deploys data/templates/* to /usr/local/hestia/data/templates/
#   4. Creates local releases repository structure
#   5. Optionally stores the release zip for website provisioning
#   6. Sets correct file permissions
#
# After Running This Script:
#   1. Create an access key for the admin user:
#      v-add-access-key admin '*' asterobuilder json
#
#   2. Copy the ACCESS_KEY_ID and SECRET_ACCESS_KEY from the output
#
#   3. Add the server in Astero Platform:
#      - Go to Platform > Servers > Add Server
#      - Enter the server IP, port (8443), and access credentials
#
#   4. The server is now ready to provision websites!
#
# Exit Codes:
#   0   - Success
#   1   - Invalid arguments or help requested
#   2   - Not running as root
#   3   - HestiaCP not installed
#   4   - Already provisioned (use --force to override)
#   5   - Missing required files (bin/ folder not found)
#   6   - Release zip not found (when --release-zip specified)
#   7   - (reserved)
#   8   - (reserved)
#   9   - File operation failed
#
# For more information, see: README.md
#

set -euo pipefail

# =============================================================================
# CONFIGURATION
# =============================================================================

# Get the directory where this script is located (should be hestia/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Release package configuration
RELEASE_TYPE="application"
RELEASE_PACKAGE="main"

# Hestia paths
HESTIA_DIR="/usr/local/hestia"
HESTIA_BIN="$HESTIA_DIR/bin"
HESTIA_DATA="$HESTIA_DIR/data"

# Astero data paths
ASTERO_DATA_DIR="$HESTIA_DATA/astero"
ASTERO_RELEASES_DIR="$ASTERO_DATA_DIR/releases"
ASTERO_LOGS_DIR="$ASTERO_DATA_DIR/logs"

# =============================================================================
# HELPER FUNCTIONS
# =============================================================================

log() {
    echo "[provision] $1"
}

log_success() {
    echo -e "[provision] \033[0;32m✓\033[0m $1"
}

log_error() {
    echo -e "[provision] \033[0;31m✗\033[0m $1" >&2
}

log_warn() {
    echo -e "[provision] \033[0;33m!\033[0m $1"
}

show_help() {
    head -60 "$0" | tail -55 | sed 's/^#//' | sed 's/^ //'
    exit 1
}

# =============================================================================
# ARGUMENT PARSING
# =============================================================================

FORCE_PROVISION=false
RELEASE_ZIP_PATH=""
RELEASE_VERSION=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --force)
            FORCE_PROVISION=true
            shift
            ;;
        --release-zip)
            RELEASE_ZIP_PATH="$2"
            shift 2
            ;;
        --version)
            RELEASE_VERSION="$2"
            shift 2
            ;;
        --help|-h)
            show_help
            ;;
        *)
            log_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# =============================================================================
# VERIFICATION
# =============================================================================

log "Starting Astero server provisioning..."
echo ""

# Check root access
log "Checking root access..."
if [ "$(id -u)" -ne 0 ]; then
    log_error "This script must be run as root"
    echo "  Try: sudo $0"
    exit 2
fi
log_success "Running as root"

# Check HestiaCP installation
log "Checking HestiaCP installation..."
if [ ! -d "$HESTIA_DIR" ]; then
    log_error "HestiaCP is not installed"
    echo "  Install HestiaCP first: https://hestiacp.com"
    exit 3
fi
if [ ! -f "$HESTIA_DIR/conf/hestia.conf" ]; then
    log_error "HestiaCP configuration not found"
    exit 3
fi
log_success "HestiaCP is installed"

# Check if already provisioned
log "Checking existing installation..."
if [ -f "$HESTIA_BIN/a-sync-releases" ] && [ "$FORCE_PROVISION" = false ]; then
    log_warn "Server appears to be already provisioned"
    echo ""
    echo "  Existing Astero scripts found in $HESTIA_BIN"
    echo "  Use --force to re-provision and overwrite existing scripts"
    echo ""
    exit 4
fi
if [ "$FORCE_PROVISION" = true ]; then
    log_warn "Force mode enabled - will overwrite existing scripts"
else
    log_success "Fresh installation detected"
fi

# Check dependencies
log "Checking dependencies..."
MISSING_DEPS=()
for cmd in unzip; do
    if ! command -v "$cmd" &>/dev/null; then
        MISSING_DEPS+=("$cmd")
    fi
done

if [ ${#MISSING_DEPS[@]} -gt 0 ]; then
    log_error "Missing required dependencies: ${MISSING_DEPS[*]}"
    echo ""
    echo "  Install them with:"
    echo "  apt-get update && apt-get install -y ${MISSING_DEPS[*]}"
    echo ""
    exit 5
fi
log_success "All dependencies available"

# Check that we're running from the hestia folder with bin/ scripts
log "Checking local hestia files..."
if [ ! -d "$SCRIPT_DIR/bin" ]; then
    log_error "bin/ folder not found in $SCRIPT_DIR"
    echo ""
    echo "  This script should be run from the extracted hestia/ folder."
    echo "  Expected structure:"
    echo "    hestia/"
    echo "      bin/           <- must exist"
    echo "      data/"
    echo "      provision-server.sh"
    echo ""
    exit 5
fi
SCRIPT_COUNT_LOCAL=$(ls -1 "$SCRIPT_DIR/bin/" 2>/dev/null | wc -l)
if [ "$SCRIPT_COUNT_LOCAL" -eq 0 ]; then
    log_error "No scripts found in $SCRIPT_DIR/bin/"
    exit 5
fi
log_success "Found $SCRIPT_COUNT_LOCAL scripts in local bin/ folder"

echo ""

# =============================================================================
# DEPLOY SCRIPTS
# =============================================================================

log "Deploying Hestia scripts..."

# Deploy bin scripts
log "  Copying scripts to $HESTIA_BIN..."
cp -f "$SCRIPT_DIR/bin/"* "$HESTIA_BIN/" || {
    log_error "Failed to copy bin scripts"
    exit 9
}

# Set permissions
chmod 755 "$HESTIA_BIN/a-"* 2>/dev/null || true

# Fix line endings (in case of Windows CRLF)
if command -v dos2unix &>/dev/null; then
    dos2unix -q "$HESTIA_BIN/a-"* 2>/dev/null || true
else
    # Fallback: use sed to remove carriage returns
    for script in "$HESTIA_BIN/a-"*; do
        sed -i 's/\r$//' "$script" 2>/dev/null || true
    done
fi

log_success "Deployed $SCRIPT_COUNT_LOCAL scripts to $HESTIA_BIN"

# Symlink aliases to /etc/profile.d/ so they're available to all users
if [ -f "$HESTIA_BIN/a-astero-aliases.sh" ]; then
    ln -sf "$HESTIA_BIN/a-astero-aliases.sh" /etc/profile.d/astero-aliases.sh
    log_success "Symlinked aliases to /etc/profile.d/astero-aliases.sh"
fi

# Deploy data templates
if [ -d "$SCRIPT_DIR/data/templates" ]; then
    log "  Copying templates to $HESTIA_DATA/templates..."

    # Deploy nginx templates (web templates for different website states)
    if [ -d "$SCRIPT_DIR/data/templates/web/nginx" ]; then
        mkdir -p "$HESTIA_DATA/templates/web/nginx/php-fpm"
        cp -rf "$SCRIPT_DIR/data/templates/web/nginx/"* "$HESTIA_DATA/templates/web/nginx/" 2>/dev/null || true
        NGINX_COUNT=$(find "$SCRIPT_DIR/data/templates/web/nginx" -name "*.tpl" -o -name "*.stpl" 2>/dev/null | wc -l)
        log_success "Deployed $NGINX_COUNT nginx templates"
    fi

    # Deploy PHP-FPM backend templates (pool configs with open_basedir for master installation)
    if [ -d "$SCRIPT_DIR/data/templates/web/php-fpm" ]; then
        mkdir -p "$HESTIA_DATA/templates/web/php-fpm"
        cp -rf "$SCRIPT_DIR/data/templates/web/php-fpm/"* "$HESTIA_DATA/templates/web/php-fpm/" 2>/dev/null || true
        PHPFPM_COUNT=$(find "$SCRIPT_DIR/data/templates/web/php-fpm" -name "*.tpl" 2>/dev/null | wc -l)
        log_success "Deployed $PHPFPM_COUNT PHP-FPM backend templates"
    fi
else
    log "  No templates found in release (optional)"
fi

echo ""

# =============================================================================
# SETUP SUPERVISOR FOR QUEUE WORKERS
# =============================================================================

log "Setting up Supervisor for queue workers..."

# Check if Supervisor is installed
if ! command -v supervisorctl &>/dev/null; then
    log "  Installing Supervisor..."
    apt-get update -qq
    apt-get install -y supervisor >/dev/null 2>&1 || {
        log_warn "Failed to install Supervisor (non-fatal)"
        log "  Queue workers will not be available. Install manually: apt install supervisor"
    }
fi

if command -v supervisorctl &>/dev/null; then
    # Enable and start Supervisor service
    systemctl enable supervisor >/dev/null 2>&1 || true
    systemctl start supervisor >/dev/null 2>&1 || true

    # Reload Supervisor to pick up any existing configs
    supervisorctl reread >/dev/null 2>&1 || true
    supervisorctl update >/dev/null 2>&1 || true

    log_success "Supervisor installed and running"
    log "  Queue worker configs will be created in: /etc/supervisor/conf.d/"
else
    log_warn "Supervisor not available - queue workers will use cron fallback"
fi

echo ""

# =============================================================================
# INSTALL IMAGE OPTIMIZATION TOOLCHAIN
# =============================================================================

log "Installing image optimization tools..."

apt-get update -qq

if apt-get install -y -qq jpegoptim optipng pngquant gifsicle libavif-bin >/dev/null 2>&1; then
    log_success "Installed image optimization packages (jpegoptim, optipng, pngquant, gifsicle, libavif-bin)"
else
    log_warn "Failed to install one or more image optimization packages"
    log "  Install manually: apt install jpegoptim optipng pngquant gifsicle libavif-bin"
fi

# Install snapd when snap is missing (required for svgo).
if ! command -v snap >/dev/null 2>&1; then
    if apt-get install -y -qq snapd >/dev/null 2>&1; then
        if command -v systemctl >/dev/null 2>&1; then
            systemctl enable snapd snapd.socket >/dev/null 2>&1 || true
            systemctl start snapd snapd.socket >/dev/null 2>&1 || true
        fi
        log_success "snapd installed"
    else
        log_warn "Failed to install snapd"
        log "  Install manually: apt install snapd"
    fi
fi

if command -v svgo >/dev/null 2>&1; then
    log_success "svgo already installed"
elif command -v snap >/dev/null 2>&1; then
    if snap list svgo >/dev/null 2>&1 || snap install svgo >/dev/null 2>&1; then
        log_success "svgo installed via snap"
    else
        log_warn "Failed to install svgo via snap"
        log "  Install manually: snap install svgo"
    fi
else
    log_warn "snap is still unavailable after snapd install attempt; skipping svgo installation"
    log "  Install manually: apt install snapd && snap install svgo"
fi

echo ""

# =============================================================================
# SETUP LOCAL RELEASES REPOSITORY
# =============================================================================

log "Setting up local releases repository..."

# Create directory structure
mkdir -p "$ASTERO_RELEASES_DIR/$RELEASE_TYPE/$RELEASE_PACKAGE" || {
    log_error "Failed to create releases directory"
    exit 9
}
mkdir -p "$ASTERO_LOGS_DIR" || {
    log_error "Failed to create logs directory"
    exit 9
}

log_success "Directory structure created"

# If release zip path provided, copy it to local repository
if [ -n "$RELEASE_ZIP_PATH" ]; then
    if [ ! -f "$RELEASE_ZIP_PATH" ]; then
        log_error "Release zip not found: $RELEASE_ZIP_PATH"
        exit 6
    fi

    # Determine version
    if [ -z "$RELEASE_VERSION" ]; then
        # Try to extract version from filename (e.g., v1.0.0.zip or 1.0.0_release.zip)
        RELEASE_VERSION=$(basename "$RELEASE_ZIP_PATH" | grep -oP '\d+\.\d+\.\d+' | head -1) || true
        if [ -z "$RELEASE_VERSION" ]; then
            log_warn "Could not determine version from filename"
            log "  Use --version to specify the release version"
            log "  Skipping release storage (you can sync releases later)"
        fi
    fi

    if [ -n "$RELEASE_VERSION" ]; then
        log "  Storing release v$RELEASE_VERSION in local repository..."

        RELEASE_DEST="$ASTERO_RELEASES_DIR/$RELEASE_TYPE/$RELEASE_PACKAGE/v$RELEASE_VERSION.zip"
        cp "$RELEASE_ZIP_PATH" "$RELEASE_DEST" || {
            log_error "Failed to copy release to local repository"
            exit 9
        }

        # Set as active release
        CURRENT_LINK="$ASTERO_RELEASES_DIR/$RELEASE_TYPE/$RELEASE_PACKAGE/current"
        rm -f "$CURRENT_LINK" 2>/dev/null || true
        ln -s "v$RELEASE_VERSION.zip" "$CURRENT_LINK" || {
            log_error "Failed to set active release"
            exit 9
        }

        log_success "Release v$RELEASE_VERSION stored and set as active"
    fi
else
    log_warn "No release zip specified"
    echo "  To provision websites, you'll need to sync releases:"
    echo "  a-sync-releases application main --set-active"
fi

echo ""

# =============================================================================
# VERIFICATION
# =============================================================================

log "Verifying installation..."

# Test that scripts are executable
if [ -x "$HESTIA_BIN/a-sync-releases" ]; then
    log_success "a-sync-releases is executable"
else
    log_warn "a-sync-releases may not be executable"
fi

if [ -x "$HESTIA_BIN/a-prepare-astero" ]; then
    log_success "a-prepare-astero is executable"
else
    log_warn "a-prepare-astero may not be executable"
fi

# List deployed scripts
echo ""
log "Deployed Astero scripts:"
ls -1 "$HESTIA_BIN/a-"* 2>/dev/null | while read -r script; do
    echo "  - $(basename "$script")"
done

echo ""

# =============================================================================
# SUCCESS
# =============================================================================

echo "=============================================="
echo ""
log_success "Server provisioning complete!"
echo ""
echo "  Scripts Location: $HESTIA_BIN/a-*"
echo "  Releases Repo: $ASTERO_RELEASES_DIR"
if [ -n "$RELEASE_VERSION" ] && [ -n "$RELEASE_ZIP_PATH" ]; then
    echo "  Active Release: v$RELEASE_VERSION"
fi
echo ""
echo "=============================================="
echo ""
echo "NEXT STEPS:"
echo ""
if [ -z "$RELEASE_ZIP_PATH" ] || [ -z "$RELEASE_VERSION" ]; then
    echo "1. Sync a release for website provisioning:"
    echo ""
    echo "   a-sync-releases application main --set-active"
    echo ""
    echo "2. Create an access key for API authentication:"
else
    echo "1. Create an access key for API authentication:"
fi
echo ""
echo "   v-add-access-key admin '*' asterobuilder json"
echo ""
echo "   This will output ACCESS_KEY_ID and SECRET_ACCESS_KEY."
echo "   Save these credentials securely!"
echo ""
if [ -z "$RELEASE_ZIP_PATH" ] || [ -z "$RELEASE_VERSION" ]; then
    echo "3. Add this server in Astero:"
else
    echo "2. Add this server in Astero:"
fi
echo ""
echo "   - Go to Platform > Servers > Add Server"
echo "   - Server IP: $(hostname -I | awk '{print $1}')"
echo "   - Port: 8443"
echo "   - Access Key ID: (from above)"
echo "   - Secret Key: (from above)"
echo ""
if [ -z "$RELEASE_ZIP_PATH" ] || [ -z "$RELEASE_VERSION" ]; then
    echo "4. Sync server info and you're ready to provision websites!"
else
    echo "3. Sync server info and you're ready to provision websites!"
fi
echo ""
echo "=============================================="
echo ""

exit 0
