#!/bin/sh
# Tests for the pi-lens Docker test runner integration:
#   - docker/phpunit              (phpunit shim -> Pest inside the app container)
#   - docker/pi-with-docker-tests (launcher that puts the shim first in PATH)
#
# Uses a controlled mock `docker` and mock `pi`; never touches real Docker
# workloads or a real pi session. Only cleans temp files it created itself.
set -u

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd) || exit 1
ROOT=$(dirname -- "$SCRIPT_DIR")

TMP=$(mktemp -d "${TMPDIR:-/tmp}/pi-runner-test.XXXXXX") || exit 1
# Test-owned symlink under tests/ (created and removed by this test only).
ESCAPE_LINK="$ROOT/tests/.pi-runner-escape.$$.php"
trap 'rm -rf "$TMP"; rm -f "$ESCAPE_LINK"' EXIT
trap 'rm -rf "$TMP"; rm -f "$ESCAPE_LINK"; exit 130' INT
trap 'rm -rf "$TMP"; rm -f "$ESCAPE_LINK"; exit 143' TERM

FAKEBIN="$TMP/bin"
mkdir -p "$FAKEBIN" || exit 1

DOCKER_LOG="$TMP/docker.log"
PI_LOG="$TMP/pi.log"
export DOCKER_LOG PI_LOG
: >"$DOCKER_LOG" || exit 1
: >"$PI_LOG" || exit 1

cat >"$FAKEBIN/docker" <<'MOCK'
#!/bin/sh
printf '%s\n' "$*" >> "$DOCKER_LOG"
exit "${DOCKER_EXIT:-0}"
MOCK

cat >"$FAKEBIN/pi" <<'MOCK'
#!/bin/sh
{
  printf 'argv:'
  for pi_arg in "$@"; do
    printf ' %s' "$pi_arg"
  done
  printf '\n'
  printf 'phpunit:%s\n' "$(command -v phpunit 2>/dev/null || true)"
  printf 'cwd:%s\n' "$(pwd -P)"
  printf 'path:%s\n' "$PATH"
} >> "$PI_LOG"
exit "${PI_EXIT:-0}"
MOCK

chmod +x "$FAKEBIN/docker" "$FAKEBIN/pi"

# Route every invocation in this test (shim and launcher) to the mock docker.
PATH="$FAKEBIN:$PATH"
export PATH

passes=0
failures=0

pass() {
    printf 'PASS: %s\n' "$1"
    passes=$((passes + 1))
}

fail() {
    printf 'FAIL: %s\n' "$1"
    failures=$((failures + 1))
}

expect_eq() {
    if [ "$3" = "$2" ]; then
        pass "$1"
    else
        printf '  expected: %s\n  actual:   %s\n' "$2" "$3"
        fail "$1"
    fi
}

expect_nonzero() {
    if [ "$2" -ne 0 ]; then
        pass "$1"
    else
        printf '  expected a non-zero exit status, got: %s\n' "$2"
        fail "$1"
    fi
}

expect_contains() {
    case $2 in
    *"$3"*) pass "$1" ;;
    *)
        printf '  expected to contain: %s\n  actual: %s\n' "$3" "$2"
        fail "$1"
        ;;
    esac
}

docker_log_lines() {
    wc -l <"$DOCKER_LOG"
}

run_shim_ok="$ROOT/tests/Unit/ExampleTest.php"
expected_invocation="compose -f $ROOT/docker-compose.yml --project-directory $ROOT exec -T app ./vendor/bin/pest /var/www/html/tests/Unit/ExampleTest.php"

# --- docker/phpunit shim ---------------------------------------------------

: >"$DOCKER_LOG"
"$ROOT/docker/phpunit" "$run_shim_ok" >/dev/null 2>&1
shim_code=$?
expect_eq "shim exits 0 on a valid repo test path" "0" "$shim_code"
expect_eq "shim invokes docker exactly once" "1" "$(($(docker_log_lines)))"
expect_eq \
    "shim forwards compose exec -T app pest with container-mapped path" \
    "$expected_invocation" \
    "$(head -n 1 "$DOCKER_LOG")"

: >"$DOCKER_LOG"
"$ROOT/docker/phpunit" >/dev/null 2>&1
expect_nonzero "shim rejects a missing argument" $?
expect_eq "shim rejection (no args) never invokes docker" "0" "$(($(docker_log_lines)))"

"$ROOT/docker/phpunit" tests/Unit/ExampleTest.php >/dev/null 2>&1
expect_nonzero "shim rejects a relative test path" $?

"$ROOT/docker/phpunit" /etc/hostname >/dev/null 2>&1
expect_nonzero "shim rejects an absolute path outside the repository" $?

"$ROOT/docker/phpunit" "$ROOT/tests/DoesNotExistTest.php" >/dev/null 2>&1
expect_nonzero "shim rejects a nonexistent test file" $?

"$ROOT/docker/phpunit" "$ROOT/tests/Unit" >/dev/null 2>&1
expect_nonzero "shim rejects a directory instead of a test file" $?

: >"$DOCKER_LOG"
"$ROOT/docker/phpunit" "$ROOT/tests/../app/Providers/AppServiceProvider.php" >/dev/null 2>&1
expect_nonzero "shim rejects a path traversal escaping tests/ via .." $?

escape_target="$TMP/escape/EscapeTarget.php"
mkdir -p "${escape_target%/*}" || exit 1
printf '<?php\n' >"$escape_target" || exit 1
ln -s "$escape_target" "$ESCAPE_LINK" || exit 1
"$ROOT/docker/phpunit" "$ESCAPE_LINK" >/dev/null 2>&1
expect_nonzero "shim rejects a symlink under tests/ pointing outside the repository" $?
rm -f "$ESCAPE_LINK"
expect_eq "shim rejections never invoke docker" "0" "$(($(docker_log_lines)))"

# --- docker/pi-with-docker-tests launcher ----------------------------------

: >"$PI_LOG"
: >"$DOCKER_LOG"
(
    cd "$ROOT/tests" || exit 1
    PATH="$FAKEBIN:$PATH" "$ROOT/docker/pi-with-docker-tests" --version
) >/dev/null 2>&1
launcher_code=$?
expect_eq "launcher exits 0 when invoked from a repo subdirectory" "0" "$launcher_code"

pi_log_content=$(cat "$PI_LOG")
expect_contains "launcher execs pi with forwarded arguments" "$pi_log_content" "argv: --version"
expect_contains "launcher resolves phpunit to the repo shim" "$pi_log_content" "phpunit:$ROOT/docker/phpunit"
expect_contains "launcher runs pi from the repository root" "$pi_log_content" "cwd:$ROOT"

launcher_path_line=$(sed -n 's/^path://p' "$PI_LOG")
expect_eq "launcher puts the repo docker dir first in PATH" "$ROOT/docker" "${launcher_path_line%%:*}"
case $launcher_path_line in
*/vendor/bin*) fail "launcher strips vendor/bin entries from PATH (got: $launcher_path_line)" ;;
'') fail "launcher produced an empty PATH for pi" ;;
*) pass "launcher strips vendor/bin entries from PATH" ;;
esac
expect_eq "launcher itself never invokes docker" "0" "$(($(docker_log_lines)))"

: >"$PI_LOG"
(
    cd "$TMP" || exit 1
    "$ROOT/docker/pi-with-docker-tests" >/dev/null 2>&1
)
expect_nonzero "launcher rejects an invocation from outside the repository" $?
expect_eq "rejected launcher invocation never execs pi" "0" "$(grep -c '' "$PI_LOG")"

: >"$PI_LOG"
(
    PATH="$ROOT/docker:/usr/bin:/bin" "$ROOT/docker/pi-with-docker-tests" >/dev/null 2>&1
)
expect_nonzero "launcher fails fast when pi is not on PATH" $?
expect_eq "launcher without pi never execs anything" "0" "$(grep -c '' "$PI_LOG")"

printf '\n%d passed, %d failed\n' "$passes" "$failures"
[ "$failures" -eq 0 ]
