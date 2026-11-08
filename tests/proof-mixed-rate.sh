#!/usr/bin/env bash
# Optional proof: restoring the pre-fix sales formula must fail testMixedConversionRatesAreConvertedPerLine.
set -euo pipefail

REAL_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PROOF_DIR="$(mktemp -d)"
chmod 0700 "$PROOF_DIR"
trap 'rm -rf "$PROOF_DIR"' EXIT

cp -a "$REAL_ROOT/." "$PROOF_DIR/"

python3 - <<PY
from pathlib import Path
path = Path("${PROOF_DIR}/statsbestsuppliers.php")
text = path.read_text()
old = """ROUND(SUM(od.product_quantity * od.unit_price_tax_excl / o.conversion_rate), 2)"""
new = """ROUND(SUM(od.product_quantity * od.unit_price_tax_excl) / o.conversion_rate, 2)"""
if old not in text:
    raise SystemExit("Expected fixed sales expression not found")
text = text.replace(old, new, 1)
text = text.replace("GROUP BY s.id_supplier, s.name", "GROUP BY p.id_supplier", 1)
path.write_text(text)
PY

LOG="$(mktemp)"
chmod 0600 "$LOG"

set +e
(
  cd "$PROOF_DIR"
  PROOF_ONLY=1 tests/run-suite.sh --filter testMixedConversionRatesAreConvertedPerLine
) >"$LOG" 2>&1
set -e

TARGET='SqlAggregationTest::testMixedConversionRatesAreConvertedPerLine'

if grep -q 'OK (' "$LOG"; then
  echo "Proof failed: PHPUnit output contains an OK summary (test or runner still green)" >&2
  cat "$LOG" >&2
  exit 1
fi

if ! grep -qF "$TARGET" "$LOG"; then
  echo "Proof failed: log does not name $TARGET" >&2
  cat "$LOG" >&2
  exit 1
fi

if ! grep -qE 'There was [0-9]+ (failure|error)|There were [0-9]+ (failures|errors)|FAILURES!|ERRORS!' "$LOG"; then
  echo "Proof failed: log does not report a PHPUnit failure or error block" >&2
  cat "$LOG" >&2
  exit 1
fi

if ! grep -qE "^[0-9]+\) ${TARGET}" "$LOG"; then
  echo "Proof failed: $TARGET is not listed as a failure or error entry" >&2
  cat "$LOG" >&2
  exit 1
fi

echo "Proof OK: reverted formula fails the mixed-rate test"
