---
name: pull-request-review
description: "Reviews GitHub pull requests from URLs like https://github.com/owner/repo/pull/123. Use when checking a PR before merge, fetching a PR branch locally, comparing it to main, running focused lint/tests, listing findings, and generating a manual review checklist for changed product areas such as theme customizer, datagrids, Ziggy routes, Inertia pages, migrations with data-loss risk, browser-sensitive admin pages, or deployment-risk changes involving queues, cron, config, and caches."
license: MIT
metadata:
  author: GitHub Copilot
---

# Pull Request Review

## When to Apply

Activate this skill when:

- The user gives a GitHub pull request URL
- The user asks to check, review, validate, or test a PR before merging
- The user wants the agent to fetch a PR branch locally and compare it with `main`
- The user wants both automated validation and a manual review checklist

## Goal

Take a PR URL, inspect the code changes locally, run the smallest relevant automated validation, and then tell the user what still needs manual review before merge.

## Expected Input

Typical input is a GitHub PR URL such as:

`https://github.com/azadshaikh/public_html/pull/4`

Extract:

- repository owner
- repository name
- PR number

Confirm the current workspace repo matches the PR repo before making local git changes. If it does not match, stop and tell the user.

## Default Workflow

### 1. Protect local work first

Before fetching or checking out the PR:

- run `git status --short --branch`
- if the worktree is dirty, do **not** overwrite it
- prefer asking the user whether to stash, commit, or stop unless the user already explicitly asked you to proceed with git operations

### 2. Fetch the PR branch

Use non-interactive git commands. Prefer:

```bash
git fetch origin
git fetch origin pull/<PR_NUMBER>/head:pr-<PR_NUMBER>
git checkout pr-<PR_NUMBER>
```

If a branch with that name already exists, inspect it before resetting or replacing it.

### 3. Compare against main

Collect:

```bash
git diff --name-status origin/main...pr-<PR_NUMBER>
git log --oneline origin/main..pr-<PR_NUMBER>
git diff origin/main...pr-<PR_NUMBER>
```

Use the changed file list to drive focused code reading and test selection.

### 4. Review with a code-review mindset

Prioritize:

- bugs and regressions
- broken route names or permission gates
- stale frontend references after backend refactors
- missing tests for changed behavior
- risky migrations or destructive data changes
- data backfills, column renames, drops, or default changes that could corrupt existing records
- deployment sequencing risk around queues, cron jobs, config changes, and cache invalidation
- UI regressions in changed workflows

Present findings first, ordered by severity, with file references.

### 5. Run focused automated validation

Choose the minimum relevant checks based on changed files.

Examples:

- React/TSX pages changed: `pnpm eslint <changed-files>`
- PHP controllers/services/requests changed: `php artisan test --compact <relevant-test-file>`
- Route or Ziggy changes: run relevant feature tests and look for stale route names in frontend files
- Datagrid or scaffold index changes: run focused feature tests for the affected resource and lint the affected page

Only run the full test suite if the user asks or if the change surface is broad enough that focused testing is not credible.

## Manual Review Checklist Generation

Always end with a manual review checklist before merge.

Build it from the changed areas, not from a generic template.

### High-signal heuristics

If the PR touches these areas, include these manual checks:

#### Theme customizer

Trigger when files mention any of:

- `theme-customizer`
- `themes/customizer`
- `customizer`
- CMS theme preview/save/reset/import/export flows

Manual checks:

- open the theme customizer page
- confirm the page still loads and the preview renders
- change a setting and verify preview updates
- save changes and reload to confirm persistence
- test reset/import/export if touched by the PR
- verify the feature has not disappeared from navigation or routing

#### Migrations and data-loss risk

Trigger when files mention:

- `database/migrations/`
- `Schema::table`
- `dropColumn`
- `renameColumn`
- `truncate`
- raw SQL data migrations or backfills
- casts, enum changes, required-field additions, or unique-index additions on existing tables

Manual checks:

- inspect every migration for destructive operations such as drops, renames, truncates, or type narrowing
- confirm existing data has a safe path when adding required columns or stricter constraints
- verify backfills are idempotent or clearly one-way, and note if a production backup is required before deploy
- confirm rollback expectations are realistic; call out irreversible migrations explicitly
- test the affected admin flows against real-looking existing records, not only fresh seeded data
- verify edited records still save correctly after the schema change and old records still render without errors
- if the PR changes slugs, foreign keys, enums, or unique constraints, confirm duplicate/conflicting legacy data is handled
- call out any deploy ordering risk such as code requiring a column before the migration is guaranteed to exist

#### Ziggy or route changes

Trigger when files mention:

- `route(`
- `ziggy`
- route files or controllers

Manual checks:

- click create/edit/show actions in the changed screens
- confirm there are no Ziggy missing-route errors in the browser
- confirm breadcrumbs and page header actions still navigate correctly

#### Datagrid or scaffold index changes

Trigger when files mention:

- `Datagrid`
- `scaffoldColumns`
- `buildScaffoldDatagridState`
- `Definition.php`

Manual checks:

- open the affected index page
- verify filters, tabs, sorting, pagination, and row actions
- confirm column widths and layout still look correct

#### Inertia page or form changes

Trigger when files mention:

- `AppLayout`
- `useForm`
- `useHttp`
- `Link`

Manual checks:

- open the page in the browser
- submit the changed form successfully
- exercise at least one validation failure path
- confirm success/error toasts and redirects still behave correctly

#### Browser-specific admin page checks

Trigger when files mention:

- admin layouts, admin pages, dashboards, settings screens, editors, datagrids, file uploads, modals, drawers, or sticky toolbars
- `position: sticky`, drag-and-drop, rich text editors, color pickers, date/time inputs, or clipboard interactions

Manual checks:

- test the changed admin screen in Chromium-based browser first, then Firefox; include Safari if the product supports it
- verify login, navigation menu, breadcrumbs, and top-level actions on the changed admin page
- confirm dropdowns, popovers, modals, drawers, and focus trapping behave correctly in each tested browser
- verify datagrid scrolling, sticky headers/toolbars, and overflow containers do not break layout or hide actions
- test file uploads, drag-and-drop, clipboard buttons, and rich text editing if present, since these often differ by browser
- confirm date, time, color, and number inputs behave correctly and submit the expected values
- verify validation messages, disabled states, loading indicators, and toast placement remain usable in each tested browser
- if the admin page is frequently used on smaller laptop widths, also test one narrow desktop viewport before merge

#### Deployment-risk changes: queues, cron, config, and cache

Trigger when files mention:

- queue jobs, listeners, workers, Horizon, or queue connection settings
- `routes/console.php`, scheduled tasks, cron-facing commands, or command signatures
- `config/*.php`, `.env.example`, feature flags, service credentials, or new environment variables
- cache keys, tagged caches, `config:cache`, route cache, view cache, or bootstrap/runtime configuration

Manual checks:

- confirm deployment order is safe: migrations first if required, then code release, then worker/scheduler restart, then cache refresh as needed
- verify new or changed queue jobs are compatible with workers that may still be running old code during rollout
- if job payload shape changed, confirm old queued jobs will not fail or corrupt data after deploy
- confirm any new scheduled command is registered, idempotent, observable, and safe if it runs twice
- verify removed or renamed commands, config keys, or env vars will not break cron, workers, or deploy scripts
- note whether `php artisan queue:restart`, Horizon restart, or worker recycle is required after merge
- note whether `php artisan config:clear`, `config:cache`, `route:clear`, or related cache rebuild steps are required
- verify cache key/schema changes will not read stale serialized data from older releases
- if third-party credentials, webhooks, or service endpoints changed, confirm there is a deployment-time checklist for secrets and connectivity

## Final Response Structure

Use this structure when reporting back to the user:

1. PR summary
2. Findings ordered by severity
3. Automated validation run
4. Manual review checklist before merge
5. Residual risks or open questions

If there are no findings, say so explicitly and still provide the manual review checklist.

## Git Safety Rules

- never use destructive git commands without explicit user approval
- prefer `git fetch`, `git checkout`, `git diff`, `git log`, `git status`
- do not amend commits unless explicitly requested
- do not discard local changes automatically

## Example Invocation

Use this skill when the user says something like:

- `check this PR https://github.com/azadshaikh/public_html/pull/4`
- `review PR 17 before merging`
- `fetch this PR and tell me what I still need to test manually`
