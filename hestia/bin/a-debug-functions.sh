#!/bin/bash
#
# File: debug-functions.sh
#
# Description: Universal debug functions for Hestia scripts that activate when
#              Laravel's APP_DEBUG is enabled. This library provides consistent
#              debug logging across all Hestia operations.
#
# Usage: Source this file in any Hestia script:
#        source "$BIN/debug-functions.sh"
#
# Functions provided:
#   - debug_init()     - Initialize debug system
#   - debug_log()      - Log debug messages
#   - debug_error()    - Log error messages with debug context
#   - debug_timing()   - Log operation timing
#   - debug_vars()     - Log variable values
#   - debug_section()  - Log section headers

# --- Debug Configuration ---

# Read Laravel debug flag from environment variable.
# This is set by a-exec when it receives --debug=1 from HestiaClient.
# When APP_DEBUG is enabled in Laravel, HestiaClient passes debug=1 to a-exec,
# which exports LARAVEL_DEBUG for all child scripts to inherit.
# Default to 0 (off) for security if not set.
LARAVEL_DEBUG="${LARAVEL_DEBUG:-0}"

# Debug log file location
DEBUG_LOG_FILE="/usr/local/hestia/data/astero/logs/astero-scripts.log"

# Current script name for logging context
SCRIPT_NAME="${0##*/}"

# --- Debug Functions ---

# Initialize debug system
debug_init() {
    if [ "$LARAVEL_DEBUG" = "1" ]; then
        # Ensure log directory exists
        mkdir -p "$(dirname "$DEBUG_LOG_FILE")"

        # Log script initialization
        debug_log "=== ${SCRIPT_NAME^^} STARTED ==="
        debug_log "Script: $0"
        debug_log "Arguments: $*"
        debug_log "Laravel debug mode: $LARAVEL_DEBUG"
        debug_log "Process ID: $$"
        debug_log "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
    fi
}

# Log debug message
debug_log() {
    if [ "$LARAVEL_DEBUG" = "1" ]; then
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        local pid=$$
        local domain_context="${domain:-${DOMAIN:-unknown}}"

        echo "[$timestamp] [PID:$pid] [$domain_context] [$SCRIPT_NAME] DEBUG: $1" >> "$DEBUG_LOG_FILE"
    fi
}

# Log error with debug context
debug_error() {
    local exit_code=$1
    local error_message=$2

    if [ "$LARAVEL_DEBUG" = "1" ]; then
        debug_log "ERROR: $error_message (Exit Code: $exit_code)"
        debug_log "Error occurred in: $SCRIPT_NAME"
        debug_log "Current directory: $(pwd)"
        debug_log "Error timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
    fi
}

# Log operation timing
debug_timing() {
    local operation_name=$1
    local start_time=$2
    local end_time=${3:-$(date +%s)}

    if [ "$LARAVEL_DEBUG" = "1" ]; then
        local duration=$((end_time - start_time))
        debug_log "TIMING: $operation_name completed in ${duration}s"
    fi
}

# Log variable values (safely handles sensitive data)
debug_vars() {
    if [ "$LARAVEL_DEBUG" = "1" ]; then
        local var_description=$1
        shift

        debug_log "VARIABLES: $var_description"
        for var in "$@"; do
            local var_name="${var%%=*}"
            local var_value="${var#*=}"

            # Sanitize sensitive variables
            case "$var_name" in
                *password*|*PASSWORD*|*secret*|*SECRET*|*key*|*KEY*)
                    debug_log "  $var_name=********"
                    ;;
                *)
                    debug_log "  $var_name=$var_value"
                    ;;
            esac
        done
    fi
}

# Log section headers for better organization
debug_section() {
    if [ "$LARAVEL_DEBUG" = "1" ]; then
        debug_log ""
        debug_log "=== $1 ==="
    fi
}

# Log command execution with output
debug_command() {
    local command_description=$1
    shift
    local command_to_run="$*"

    if [ "$LARAVEL_DEBUG" = "1" ]; then
        debug_log "COMMAND: $command_description"
        debug_log "Executing: $command_to_run"

        local start_time=$(date +%s)
        local output
        local exit_code

        # Execute command and capture output
        output=$($command_to_run 2>&1)
        exit_code=$?

        local end_time=$(date +%s)
        local duration=$((end_time - start_time))

        debug_log "Command exit code: $exit_code"
        debug_log "Command duration: ${duration}s"

        if [ $exit_code -ne 0 ] || [ ${#output} -lt 1000 ]; then
            debug_log "Command output: $output"
        else
            debug_log "Command output: ${output:0:500}... (truncated, ${#output} chars total)"
        fi

        return $exit_code
    else
        # When debug is off, just run the command normally
        "$@"
    fi
}

# Enhanced error handler that integrates with debug system
debug_handle_error() {
    local exit_code=$1
    local error_message=$2

    # Log error with debug context
    debug_error "$exit_code" "$error_message"

    # Use the debug logger if available, otherwise echo
    if [ -x "$BIN/a-log-debug" ]; then
        "$BIN/a-log-debug" "Error" "$SCRIPT_NAME" "$error_message"
    else
        echo "Error: $error_message" >&2
    fi

    # Log script termination
    if [ "$LARAVEL_DEBUG" = "1" ]; then
        debug_log "=== ${SCRIPT_NAME^^} TERMINATED WITH ERROR ==="
        debug_log "Final exit code: $exit_code"
    fi

    exit "$exit_code"
}

# Log successful script completion
debug_success() {
    if [ "$LARAVEL_DEBUG" = "1" ]; then
        debug_log "=== ${SCRIPT_NAME^^} COMPLETED SUCCESSFULLY ==="
        debug_log "Final status: SUCCESS"
        debug_log "Completion time: $(date '+%Y-%m-%d %H:%M:%S')"
    fi
}

# Cleanup function to be called on script exit
debug_cleanup() {
    if [ "$LARAVEL_DEBUG" = "1" ]; then
        debug_log "=== ${SCRIPT_NAME^^} FINISHED ==="
    fi
}

# Set up cleanup trap
trap debug_cleanup EXIT

# --- Utility Functions ---

# Check if debug mode is enabled
is_debug_enabled() {
    [ "$LARAVEL_DEBUG" = "1" ]
}

# Get debug log file path
get_debug_log_file() {
    echo "$DEBUG_LOG_FILE"
}

# --- Export Functions ---

# Make functions available to scripts that source this file
export -f debug_init
export -f debug_log
export -f debug_error
export -f debug_timing
export -f debug_vars
export -f debug_section
export -f debug_command
export -f debug_handle_error
export -f debug_success
export -f debug_cleanup
export -f is_debug_enabled
export -f get_debug_log_file
