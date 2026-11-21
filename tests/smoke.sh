#!/usr/bin/env bash
# Builds the app and exercises auth against a stub PocketBase (no real PocketBase needed).
# Usage: tests/smoke.sh   (needs php, curl; uses ports 8000 and 8090)
set -u
root=$(cd "$(dirname "$0")/.." && pwd)
work=$(mktemp -d)
pids=()
cleanup() { kill "${pids[@]}" 2>/dev/null; rm -rf "$work"; }
trap cleanup EXIT

(cd "$root" && php build.php build >/dev/null) || { echo "build failed"; exit 1; }
cp "$root/build/index.php" "$work/index.php"
mkdir -p "$work/pocketbase/pb_data"

php -S 127.0.0.1:8090 "$root/tests/stub_pocketbase.php" >/dev/null 2>&1 & pids+=($!)
(cd "$work" && exec php -S 127.0.0.1:8000 index.php >"$work/app.log" 2>&1) & pids+=($!)
for _ in $(seq 1 30); do curl -s -o /dev/null http://127.0.0.1:8000/login && break; sleep 0.2; done

fail=0
check() { # name expected actual
  if [ "$2" = "$3" ]; then echo "ok   $1"; else echo "FAIL $1 (expected $2, got $3)"; fail=1; fi
}
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
app=http://127.0.0.1:8000

check "api without auth is rejected" 401 "$(code "$app/?api=1")"

forged="a.$(printf '{"exp":9999999999}' | base64 -w0 | tr '+/' '-_' | tr -d '=').b"
check "api with forged cookie is rejected" 401 "$(code -H "Cookie: auth_token=$forged" "$app/?api=1")"

check "install on installed instance is refused" 409 "$(code -X POST -d 'email=evil@example.com&password=x' "$app/install")"

login=$(curl -s -i --data-urlencode 'email=admin@example.com' --data-urlencode 'password=p&ss<word>"1' "$app/?login")
token=$(printf '%s' "$login" | tr -d '\r' | sed -n 's/^Set-Cookie: auth_token=\([^;]*\).*/\1/p' | head -1)
check "login with special-character password sets a token" yes "$([ -n "$token" ] && echo yes || echo no)"

check "api with real token is allowed" 200 "$(code -H "Cookie: auth_token=$token" "$app/?api=1")"
check "wrong password gets no token" no "$(curl -s -i -d 'email=admin@example.com&password=nope' "$app/?login" | grep -q '^Set-Cookie: auth_token=[^;]' && echo yes || echo no)"

[ $fail -eq 0 ] && echo "smoke tests passed" || { echo "smoke tests FAILED"; tail -20 "$work/app.log"; }
exit $fail
