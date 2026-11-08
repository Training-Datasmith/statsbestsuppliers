#!/usr/bin/env bash
# Optional proof: restoring the pre-fix sales formula must fail testMixedConversionRatesAreConvertedPerLine.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
MODULE_FILE="$ROOT_DIR/statsbestsuppliers.php"
BACKUP="$(mktemp)"
trap 'cp "$BACKUP" "$MODULE_FILE"' EXIT

cp "$MODULE_FILE" "$BACKUP"

cd "$ROOT_DIR"
python3 - <<'PY'
from pathlib import Path
path = Path("statsbestsuppliers.php")
text = path.read_text()
old = """ROUND(SUM(od.product_quantity * od.unit_price_tax_excl / o.conversion_rate), 2)"""
new = """ROUND(SUM(od.product_quantity * od.unit_price_tax_excl) / o.conversion_rate, 2)"""
if old not in text:
    raise SystemExit("Expected fixed sales expression not found")
text = text.replace(old, new, 1)
text = text.replace("GROUP BY s.id_supplier, s.name", "GROUP BY p.id_supplier", 1)
path.write_text(text)
PY

export PROOF_ONLY=1
if tests/run-suite.sh --filter testMixedConversionRatesAreConvertedPerLine; then
  echo "Proof failed: test still passes with reverted formula" >&2
  exit 1
fi

echo "Proof OK: reverted formula fails the mixed-rate test"
