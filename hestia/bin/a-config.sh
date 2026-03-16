#!/bin/bash
#
# File: a-config.sh
#
# Description: Common configuration for all Astero Hestia scripts.
#              Source this file in any Hestia script to access shared settings.
#
# Usage: source "$BIN/a-config.sh" or source /usr/local/hestia/bin/a-config.sh
#

# =============================================================================
# ASTERO RELEASE SERVER CONFIGURATION
# =============================================================================

# Central release server domain - change this to switch release source
# This is used by a-sync-releases to download new versions
readonly RELEASE_DOMAIN="pkg.astero.net.in"

# Full API base URL for release operations
readonly RELEASE_API_URL="https://${RELEASE_DOMAIN}/api/release-manager/v1/releases"

# =============================================================================
# LOCAL STORAGE PATHS
# =============================================================================

# Base directory for all Astero data
readonly ASTERO_DATA_DIR="${ASTERO_DATA_DIR:-/usr/local/hestia/data/astero}"

# Local releases repository path
readonly RELEASES_BASE_DIR="${ASTERO_DATA_DIR}/releases"

# Default release type and package
readonly DEFAULT_RELEASE_TYPE="application"
readonly DEFAULT_RELEASE_PACKAGE="main"

# =============================================================================
# SCRIPT SETTINGS
# =============================================================================

# Request timeout for API calls (in seconds)
readonly API_TIMEOUT=60

# Maximum retries for failed downloads
readonly MAX_RETRIES=3

# Export variables for use in sourced scripts
export RELEASE_DOMAIN RELEASE_API_URL ASTERO_DATA_DIR RELEASES_BASE_DIR
export DEFAULT_RELEASE_TYPE DEFAULT_RELEASE_PACKAGE API_TIMEOUT MAX_RETRIES

# =============================================================================
# HELPER FUNCTIONS
# =============================================================================

# Get the releases directory for a specific type and package
get_releases_dir() {
    local type="${1:-$DEFAULT_RELEASE_TYPE}"
    local package="${2:-$DEFAULT_RELEASE_PACKAGE}"
    echo "${RELEASES_BASE_DIR}/${type}/${package}"
}

# Get the full API endpoint for latest release info
get_latest_release_url() {
    local type="${1:-$DEFAULT_RELEASE_TYPE}"
    local package="${2:-$DEFAULT_RELEASE_PACKAGE}"
    echo "${RELEASE_API_URL}/latest-update/${type}/${package}"
}

# Check if required tools are available
check_dependencies() {
    local missing=()

    for cmd in curl jq; do
        if ! command -v "$cmd" &> /dev/null; then
            missing+=("$cmd")
        fi
    done

    if [ ${#missing[@]} -gt 0 ]; then
        echo "ERROR: Missing required tools: ${missing[*]}"
        echo "Install with: apt-get install ${missing[*]}"
        return 1
    fi

    return 0
}
