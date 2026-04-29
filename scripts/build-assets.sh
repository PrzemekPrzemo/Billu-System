#!/usr/bin/env bash
# Minifikuje CSS/JS, generuje wersjonowane pliki .min.* i manifest.json.
# Idempotentny - kiedy treść się nie zmienia, hash się nie zmienia.
#
# Wymaga: node + npx (csso-cli i terser pobierane on-the-fly przez --yes).
# Uruchamiać przed deployem: bash scripts/build-assets.sh

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ASSETS="${ROOT}/public/assets"
MANIFEST="${ASSETS}/manifest.json"

cd "$ROOT"

if ! command -v npx >/dev/null 2>&1; then
    echo "ERROR: npx is required (install Node.js)." >&2
    exit 1
fi

declare -A OUT

# CSS
CSS_SRC="${ASSETS}/css/style.css"
if [[ -f "$CSS_SRC" ]]; then
    CSS_MIN="${ASSETS}/css/style.min.css"
    npx --yes csso-cli "$CSS_SRC" -o "$CSS_MIN"
    HASH=$(sha256sum "$CSS_MIN" | cut -c1-10)
    OUT["css/style.css"]="css/style.min.css?v=${HASH}"
    echo "  CSS  -> css/style.min.css?v=${HASH}"
fi

# JS
JS_SRC="${ASSETS}/js/app.js"
if [[ -f "$JS_SRC" ]]; then
    JS_MIN="${ASSETS}/js/app.min.js"
    npx --yes terser "$JS_SRC" -c -m -o "$JS_MIN"
    HASH=$(sha256sum "$JS_MIN" | cut -c1-10)
    OUT["js/app.js"]="js/app.min.js?v=${HASH}"
    echo "  JS   -> js/app.min.js?v=${HASH}"
fi

# Generate manifest.json
{
    echo "{"
    FIRST=1
    for K in "${!OUT[@]}"; do
        if [[ $FIRST -eq 0 ]]; then echo ","; fi
        printf '  "%s": "%s"' "$K" "${OUT[$K]}"
        FIRST=0
    done
    echo ""
    echo "}"
} > "$MANIFEST"

echo "Manifest written: $MANIFEST"
