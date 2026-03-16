#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/.." && pwd)"
modx_root="$(cd "${repo_root}/../.." && pwd)"

# ---------------------------------------------------------------------------
# 1. Read version from build config
# ---------------------------------------------------------------------------
version=$(php -r "\$c = include '${repo_root}/_build/build.config.php'; echo \$c['version'];")
release=$(php -r "\$c = include '${repo_root}/_build/build.config.php'; echo \$c['release'];")
tag="v${version}-${release}"
full="${version}-${release}"

echo "Version from build config: ${full}"
echo "Git tag: ${tag}"

# ---------------------------------------------------------------------------
# 2. Check CHANGELOG.md has a matching entry and extract its notes
# ---------------------------------------------------------------------------
changelog="${repo_root}/CHANGELOG.md"

if grep -qi "^## .*unreleased" "${changelog}"; then
    echo "ERROR: CHANGELOG.md still contains an Unreleased section." >&2
    echo "       Move those notes under '## ${full}' before releasing." >&2
    exit 1
fi

if ! grep -q "^## ${full}\$" "${changelog}"; then
    echo "ERROR: No '## ${full}' heading found in CHANGELOG.md" >&2
    exit 1
fi

# Extract the notes for this version (everything between this heading and the next)
notes=$(awk "found && /^## /{exit} /^## ${full}\$/{found=1; next} found{print}" "${changelog}")

if [ -z "${notes}" ]; then
    echo "ERROR: Changelog entry for ${full} is empty" >&2
    exit 1
fi

echo ""
echo "Changelog notes:"
echo "${notes}"
echo ""

# ---------------------------------------------------------------------------
# 3. Check the tag doesn't already exist
# ---------------------------------------------------------------------------
if git -C "${repo_root}" rev-parse "${tag}" >/dev/null 2>&1; then
    echo "ERROR: Git tag '${tag}' already exists" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# 4. Check working tree is clean
# ---------------------------------------------------------------------------
if [ -n "$(git -C "${repo_root}" status --porcelain)" ]; then
    echo "ERROR: Working tree is not clean. Commit or stash changes first." >&2
    git -C "${repo_root}" status --short
    exit 1
fi

# ---------------------------------------------------------------------------
# 5. Build the transport package
# ---------------------------------------------------------------------------
echo "Building transport package via ddev..."

ddev exec composer install --working-dir=/var/www/html/extras/twig-extra/core/components/twig
ddev exec php /var/www/html/extras/twig-extra/_build/build.transport.php

zip_path="${modx_root}/core/packages/twig-${full}.transport.zip"

if [ ! -f "${zip_path}" ]; then
    echo "ERROR: Expected transport zip not found at ${zip_path}" >&2
    exit 1
fi

echo "Transport package built: ${zip_path}"

# ---------------------------------------------------------------------------
# 6. Tag and create GitHub release
# ---------------------------------------------------------------------------
echo ""
echo "Ready to create release ${tag}"
echo "  Zip: ${zip_path}"
echo ""
read -rp "Create git tag and GitHub release? [y/N] " confirm

if [[ ! "${confirm}" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 0
fi

git -C "${repo_root}" tag -a "${tag}" -m "Release ${full}"
git -C "${repo_root}" push origin "${tag}"

gh release create "${tag}" \
    --repo boffinate/twig-for-modx \
    --title "Twig for MODX ${full}" \
    --notes "${notes}" \
    "${zip_path}#twig-${full}.transport.zip"

echo ""
echo "Released: ${tag}"
echo "https://github.com/boffinate/twig-for-modx/releases/tag/${tag}"
