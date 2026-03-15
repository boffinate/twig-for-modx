#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"
modx_root="$(cd "${repo_root}/../.." && pwd)"
backup_root="${repo_root}/.backup"

link_path() {
    local source_path="$1"
    local target_path="$2"

    if [[ -L "${target_path}" ]] && [[ "$(readlink -f "${target_path}")" == "$(readlink -f "${source_path}")" ]]; then
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
    ln -sfn "${source_path}" "${target_path}"
    printf 'Linked %s -> %s\n' "${target_path}" "${source_path}"
}

link_path "${repo_root}/core/components/twig" "${modx_root}/core/components/twig"
link_path "${repo_root}/assets/components/twig" "${modx_root}/assets/components/twig"
