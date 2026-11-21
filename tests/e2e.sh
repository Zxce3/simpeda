#!/usr/bin/env bash
# End-to-end test with a REAL PocketBase: runs the app's own installer (downloads the
# pinned PocketBase release from GitHub), then checks the schema, login and API.
# Needs network, php, curl, unzip; uses ports 8000 and 8090. Not run in CI.
set -u
root=$(cd "$(dirname "$0")/.." && pwd)
work=$(mktemp -d)
app=http://127.0.0.1:8000
pb=http://127.0.0.1:8090
email=admin@example.com
password='p&ss<word>"\1234'
fail=0

cleanup() {
  # PocketBase is started by the app itself, so find it by its working directory.
  for pid in $(pgrep -x pocketbase); do
    [ "$(readlink "/proc/$pid/cwd" 2>/dev/null)" = "$work" ] && kill "$pid" 2>/dev/null
  done
  [ -n "${app_pid:-}" ] && kill "$app_pid" 2>/dev/null
  rm -rf "$work"
}
trap cleanup EXIT
check() { if [ "$2" = "$3" ]; then echo "ok   $1"; else echo "FAIL $1 (expected $2, got $3)"; fail=1; fi; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
json() { php -r '$j=json_decode(stream_get_contents(STDIN),true); echo eval("return $argv[1];");' "$1"; }

for port in 8000 8090; do
  if (exec 3<>/dev/tcp/127.0.0.1/$port) 2>/dev/null; then echo "port $port is in use"; exit 1; fi
done

(cd "$root" && php build.php build >/dev/null) || { echo "build failed"; exit 1; }
cp "$root/build/index.php" "$work/index.php"
(cd "$work" && exec php -S 127.0.0.1:8000 index.php >"$work/app.log" 2>&1) & app_pid=$!
sleep 1

check "fresh instance redirects to /install" 302 "$(code "$app/")"
check "install page renders" 200 "$(code "$app/install")"
check "short password is rejected" 400 "$(code -d "email=$email&password=short" "$app/install")"

echo "installing PocketBase (downloads, starts, creates superuser and schema)..."
out=$(curl -s --max-time 120 --data-urlencode "email=$email" --data-urlencode "password=$password" "$app/install")
check "install succeeds" '{"message":"Installation successful"}' "$out"

body=$(php -r 'echo json_encode(["identity"=>$argv[1],"password"=>$argv[2]]);' "$email" "$password")
token=$(curl -s -X POST "$pb/api/collections/_superusers/auth-with-password" -H 'Content-Type: application/json' -d "$body" | json '$j["token"] ?? ""')
check "superuser password stored exactly as entered" yes "$([ -n "$token" ] && echo yes || echo no)"

names=$(curl -s -H "Authorization: Bearer $token" "$pb/api/collections?perPage=200" \
  | json '(function($j){$n=array_column($j["items"] ?? [], "name"); sort($n); return implode(",", array_intersect($n, ["cpu","global_info","host","server","state"]));})($j)')
check "schema collections created" cpu,global_info,host,server,state "$names"
fields=$(curl -s -H "Authorization: Bearer $token" "$pb/api/collections/pbc_state" | json 'count($j["fields"] ?? [])')
check "state collection has its schema fields" yes "$([ "${fields:-0}" -ge 12 ] && echo yes || echo no)"

check "reinstall is refused" 409 "$(code -d "email=evil@example.com&password=whatever123" "$app/install")"
check "api without auth is rejected" 401 "$(code "$app/?api=1")"
check "dashboard without auth redirects" 302 "$(code "$app/?dashboard")"

login=$(curl -s -i --data-urlencode "email=$email" --data-urlencode "password=$password" "$app/?login")
cookie=$(printf '%s' "$login" | tr -d '\r' | sed -n 's/^Set-Cookie: auth_token=\([^;]*\).*/\1/p' | head -1)
check "login sets auth cookie" yes "$([ -n "$cookie" ] && echo yes || echo no)"
check "wrong password gets no cookie" no "$(curl -s -i -d "email=$email&password=wrongpass1" "$app/?login" | grep -q '^Set-Cookie: auth_token=[^;]' && echo yes || echo no)"

api=$(curl -s -H "Cookie: auth_token=$cookie" "$app/?api=1")
check "api returns system info when logged in" yes "$(printf '%s' "$api" | json 'isset($j["cpu"], $j["memory"], $j["processes"]) ? "yes" : "no"')"
check "dashboard renders when logged in" 200 "$(code -H "Cookie: auth_token=$cookie" "$app/?dashboard")"
check "forged cookie is rejected" 401 "$(code -H 'Cookie: auth_token=a.eyJleHAiOjk5OTk5OTk5OTl9.b' "$app/?api=1")"

[ $fail -eq 0 ] && echo "e2e tests passed" || { echo "e2e tests FAILED"; tail -15 "$work/app.log"; }
exit $fail
