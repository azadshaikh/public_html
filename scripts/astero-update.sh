#!/usr/bin/env bash
set -euo pipefail
trap 'echo "[astero:update] Failed at line $LINENO while running: $BASH_COMMAND" >&2' ERR

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

DRY_RUN=false

for arg in "$@"; do
    case "$arg" in
        --dry-run)
            DRY_RUN=true
            ;;
        *)
            echo "[astero:update] Unknown option: $arg"
            echo "Usage: $0 [--dry-run]"
            exit 1
            ;;
    esac
done

run_step() {
    local description="$1"
    shift

    echo "[astero:update] $description"

    if [[ "$DRY_RUN" == true ]]; then
        echo "[astero:update] DRY RUN: $*"
        return 0
    fi

    "$@"
}

check_requirements() {
    local required_tools=(php composer pnpm)

    for tool in "${required_tools[@]}"; do
        if ! command -v "$tool" >/dev/null 2>&1; then
            echo "[astero:update] Missing required tool: $tool"
            exit 1
        fi
    done
}

detect_app_env() {
    local env_value="${APP_ENV:-}"

    if [[ -z "$env_value" && -f "$PROJECT_ROOT/.env" ]]; then
        env_value="$(
            grep -E '^APP_ENV=' "$PROJECT_ROOT/.env" | tail -n 1 | cut -d '=' -f 2- \
                | tr -d '"' | tr -d "'" | tr -d '[:space:]'
        )"
    fi

    if [[ -z "$env_value" ]]; then
        env_value="production"
    fi

    echo "$env_value"
}

main() {
    local app_env

    cd "$PROJECT_ROOT"
    check_requirements

    app_env="$(detect_app_env)"
    echo "[astero:update] Detected APP_ENV=$app_env"

    if [[ "$app_env" == "local" ]]; then
        run_step "Installing PHP dependencies (local)" \
            composer install --no-interaction
    else
        run_step "Installing PHP dependencies (production)" \
            composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    fi

    run_step "Running database migrations" \
        php artisan migrate --force --no-interaction

    run_step "Installing Node dependencies (lockfile)" \
        pnpm install --frozen-lockfile

    run_step "Building frontend assets" \
        pnpm run build:prod

    run_step "Rebuilding Astero caches" \
        php artisan astero:recache --no-interaction

    run_step "Restarting queue workers" \
        php artisan queue:restart --no-interaction

    echo "[astero:update] Update completed."
}

main "$@"
