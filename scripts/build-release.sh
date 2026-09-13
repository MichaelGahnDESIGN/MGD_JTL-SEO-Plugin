#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-1.1.0}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/release"
PLUGIN="$ROOT/plugin/MGD_SEOoverride_Plugin"
ZIP="$OUT/MGD_JTL_SEO-${VERSION}.zip"

rm -rf "$OUT"
mkdir -p "$OUT"

find "$PLUGIN" -name '*.php' -print0 | while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done

(
  cd "$ROOT/plugin"
  zip -qr "$ZIP" MGD_SEOoverride_Plugin
)

if command -v shasum >/dev/null 2>&1; then
  shasum -a 256 "$ZIP" > "$ZIP.sha256"
else
  sha256sum "$ZIP" > "$ZIP.sha256"
fi

echo "Release erstellt: $ZIP"
