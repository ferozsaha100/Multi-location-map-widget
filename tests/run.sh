#!/usr/bin/env bash
#
# Runs the full test suite against the plugin in ./ave-multi-location-map.
#
#   bash tests/run.sh              # everything (needs: npm install --prefix tests
#                                  #             && npx --prefix tests playwright install chromium)
#   bash tests/run.sh --no-browser # PHP + JavaScript unit tests only (PHP 7.4+ and Node 18+)
#
set -euo pipefail

ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
PLUGIN="$ROOT/ave-multi-location-map"
OUT="$ROOT/tests/.out"
BASE_URL="http://127.0.0.1:8765/plugin" # Virtual origin; the browser tests intercept it.

rm -rf "$OUT"
mkdir -p "$OUT/shots"

# Show any FAIL lines plus the summary line; the test's exit code still fails the run.
report() { awk '/^FAIL/ { print } { last = $0 } END { print last }'; }

section() { printf '\n\033[1m== %s ==\033[0m\n' "$1"; }

section "PHP syntax ($(php -r 'echo PHP_VERSION;'))"
find "$PLUGIN" -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax errors' || true
find "$PLUGIN" -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null
echo "OK"

section "JavaScript syntax"
for f in "$PLUGIN"/assets/js/*.js; do node --check "$f"; done
echo "OK"

section "Office Map: settings, OpenStreetMap lookups, REST, render, phones, pins"
php "$ROOT/tests/php/office-map.test.php" "$PLUGIN" "$OUT" "$BASE_URL" | report

section "Original Multi-Location Map widget"
php "$ROOT/tests/php/legacy-widget.test.php" "$PLUGIN" "$OUT" | report

section "Editor buttons (Find on map / Find all missing pins)"
node "$ROOT/tests/js/editor.test.js" "$PLUGIN/assets/js/ave-office-map-editor.js" | report

if [[ "${1:-}" == "--no-browser" ]]; then
	printf '\nBrowser tests skipped (--no-browser).\n'
	exit 0
fi

for suite in core phones numbers pins mobile; do
	section "Browser: $suite"
	node "$ROOT/tests/browser/$suite.test.js" "$PLUGIN" "$OUT" "$OUT/shots" | report
done

printf '\nAll suites passed.\n'
