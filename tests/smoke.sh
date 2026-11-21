#!/usr/bin/env bash
# Builds the app and exercises auth against a stub PocketBase (no real PocketBase needed).
# Usage: tests/smoke.sh   (needs php, curl; uses ports 18000 and 18090, override with SIMPEDA_TEST_APP_PORT / SIMPEDA_TEST_PB_PORT)
set -u
# Ports are chosen to avoid clashing with a dashboard you run yourself (8000, 8080, 8090).
APP_PORT=${SIMPEDA_TEST_APP_PORT:-18000}
PB_PORT=${SIMPEDA_TEST_PB_PORT:-18090}
export SIMPEDA_PB_PORT=$PB_PORT
root=$(cd "$(dirname "$0")/.." && pwd)
work=$(mktemp -d)
pids=()
cleanup() { kill "${pids[@]}" 2>/dev/null; rm -rf "$work"; }
trap cleanup EXIT

for port in $APP_PORT $PB_PORT; do
  if (exec 3<>/dev/tcp/127.0.0.1/$port) 2>/dev/null; then echo "port $port is in use"; exit 1; fi
done

(cd "$root" && php build.php build >/dev/null) || { echo "build failed"; exit 1; }
cp "$root/build/index.php" "$work/index.php"
mkdir -p "$work/pocketbase/pb_data"

export STUB_REVOKED_FILE="$work/revoked"
export SIMPEDA_VERIFY_INTERVAL=1
php -S 127.0.0.1:$PB_PORT "$root/tests/stub_pocketbase.php" >/dev/null 2>&1 & pids+=($!)
(cd "$work" && exec php -S 127.0.0.1:$APP_PORT index.php >"$work/app.log" 2>&1) & pids+=($!)
for _ in $(seq 1 30); do curl -s -o /dev/null http://127.0.0.1:$APP_PORT/login && break; sleep 0.2; done

fail=0
check() { # name expected actual
  if [ "$2" = "$3" ]; then echo "ok   $1"; else echo "FAIL $1 (expected $2, got $3)"; fail=1; fi
}
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
app=http://127.0.0.1:$APP_PORT
password='p&ss<word>"1'

csrf_for() { curl -s -c "$1" -b "$1" "$app/login" | sed -n 's/.*name="csrf" value="\([^"]*\)".*/\1/p' | head -1; }
# login <jar> <email> <password> [extra curl args]  -> prints the HTTP status
login() {
  local jar=$1 email=$2 pass=$3; shift 3
  curl -s -o "$work/login.out" -w '%{http_code}' -b "$jar" -c "$jar" --data-urlencode "csrf=$(csrf_for "$jar")" \
    --data-urlencode "email=$email" --data-urlencode "password=$pass" "$@" "$app/login"
}

# --- anonymous access
check "api without auth is rejected" 401 "$(code "$app/?api=1")"
forged="a.$(printf '{"exp":9999999999}' | base64 -w0 | tr '+/' '-_' | tr -d '=').b"
check "api with forged cookie is rejected" 401 "$(code -H "Cookie: auth_token=$forged" "$app/?api=1")"
check "dashboard page without auth redirects" 302 "$(code "$app/dashboard")"
check "dashboard (query) without auth redirects" 302 "$(code "$app/?dashboard")"
check "login page renders" 200 "$(code "$app/login")"
check "unknown page is a 404" 404 "$(code "$app/nope")"
check "web install is disabled" 403 "$(code -X POST -d 'email=evil@example.com&password=whatever123' "$app/install")"

# --- login
jar="$work/jar"
check "login without CSRF token is rejected" 400 "$(code -b "$jar" -c "$jar" -d "email=admin@example.com&password=x" "$app/login")"
check "wrong password is rejected" 401 "$(login "$work/bad" admin@example.com nope)"
check "wrong password shows an error on the page" yes "$(grep -q 'Invalid email or password' "$work/login.out" && echo yes || echo no)"
check "login redirects to the dashboard" 302 "$(login "$jar" admin@example.com "$password")"
check "api works after login" 200 "$(code -b "$jar" "$app/?api=1")"
check "dashboard works after login" 200 "$(code -b "$jar" "$app/dashboard")"
check "api returns the expected sections" yes "$(curl -s -b "$jar" "$app/?api=1" | php -r '$j=json_decode(stream_get_contents(STDIN),true);echo isset($j["basic"],$j["cpu"],$j["memory"],$j["processes"])?"yes":"no";')"
check "login page redirects when logged in" 302 "$(code -b "$jar" "$app/login")"

# --- remember me
hdr=$(curl -s -D - -o /dev/null -c "$work/r" -b "$work/r" --data-urlencode "csrf=$(csrf_for "$work/r")" \
  --data-urlencode "email=admin@example.com" --data-urlencode "password=$password" -d remember=1 "$app/login" | tr -d '\r')
check "remember me sets a persistent auth cookie" yes "$(printf '%s' "$hdr" | grep -i '^Set-Cookie: auth_token=' | grep -qi 'expires=' && echo yes || echo no)"
hdr=$(curl -s -D - -o /dev/null -c "$work/s" -b "$work/s" --data-urlencode "csrf=$(csrf_for "$work/s")" \
  --data-urlencode "email=admin@example.com" --data-urlencode "password=$password" "$app/login" | tr -d '\r')
check "without remember me the auth cookie is a session cookie" no "$(printf '%s' "$hdr" | grep -i '^Set-Cookie: auth_token=' | grep -qi 'expires=' && echo yes || echo no)"

# --- token revoked in PocketBase: re-verified after the cache interval
touch "$work/revoked"
sleep 2
check "revoked token is rejected after re-verification" 401 "$(code -b "$jar" "$app/?api=1")"
rm -f "$work/revoked"

# --- logout
check "login again" 302 "$(login "$jar" admin@example.com "$password")"
check "logout without CSRF token does not log out" 200 "$(code -b "$jar" -c "$jar" -X POST "$app/?logout" >/dev/null; code -b "$jar" "$app/?api=1")"
csrf=$(curl -s -b "$jar" "$app/dashboard" | sed -n 's/.*name="csrf" value="\([^"]*\)".*/\1/p' | head -1)
check "logout redirects to login" 302 "$(code -b "$jar" -c "$jar" -d "csrf=$csrf" "$app/?logout")"
check "api is closed after logout" 401 "$(code -b "$jar" "$app/?api=1")"

[ $fail -eq 0 ] && echo "smoke tests passed" || { echo "smoke tests FAILED"; tail -20 "$work/app.log"; }
exit $fail
