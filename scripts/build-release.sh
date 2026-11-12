#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-1.1.3}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/release"
PLUGIN="$ROOT/plugin/MGD_SEOoverride_Plugin"
ZIP="$OUT/MGD_JTL_SEO-${VERSION}.zip"

rm -rf "$OUT"
mkdir -p "$OUT"

find "$PLUGIN" -name '*.php' -print0 | while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done

# Rechte fix 755/644 - Volume liefert sonst 700 (unzip per SSH > 403)
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
cp -R "$PLUGIN" "$STAGE/"
find "$STAGE" \( -name '.DS_Store' -o -name '._*' \) -exec rm -f {} +
find "$STAGE/MGD_SEOoverride_Plugin" -type d -exec chmod 755 {} +
find "$STAGE/MGD_SEOoverride_Plugin" -type f -exec chmod 644 {} +

(
  cd "$STAGE"
  zip -qrX "$ZIP" MGD_SEOoverride_Plugin
)

# Gegenprobe Rechte im ZIP
BAD="$(zipinfo "$ZIP" | awk '/^[d-]/ && $1 != "drwxr-xr-x" && $1 != "-rw-r--r--"' | wc -l | tr -d ' ')"
if [ "$BAD" != "0" ]; then
  echo "Falsche Rechte im ZIP: $BAD Einträge" >&2
  exit 1
fi

if command -v shasum >/dev/null 2>&1; then
  shasum -a 256 "$ZIP" > "$ZIP.sha256"
else
  sha256sum "$ZIP" > "$ZIP.sha256"
fi

echo "Release erstellt: $ZIP"
