#!/usr/bin/env bash

set -euo pipefail

pnpm format
pnpm lint
./vendor/bin/pint --parallel app bootstrap config database routes tests modules
