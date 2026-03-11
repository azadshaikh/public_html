#!/usr/bin/env bash

set -euo pipefail

pnpm format
pnpm lint
./vendor/bin/pint --parallel
