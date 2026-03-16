#!/bin/bash
#
# Astero CLI Aliases
# Deployed to /usr/local/hestia/bin/ and symlinked to /etc/profile.d/
# Available to all bash users on the server.
#

# ── System ──────────────────────────────────────────────
alias update='sudo apt update && sudo apt upgrade -y'

# ── Artisan ─────────────────────────────────────────────
alias a="php artisan"
alias tinker="php artisan tinker"
alias routes="php artisan route:list --columns=method,uri,name,action"
alias mfs="php artisan migrate:fresh --seed"
alias migrate="php artisan migrate"
alias rollback="php artisan migrate:rollback"

# ── Astero ──────────────────────────────────────────────
alias asteroinstall="php artisan astero:install"
alias asteroupdate="npm run astero:update"
alias asterorecache="php artisan astero:recache"

# ── Dev Servers ─────────────────────────────────────────
alias dev="npm run dev"
alias build="npm run build"
alias queue="php artisan queue:work --tries=3"
alias qr="php artisan queue:restart"

# ── Testing ─────────────────────────────────────────────
alias t="php artisan test --compact"
alias tf="php artisan test --compact --filter"
alias tp="php artisan test --compact --parallel"
alias e2e="npm run test:e2e"

# ── Code Quality ────────────────────────────────────────
alias pint="vendor/bin/pint --dirty"
alias pintall="vendor/bin/pint"
alias stan="vendor/bin/phpstan analyse --memory-limit=2G"
alias lint="npm run lint"

# ── Cache & Optimization ───────────────────────────────
alias cc="php artisan optimize:clear"
alias cache="php artisan optimize"

# ── Logs & Debug ────────────────────────────────────────
alias logs="php artisan pail"
alias ql="php artisan queue:listen --tries=3"

# ── Git Shortcuts ───────────────────────────────────────
alias gs="git status"
alias gd="git diff"
alias gl="git log --oneline -20"
alias gp="git pull"
alias gc="git commit"
alias gca="git commit --amend --no-edit"
alias gco="git checkout"
alias gb="git branch"
