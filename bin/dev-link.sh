#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"
modx_root="$(cd "${repo_root}/../.." && pwd)"
backup_root="${repo_root}/.backup"

# Symlinks are created with a RELATIVE target so they resolve correctly both
# on the host and inside the ddev container, where the repo is mounted at a
# different absolute path (/var/www/html/...).
link_path() {
    local relative_source="$1"
    local target_path="$2"

    if [[ -L "${target_path}" ]] && [[ "$(readlink "${target_path}")" == "${relative_source}" ]]; then
        return 0
    fi

    if [[ -e "${target_path}" ]] && [[ ! -L "${target_path}" ]]; then
        mkdir -p "${backup_root}"
        local backup_name
        backup_name="$(printf '%s' "${target_path}" | sed 's#^/##; s#/#-#g')"
        local backup_path="${backup_root}/${backup_name}.pre-extra.$(date +%Y%m%d%H%M%S)"
        mv "${target_path}" "${backup_path}"
        printf 'Moved %s to %s\n' "${target_path}" "${backup_path}"
    fi

    mkdir -p "$(dirname "${target_path}")"
    ln -sfn "${relative_source}" "${target_path}"
    printf 'Linked %s -> %s\n' "${target_path}" "${relative_source}"
}

link_path "../../extras/twig-extra/core/components/twig" "${modx_root}/core/components/twig"

# The repo currently ships no assets; only link (or keep a link to) the assets
# directory if it exists, and clean up a dangling link left by earlier runs.
if [[ -d "${repo_root}/assets/components/twig" ]]; then
    link_path "../../extras/twig-extra/assets/components/twig" "${modx_root}/assets/components/twig"
elif [[ -L "${modx_root}/assets/components/twig" ]] && [[ ! -e "${modx_root}/assets/components/twig" ]]; then
    rm "${modx_root}/assets/components/twig"
    printf 'Removed dangling link %s\n' "${modx_root}/assets/components/twig"
fi
