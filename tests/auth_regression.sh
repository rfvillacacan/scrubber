#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASE_URL="${BASE_URL:-http://127.0.0.1:18081}"
COOKIE_JAR="${ROOT_DIR}/tests/.cookies.txt"
BODY_FILE="${ROOT_DIR}/tests/.resp.txt"
SERVER_LOG="${ROOT_DIR}/tests/.server.log"
AUTH_USER="${AUTH_USER:-tester}"
AUTH_PASS="${AUTH_PASS:-secret123}"

cleanup() {
  rm -f "$COOKIE_JAR" "$BODY_FILE"
  if [[ -n "${SERVER_PID:-}" ]]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

if [[ "${START_LOCAL_SERVER:-1}" == "1" ]]; then
  APP_BASIC_AUTH_USER="$AUTH_USER" APP_BASIC_AUTH_PASS="$AUTH_PASS" \
    php -S 127.0.0.1:18081 -t "$ROOT_DIR" >"$SERVER_LOG" 2>&1 &
  SERVER_PID=$!
  for _ in {1..30}; do
    if curl -sS -o /dev/null "$BASE_URL/" >/dev/null 2>&1; then
      break
    fi
    sleep 0.2
  done
fi

assert_eq() {
  local actual="$1"
  local expected="$2"
  local label="$3"
  if [[ "$actual" != "$expected" ]]; then
    echo "[FAIL] $label"
    echo "Expected: $expected"
    echo "Actual: $actual"
    exit 1
  fi
  echo "[OK] $label"
}

CODE=$(curl -sS -o "$BODY_FILE" -w "%{http_code}" "$BASE_URL/")
assert_eq "$CODE" "401" "Unauthenticated request gets 401"

WWW_AUTH=$(curl -sS -D - -o /dev/null "$BASE_URL/" | tr -d '\r' | awk -F': ' '/^WWW-Authenticate:/ {print $2}' | head -n1)
if [[ "$WWW_AUTH" != *"Basic"* ]]; then
  echo "[FAIL] Missing WWW-Authenticate challenge"
  exit 1
fi
echo "[OK] WWW-Authenticate challenge present"

CODE=$(curl -sS -o "$BODY_FILE" -w "%{http_code}" -u "$AUTH_USER:$AUTH_PASS" -b "$COOKIE_JAR" -c "$COOKIE_JAR" "$BASE_URL/")
assert_eq "$CODE" "200" "Valid credentials get page"

HOME_HTML=$(cat "$BODY_FILE")
CSRF_TOKEN=$(printf '%s' "$HOME_HTML" | sed -n 's/.*name="csrf-token" content="\([a-f0-9]\{64\}\)".*/\1/p' | head -n1)
if [[ ${#CSRF_TOKEN} -ne 64 ]]; then
  echo "[FAIL] Missing CSRF token after auth"
  exit 1
fi
echo "[OK] Authenticated page includes CSRF token"

CODE=$(curl -sS -o "$BODY_FILE" -w "%{http_code}" -u "$AUTH_USER:$AUTH_PASS" -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  --data "action=history&csrf_token=$CSRF_TOKEN" "$BASE_URL/")
assert_eq "$CODE" "200" "Authenticated API request succeeds"

echo "All auth regression checks passed."
