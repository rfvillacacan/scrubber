#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASE_URL="${BASE_URL:-http://127.0.0.1:18080}"
COOKIE_JAR="${ROOT_DIR}/tests/.cookies.txt"
BODY_FILE="${ROOT_DIR}/tests/.resp.txt"
SERVER_LOG="${ROOT_DIR}/tests/.server.log"

cleanup() {
  rm -f "$COOKIE_JAR" "$BODY_FILE"
  if [[ -n "${SERVER_PID:-}" ]]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

if [[ "${START_LOCAL_SERVER:-1}" == "1" ]]; then
  php -S 127.0.0.1:18080 -t "$ROOT_DIR" >"$SERVER_LOG" 2>&1 &
  SERVER_PID=$!
  for _ in {1..30}; do
    if curl -sS "$BASE_URL/" >/dev/null 2>&1; then
      break
    fi
    sleep 0.2
  done
fi

assert_contains() {
  local haystack="$1"
  local needle="$2"
  local label="$3"
  if [[ "$haystack" != *"$needle"* ]]; then
    echo "[FAIL] $label"
    echo "Expected to find: $needle"
    echo "Actual: $haystack"
    exit 1
  fi
  echo "[OK] $label"
}

status_and_body() {
  local method="$1"
  local data="$2"
  if [[ "$method" == "POST" ]]; then
    local code
    code=$(curl -sS -o "$BODY_FILE" -w "%{http_code}" -b "$COOKIE_JAR" -c "$COOKIE_JAR" --data "$data" "$BASE_URL/")
    printf '%s\n' "$code"
  else
    local code
    code=$(curl -sS -o "$BODY_FILE" -w "%{http_code}" -b "$COOKIE_JAR" -c "$COOKIE_JAR" "$BASE_URL/")
    printf '%s\n' "$code"
  fi
}

HOME_HTML=$(curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" "$BASE_URL/")
CSRF_TOKEN=$(printf '%s' "$HOME_HTML" | sed -n 's/.*name="csrf-token" content="\([a-f0-9]\{64\}\)".*/\1/p' | head -n1)
SESSION_ID=$(printf '%s' "$HOME_HTML" | sed -n 's/.*id="sessionId">\([a-f0-9]\{32\}\)<.*/\1/p' | head -n1)

if [[ ${#CSRF_TOKEN} -ne 64 ]]; then
  echo "[FAIL] missing csrf token"
  exit 1
fi
if [[ ${#SESSION_ID} -ne 32 ]]; then
  echo "[FAIL] missing session id"
  exit 1
fi

echo "[OK] home page exposes csrf/session identifiers"

# 1) CSRF enforcement
CODE=$(status_and_body POST "action=history")
BODY=$(cat "$BODY_FILE")
if [[ "$CODE" != "403" ]]; then
  echo "[FAIL] CSRF missing should return 403, got $CODE"
  exit 1
fi
assert_contains "$BODY" "Invalid CSRF token" "CSRF rejection"

# 2) Valid CSRF should work
CODE=$(status_and_body POST "action=history&csrf_token=$CSRF_TOKEN")
BODY=$(cat "$BODY_FILE")
if [[ "$CODE" != "200" ]]; then
  echo "[FAIL] history with CSRF should return 200, got $CODE"
  exit 1
fi
assert_contains "$BODY" "[" "History endpoint returns JSON array"

# 3) Upload invalid type should fail
CODE=$(curl -sS -o "$BODY_FILE" -w "%{http_code}" -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -F "action=upload_ruleset" \
  -F "csrf_token=$CSRF_TOKEN" \
  -F "ruleset=@/etc/hosts" \
  "$BASE_URL/")
BODY=$(cat "$BODY_FILE")
if [[ "$CODE" != "200" ]]; then
  echo "[FAIL] invalid upload type request should return 200 JSON, got $CODE"
  exit 1
fi
assert_contains "$BODY" "Invalid file type" "Upload file-type validation"

# 4) Rate limit for scrub (8/10s)
RATE_HIT=0
for i in {1..9}; do
  CODE=$(status_and_body POST "action=scrub&csrf_token=$CSRF_TOKEN&text=test$i@example.com")
  BODY=$(cat "$BODY_FILE")
  if [[ "$BODY" == *"Too many requests"* ]]; then
    RATE_HIT=1
    if [[ "$CODE" != "429" ]]; then
      echo "[FAIL] rate-limit response should be 429, got $CODE"
      exit 1
    fi
    break
  fi
done
if [[ "$RATE_HIT" != "1" ]]; then
  echo "[FAIL] scrub rate limit did not trigger"
  exit 1
fi
echo "[OK] scrub rate limit enforced"

# 5) Encrypt then verify resume without passphrase fails
CODE=$(status_and_body POST "action=encrypt_session&csrf_token=$CSRF_TOKEN&passphrase=supersecurepass")
BODY=$(cat "$BODY_FILE")
assert_contains "$BODY" "\"status\":\"ok\"" "Encrypt session"

NEW_COOKIE_JAR="${ROOT_DIR}/tests/.cookies2.txt"
rm -f "$NEW_COOKIE_JAR"
HOME2=$(curl -sS -b "$NEW_COOKIE_JAR" -c "$NEW_COOKIE_JAR" "$BASE_URL/")
CSRF2=$(printf '%s' "$HOME2" | sed -n 's/.*name="csrf-token" content="\([a-f0-9]\{64\}\)".*/\1/p' | head -n1)
CODE=$(curl -sS -o "$BODY_FILE" -w "%{http_code}" -b "$NEW_COOKIE_JAR" -c "$NEW_COOKIE_JAR" --data "action=resume_session&csrf_token=$CSRF2&session_id=$SESSION_ID&passphrase=" "$BASE_URL/")
BODY=$(cat "$BODY_FILE")
assert_contains "$BODY" "Passphrase required for encrypted sessions" "Encrypted resume requires passphrase"
rm -f "$NEW_COOKIE_JAR"

echo "All security regression checks passed."
