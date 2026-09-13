#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-}"
REQUIRES_JTL_UPDATE="${2:-true}"
SELF_UPDATE_SAFE="${3:-false}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/release"
PLUGIN="$ROOT/plugin/MGD_SEOoverride_Plugin"

if [[ -z "$VERSION" ]]; then
  VERSION="$(php -r '$x=simplexml_load_file($argv[1]); if(!$x){exit(1);} echo (string)$x->Version;' "$PLUGIN/info.xml")"
fi
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Ungültige Version: $VERSION" >&2
  exit 1
fi
if [[ "$REQUIRES_JTL_UPDATE" != "true" && "$REQUIRES_JTL_UPDATE" != "false" ]]; then
  echo "REQUIRES_JTL_UPDATE muss true oder false sein" >&2
  exit 1
fi
if [[ "$SELF_UPDATE_SAFE" != "true" && "$SELF_UPDATE_SAFE" != "false" ]]; then
  echo "SELF_UPDATE_SAFE muss true oder false sein" >&2
  exit 1
fi

INFO_VERSION="$(php -r '$x=simplexml_load_file($argv[1]); if(!$x){exit(1);} echo (string)$x->Version;' "$PLUGIN/info.xml")"
PLUGIN_ID="$(php -r '$x=simplexml_load_file($argv[1]); if(!$x){exit(1);} echo (string)$x->PluginID;' "$PLUGIN/info.xml")"
MIN_SHOP="$(php -r '$x=simplexml_load_file($argv[1]); if(!$x){exit(1);} echo (string)$x->MinShopVersion;' "$PLUGIN/info.xml")"
if [[ "$INFO_VERSION" != "$VERSION" ]]; then
  echo "info.xml Version $INFO_VERSION passt nicht zum Build $VERSION" >&2
  exit 1
fi
if [[ "$PLUGIN_ID" != "MGD_SEOoverride_Plugin" ]]; then
  echo "Unerwartete PluginID: $PLUGIN_ID" >&2
  exit 1
fi

ZIP="$OUT/MGD_JTL_SEO-${VERSION}.zip"
SHA_FILE="$ZIP.sha256"
MANIFEST="$OUT/MGD_JTL_SEO-${VERSION}.manifest.json"

rm -rf "$OUT"
mkdir -p "$OUT"

find "$PLUGIN" -name '*.php' -print0 | while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done
php -r '$x=simplexml_load_file($argv[1]); if($x===false){exit(1);} echo "info.xml OK\n";' "$PLUGIN/info.xml"

# JTL nutzt gettext-Kompilate für übersetzte Settings. In CI/Release-Builds
# werden vorhandene base.po-Dateien automatisch zu base.mo kompiliert.
if command -v msgfmt >/dev/null 2>&1; then
  while IFS= read -r -d '' po; do
    mo="${po%.po}.mo"
    msgfmt "$po" -o "$mo"
    echo "Locale kompiliert: $mo"
  done < <(find "$PLUGIN/locale" -name 'base.po' -print0 2>/dev/null || true)
fi

(
  cd "$ROOT/plugin"
  zip -qr "$ZIP" MGD_SEOoverride_Plugin
)

if command -v shasum >/dev/null 2>&1; then
  SHA="$(shasum -a 256 "$ZIP" | awk '{print $1}')"
else
  SHA="$(sha256sum "$ZIP" | awk '{print $1}')"
fi
printf '%s  %s\n' "$SHA" "$(basename "$ZIP")" > "$SHA_FILE"

cat > "$MANIFEST" <<JSON
{
  "schema": 1,
  "plugin_id": "MGD_SEOoverride_Plugin",
  "product": "MGD JTL SEO & PageSpeed",
  "version": "$VERSION",
  "min_shop_version": "$MIN_SHOP",
  "sha256": "$SHA",
  "requires_jtl_update": $REQUIRES_JTL_UPDATE,
  "self_update_safe": $SELF_UPDATE_SAFE,
  "release_asset": "$(basename "$ZIP")"
}
JSON

php -r '$j=json_decode(file_get_contents($argv[1]), true, 8, JSON_THROW_ON_ERROR); if(($j["plugin_id"]??"")!=="MGD_SEOoverride_Plugin" || ($j["sha256"]??"")!==$argv[2]){exit(1);} echo "manifest OK\n";' "$MANIFEST" "$SHA"

echo "Release erstellt: $ZIP"
echo "SHA256: $SHA"
echo "Manifest: $MANIFEST"
