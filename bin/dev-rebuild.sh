#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"

composer install --working-dir="${repo_root}/core/components/twig"
php "${repo_root}/_build/build.transport.php"
php "${repo_root}/bin/install-dev-package.php"

