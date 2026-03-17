# Local Dev Setup Instructions

## Initial Setup (Git + GitHub CLI)

### Install `git` and `gh`

On Ubuntu/Debian:

```bash
sudo apt update
sudo apt install -y git gh
```

### Authenticate with GitHub

```bash
gh auth login
```

### Configure Git name/email

```bash
git config --global user.name "Azad Shaikh"
git config --global user.email "mohd.azad.shaikh@gmail.com"
git config --global init.defaultBranch main
git config --global pull.rebase false
```

### Ensure new repos use `main` (not `master`)

```bash
git config --global init.defaultBranch main
```

## Repo Setup (init + upstream main)

If you already have the code directory (or you created it manually), initialize it like this:

```bash
cd /path/to/your/local/astero

# init repository (branch = main)
git init

# add upstream (official repo)
git remote add upstream https://github.com/asterodigital/asterobuilder.git

# fetch and create local main tracking upstream/main
git fetch upstream
git switch -c main --track upstream/main
```


Automatically reloads the application framework on every job. This means you don't have to restart it after every code change.
php artisan queue:listen -vvv

Needs to be manually restarted to see code changes
php artisan queue:work

production usage example
php artisan queue:work --timeout=90 --tries=3

Run vite server for live assets compliling and reloading.
npm run dev

php artisan log-viewer:publish

# End-to-End Testing (Playwright)

## Prerequisite: Seed Test Users

E2E login tests rely on seeded users.

```bash
php artisan astero:install
```

## Prerequisite: Install Playwright Browsers

If running for the first time:

```bash
npx playwright install
```

If tests fail with missing library errors on Linux:

```bash
npx playwright install-deps
```

## Running Tests

Run all E2E tests (Headless):

```bash
npm run test:e2e
```

Run tests visually (Headed browser):

```bash
npm run test:e2e:headed
```

Run tests in interactive UI mode (Time travel debugging):

```bash
npm run test:e2e:ui
```

Run tests visually in "Watch Mode" (Slow motion 500ms):

```bash
npm run test:e2e:watch
```

**Note:** Ensure you have the application running (or built via `npm run build`) before executing tests, as they run against `APP_URL`.
