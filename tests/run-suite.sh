#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
DOCKER_BIN="${DOCKER_BIN:-docker}"
if [[ "$USE_SUDO_DOCKER" == "1" ]]; then
  DOCKER=(sudo "$DOCKER_BIN")
else
  DOCKER=("$DOCKER_BIN")
fi

PHP_IMAGE_LIST="php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143"
MYSQL_IMAGE="mysql@sha256:dab0a802b44617303694fb17d166501de279c3031ddeb28c56ecf7fcab5ef0da"
DEBIAN_IMAGE="debian@sha256:734a72b3d6142a71993b4bc580ca01d8ca687f77a2c422d96a961d3376b179bc"
PHPUNIT_SHA256="ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e"
PHPUNIT_FINGERPRINT="D8406D0D82947747293778314AA394086372C20A"
NETWORK_NAME="statsbestsuppliers"
MYSQL_NAME="statsbestsuppliers-mysql"
PHP_RUNNER_TAG="statsbestsuppliers-php56-runner:local"
TEST_FILE_COUNT=5
METHOD_COUNT_EXPECTED=34

FILTER_ARGS=()
PROOF_ONLY="${PROOF_ONLY:-0}"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --filter)
      shift
      FILTER_ARGS=(--filter "$1")
      shift
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
done

RUN_TMP="$(mktemp -d)"
chmod 0700 "$RUN_TMP"
ARTIFACT_TMP="$(mktemp -d)"
chmod 0700 "$ARTIFACT_TMP"
cleanup() {
  "${DOCKER[@]}" rm -f "$MYSQL_NAME" >/dev/null 2>&1 || true
  "${DOCKER[@]}" network rm "$NETWORK_NAME" >/dev/null 2>&1 || true
  rm -rf "$RUN_TMP" "$ARTIFACT_TMP" "$BUILD_CTX"
}
BUILD_CTX="$(mktemp -d)"
chmod 0700 "$BUILD_CTX"
trap cleanup EXIT

log() {
  echo "[run-suite] $*"
}

fail_diag() {
  local output="$1"
  local patterns=(
    'Notice:'
    'Warning:'
    'Deprecated:'
    'Strict Standards:'
    'Parse error:'
    'Fatal error:'
    'Catchable fatal error:'
    'Recoverable fatal error:'
  )
  local pattern
  for pattern in "${patterns[@]}"; do
  if grep -q "$pattern" <<<"$output"; then
      echo "PHP diagnostic detected in test output: $pattern" >&2
      exit 1
    fi
  done
}

extract_ok_line() {
  local plural_only="${1:-1}"
  if [[ "$plural_only" == "1" ]]; then
    grep -E '^OK \([0-9]+ tests, [0-9]+ assertions\)$' || true
  else
    grep -E '^OK \([0-9]+ tests?, [0-9]+ assertions\)$' || true
  fi
}

verify_phpunit_phar() {
  local phar="$RUN_TMP/phpunit-4.8.36.phar"
  local asc="$RUN_TMP/phpunit-4.8.36.phar.asc"
  if [[ ! -f "$phar" ]]; then
    curl -fsSL -o "$phar" https://phar.phpunit.de/phpunit-4.8.36.phar
    curl -fsSL -o "$asc" https://phar.phpunit.de/phpunit-4.8.36.phar.asc
  fi
  echo "$PHPUNIT_SHA256  $phar" | sha256sum -c -
  local gpg_home
  gpg_home="$(mktemp -d)"
  chmod 700 "$gpg_home"
  GNUPGHOME="$gpg_home" gpg --batch --keyserver hkps://keyserver.ubuntu.com --recv-keys 4AA394086372C20A
  GNUPGHOME="$gpg_home" gpg --batch --status-fd 1 --verify "$asc" "$phar" | grep -q "VALIDSIG $PHPUNIT_FINGERPRINT"
  rm -rf "$gpg_home"
}

ensure_php_runner_image() {
  if "${DOCKER[@]}" image inspect "$PHP_RUNNER_TAG" >/dev/null 2>&1; then
    return
  fi
  cat >"$BUILD_CTX/Dockerfile" <<EOF
FROM ${PHP_IMAGE_LIST}
COPY --from=${DEBIAN_IMAGE} /usr/share/keyrings/debian-archive-keyring.gpg /etc/apt/trusted.gpg.d/debian-archive-keyring.gpg
COPY --from=${DEBIAN_IMAGE} /usr/share/keyrings/debian-archive-removed-keys.gpg /etc/apt/trusted.gpg.d/debian-archive-removed-keys.gpg
RUN sed -i 's|deb.debian.org|archive.debian.org|g' /etc/apt/sources.list \\
 && sed -i 's|security.debian.org|archive.debian.org|g' /etc/apt/sources.list \\
 && sed -i '/stretch-updates/d' /etc/apt/sources.list \\
 && echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until \\
 && apt-get update \\
 && apt-get install -y --no-install-recommends \${PHPIZE_DEPS} \\
 && docker-php-ext-install pdo_mysql \\
 && apt-get clean && rm -rf /var/lib/apt/lists/*
EOF
  "${DOCKER[@]}" build --platform linux/amd64 -t "$PHP_RUNNER_TAG" "$BUILD_CTX"
}

start_mysql() {
  "${DOCKER[@]}" network inspect "$NETWORK_NAME" >/dev/null 2>&1 || "${DOCKER[@]}" network create "$NETWORK_NAME"
  "${DOCKER[@]}" rm -f "$MYSQL_NAME" >/dev/null 2>&1 || true
  "${DOCKER[@]}" run -d --platform linux/amd64 --name "$MYSQL_NAME" --network "$NETWORK_NAME" \
    -e MYSQL_ROOT_PASSWORD=statsbestsuppliers \
    -e MYSQL_DATABASE=statsbestsuppliers \
    --health-cmd="mysqladmin ping -h 127.0.0.1 -uroot -pstatsbestsuppliers || exit 1" \
    --health-interval=1s --health-timeout=5s --health-retries=30 \
    "$MYSQL_IMAGE" >/dev/null
  local deadline=$((SECONDS + 60))
  while [[ $SECONDS -lt $deadline ]]; do
    local status
    status="$("${DOCKER[@]}" inspect -f '{{.State.Health.Status}}' "$MYSQL_NAME" 2>/dev/null || echo starting)"
    if [[ "$status" == "healthy" ]]; then
      return
    fi
    sleep 1
  done
  echo "MySQL container did not become healthy" >&2
  exit 1
}

init_schema() {
  "${DOCKER[@]}" exec -i "$MYSQL_NAME" mysql -uroot -pstatsbestsuppliers statsbestsuppliers <<'SQL'
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS ps_order_detail;
DROP TABLE IF EXISTS ps_orders;
DROP TABLE IF EXISTS ps_product;
DROP TABLE IF EXISTS ps_supplier;
CREATE TABLE ps_supplier (
  id_supplier INT UNSIGNED NOT NULL,
  name VARCHAR(64) NOT NULL,
  id_shop INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_supplier)
) ENGINE=InnoDB;
CREATE TABLE ps_product (
  id_product INT UNSIGNED NOT NULL,
  id_supplier INT UNSIGNED NULL,
  id_shop INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_product)
) ENGINE=InnoDB;
CREATE TABLE ps_orders (
  id_order INT UNSIGNED NOT NULL,
  invoice_date DATETIME NOT NULL,
  valid TINYINT(1) NOT NULL,
  conversion_rate DECIMAL(13,6) NOT NULL,
  id_shop INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_order)
) ENGINE=InnoDB;
CREATE TABLE ps_order_detail (
  id_order_detail INT UNSIGNED NOT NULL,
  id_order INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_quantity INT NOT NULL,
  unit_price_tax_excl DECIMAL(20,6) NOT NULL,
  product_price DECIMAL(20,6) NOT NULL,
  PRIMARY KEY (id_order_detail)
) ENGINE=InnoDB;
SET FOREIGN_KEY_CHECKS=1;
SQL
}

lint_files() {
  local files=(
    statsbestsuppliers.php
    upgrade/upgrade-2.0.1.php
    tests/bootstrap.php
    tests/stubs/PrestaShopBoundary.php
    tests/support/StatsbestsuppliersTestCase.php
    tests/support/StatsbestsuppliersProbe.php
    tests/support/include-target.php
    tests/ModuleMetadataTest.php
    tests/SqlAggregationTest.php
    tests/HookRenderTest.php
    tests/InstallUpgradeTest.php
    tests/WebGuardTest.php
  )
  local file
  for file in "${files[@]}"; do
    "${DOCKER[@]}" run --rm --platform linux/amd64 -v "$ROOT_DIR:/src:ro" -w /src "$PHP_RUNNER_TAG" \
      php -l "/src/$file"
  done
}

generate_phpunit_xml() {
  local xml_path="$1"
  local ordered="$2"
  local xml_dir
  xml_dir="$(dirname "$xml_path")"
  local xml_base
  xml_base="$(basename "$xml_path")"
  chmod 0777 "$(dirname "$xml_path")"
  "${DOCKER[@]}" run --rm --platform linux/amd64 -v "$ROOT_DIR:/src:ro" -v "$(dirname "$xml_path"):/xml-out:rw" \
    "$PHP_RUNNER_TAG" php -r '
$ordered = $argv[1] === "1";
$root = "/src";
$files = array(
  "tests/HookRenderTest.php",
  "tests/InstallUpgradeTest.php",
  "tests/ModuleMetadataTest.php",
  "tests/SqlAggregationTest.php",
  "tests/WebGuardTest.php",
);
sort($files);
$globbed = glob($root . "/tests/*Test.php");
$globbedRel = array();
foreach ($globbed as $path) {
  $globbedRel[] = substr($path, strlen($root) + 1);
}
sort($globbedRel);
if ($files !== $globbedRel) {
  fwrite(STDERR, "Test file list drift\n");
  exit(1);
}
$run = $files;
if (!$ordered) {
  srand(20261108);
  shuffle($run);
}
$doc = new DOMDocument("1.0", "UTF-8");
$doc->formatOutput = true;
$phpunit = $doc->createElement("phpunit");
$phpunit->setAttribute("bootstrap", $root . "/tests/bootstrap.php");
$phpunit->setAttribute("backupGlobals", "false");
$phpunit->setAttribute("backupStaticAttributes", "false");
$phpunit->setAttribute("colors", "false");
$phpunit->setAttribute("convertErrorsToExceptions", "true");
$phpunit->setAttribute("convertNoticesToExceptions", "true");
$phpunit->setAttribute("convertWarningsToExceptions", "true");
$phpunit->setAttribute("verbose", "true");
$suite = $doc->createElement("testsuite");
$suite->setAttribute("name", "module-tests");
foreach ($run as $rel) {
  $file = $doc->createElement("file", $root . "/" . $rel);
  $suite->appendChild($file);
}
$phpunit->appendChild($suite);
$doc->appendChild($phpunit);
$doc->save("/xml-out/" . $argv[2]);
' "$ordered" "$xml_base" 
}

run_phpunit() {
  local xml_path="$1"
  local log_path="$2"
  "${DOCKER[@]}" run --rm --platform linux/amd64 --network "container:$MYSQL_NAME" \
    -v "$ROOT_DIR:/src:ro" \
    -v "$RUN_TMP:/run-tmp:ro" \
    -v "$xml_path:/phpunit.xml:ro" \
    -e STATS_TEST_DB_HOST="127.0.0.1" \
    -e STATS_TEST_DB_NAME=statsbestsuppliers \
    -e STATS_TEST_DB_USER=root \
    -e STATS_TEST_DB_PASSWORD=statsbestsuppliers \
    -e PHP_BIN=php \
    -w /src \
    "$PHP_RUNNER_TAG" \
    bash -lc 'php -v | grep -q "PHP 5.6.40" && php -m | grep -q pdo_mysql && php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 -d log_errors=0 /run-tmp/phpunit-4.8.36.phar --configuration /phpunit.xml "$@"' _ "${FILTER_ARGS[@]}" \
    | tee "$log_path"
  local exit_code="${PIPESTATUS[0]}"
  return "$exit_code"
}

verify_glob_count() {
  local count
  count="$(find "$ROOT_DIR/tests" -maxdepth 1 -name '*Test.php' | wc -l | tr -d ' ')"
  if [[ "$count" != "$TEST_FILE_COUNT" ]]; then
    echo "Expected $TEST_FILE_COUNT top-level test files, found $count" >&2
    exit 1
  fi
}

main() {
  local proof_or_filter=0
  if [[ "$PROOF_ONLY" == "1" || ${#FILTER_ARGS[@]} -gt 0 ]]; then
    proof_or_filter=1
  fi

  command -v "${DOCKER[0]}" >/dev/null 2>&1 || { echo "docker not found in PATH" >&2; exit 1; }
  verify_glob_count
  log "Pulling images"
  "${DOCKER[@]}" pull --platform linux/amd64 "$PHP_IMAGE_LIST" >/dev/null
  "${DOCKER[@]}" pull --platform linux/amd64 "$MYSQL_IMAGE" >/dev/null
  "${DOCKER[@]}" pull --platform linux/amd64 "$DEBIAN_IMAGE" >/dev/null
  verify_phpunit_phar
  ensure_php_runner_image
  start_mysql
  init_schema
  lint_files

  local ordered_xml shuffled_xml ordered_log shuffled_log
  local xml_dir="$RUN_TMP/phpunit-xml"
  mkdir -p "$xml_dir"
  chmod 0777 "$xml_dir"
  ordered_xml="$xml_dir/ordered.xml"
  shuffled_xml="$xml_dir/shuffled.xml"
  ordered_log="$ARTIFACT_TMP/ordered.log"
  shuffled_log="$ARTIFACT_TMP/shuffled.log"
  generate_phpunit_xml "$ordered_xml" 1
  generate_phpunit_xml "$shuffled_xml" 0

  local ordered_exit=0 shuffled_exit=0
  run_phpunit "$ordered_xml" "$ordered_log" || ordered_exit=$?

  local ordered_output
  ordered_output="$(cat "$ordered_log")"
  fail_diag "$ordered_output"

  if [[ "$proof_or_filter" == "1" ]]; then
    exit "$ordered_exit"
  fi

  run_phpunit "$shuffled_xml" "$shuffled_log" || shuffled_exit=$?

  local shuffled_output
  shuffled_output="$(cat "$shuffled_log")"
  fail_diag "$shuffled_output"

  local ordered_ok shuffled_ok
  ordered_ok="$(extract_ok_line 1 <<<"$ordered_output")"
  shuffled_ok="$(extract_ok_line 1 <<<"$shuffled_output")"

  if [[ -z "$ordered_ok" || -z "$shuffled_ok" ]]; then
    echo "Missing OK summary line in PHPUnit output" >&2
    exit 1
  fi

  if [[ "$ordered_ok" != "$shuffled_ok" ]]; then
    echo "Ordered and shuffled OK summaries differ: $ordered_ok vs $shuffled_ok" >&2
    exit 1
  fi

  if grep -qE 'FAILURES!|ERRORS!|Skipped:|Incomplete:|Risky:' <<<"$ordered_output$shuffled_output"; then
    echo "PHPUnit reported failures, errors, skips, incomplete or risky tests" >&2
    exit 1
  fi

  if [[ "$ordered_ok" != OK\ \(${METHOD_COUNT_EXPECTED}\ tests,* ]]; then
    echo "Unexpected test count in summary: $ordered_ok" >&2
    exit 1
  fi

  if [[ "$ordered_exit" -ne 0 || "$shuffled_exit" -ne 0 ]]; then
    exit 1
  fi

  log "Suite green: $ordered_ok"
  exit 0
}

main "$@"
