#!/usr/bin/env bash
# End-to-end test with a REAL PocketBase: runs the app's CLI installer (php index.php install) (downloads the
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
  [ -n "${worker_pid:-}" ] && kill "$worker_pid" 2>/dev/null
  [ -n "${hook_pid:-}" ] && kill "$hook_pid" 2>/dev/null
  rm -rf "$work"
}
trap cleanup EXIT
check() { if [ "$2" = "$3" ]; then echo "ok   $1"; else echo "FAIL $1 (expected $2, got $3)"; fail=1; fi; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
wait_for() { # wait_for SECONDS command...  -> succeeds as soon as the command does
  local limit=$1; shift
  for _ in $(seq 1 "$limit"); do "$@" >/dev/null 2>&1 && return 0; sleep 1; done; return 1
}
json() { php -r '$j=json_decode(stream_get_contents(STDIN),true); echo eval("return $argv[1];");' "$1"; }

for port in 8000 8090 8099; do
  if (exec 3<>/dev/tcp/127.0.0.1/$port) 2>/dev/null; then echo "port $port is in use"; exit 1; fi
done

(cd "$root" && php build.php build >/dev/null) || { echo "build failed"; exit 1; }
cp "$root/build/index.php" "$work/index.php"
(cd "$work" && exec php -S 127.0.0.1:8000 index.php >"$work/app.log" 2>&1) & app_pid=$!
sleep 1

check "fresh instance redirects to /install" 302 "$(code "$app/")"
check "install page renders" 200 "$(code "$app/install")"
check "web install is disabled" 403 "$(code -d "email=$email&password=$password" "$app/install")"

cli() { (cd "$work" && php index.php install "$@"); }
printf 'short\n' | cli --email="$email" >/dev/null 2>&1; check "CLI rejects a short password" 1 "$?"
printf 'x\n' | cli --email=not-an-email >/dev/null 2>&1; check "CLI rejects an invalid email" 1 "$?"
echo "installing PocketBase via CLI (downloads, starts, creates superuser and schema)..."
printf '%s\n' "$password" | cli --email="$email" >"$work/cli.log" 2>&1
check "CLI install succeeds" 0 "$?"
printf '%s\n' "$password" | cli --email="$email" >/dev/null 2>&1; check "CLI refuses to install twice" 1 "$?"

body=$(php -r 'echo json_encode(["identity"=>$argv[1],"password"=>$argv[2]]);' "$email" "$password")
token=$(curl -s -X POST "$pb/api/collections/_superusers/auth-with-password" -H 'Content-Type: application/json' -d "$body" | json '$j["token"] ?? ""')
check "superuser password stored exactly as entered" yes "$([ -n "$token" ] && echo yes || echo no)"

names=$(curl -s -H "Authorization: Bearer $token" "$pb/api/collections?perPage=200" \
  | json '(function($j){$n=array_column($j["items"] ?? [], "name"); sort($n); return implode(",", array_intersect($n, ["servers","metrics","monitors","monitor_results","alert_events","settings"]));})($j)')
check "schema collections created" alert_events,metrics,monitor_results,monitors,servers,settings "$names"
fields=$(curl -s -H "Authorization: Bearer $token" "$pb/api/collections/metrics" | json 'count($j["fields"] ?? [])')
check "metrics collection has its schema fields" yes "$([ "${fields:-0}" -ge 15 ] && echo yes || echo no)"

check "web reinstall is refused" 403 "$(code -d "email=evil@example.com&password=whatever123" "$app/install")"
check "api without auth is rejected" 401 "$(code "$app/?api=1")"
check "dashboard without auth redirects" 302 "$(code "$app/?dashboard")"

jar="$work/jar"
csrf=$(curl -s -c "$jar" -b "$jar" "$app/login" | sed -n 's/.*name="csrf" value="\([^"]*\)".*/\1/p' | head -1)
check "login with the real password succeeds" 302 "$(code -b "$jar" -c "$jar" --data-urlencode "csrf=$csrf" --data-urlencode "email=$email" --data-urlencode "password=$password" "$app/login")"
csrf=$(curl -s -c "$work/bad" -b "$work/bad" "$app/login" | sed -n 's/.*name="csrf" value="\([^"]*\)".*/\1/p' | head -1)
check "wrong password is rejected" 401 "$(code -b "$work/bad" -c "$work/bad" --data-urlencode "csrf=$csrf" --data-urlencode "email=$email" --data-urlencode "password=wrongpass1" "$app/login")"

api=$(curl -s -b "$jar" "$app/?api=1")
check "api returns system info when logged in" yes "$(printf '%s' "$api" | json 'isset($j["cpu"], $j["memory"], $j["processes"]) ? "yes" : "no"')"
check "dashboard renders when logged in" 200 "$(code -b "$jar" "$app/dashboard")"
check "dashboard redirects when logged out" 302 "$(code "$app/dashboard")"
check "forged cookie is rejected" 401 "$(code -H 'Cookie: auth_token=a.eyJleHAiOjk5OTk5OTk5OTl9.b' "$app/?api=1")"

# PocketBase is stopped: the app must restart it on demand.
for pid in $(pgrep -x pocketbase); do [ "$(readlink "/proc/$pid/cwd" 2>/dev/null)" = "$work" ] && kill "$pid"; done
port_closed() { ! (exec 3<>/dev/tcp/127.0.0.1/8090) 2>/dev/null; }
wait_for 15 port_closed
check "status endpoint restarts PocketBase" 202 "$(code "$app/?status")"
sleep 6
check "PocketBase is healthy again" 200 "$(code "$pb/api/health")"
check "status reports running" 200 "$(code "$app/?status")"

# --- multi-server features -----------------------------------------------------------
csrf_api=$(curl -s -b "$jar" "$app/api/session" | json '$j["csrf"] ?? ""')
# api METHOD URL [json]  ->  sets $code and $body
api() {
  local res; res=$(curl -s -w '\n%{http_code}' -b "$jar" -H "X-CSRF-Token: $csrf_api" -H 'Content-Type: application/json' "$@")
  code=${res##*$'\n'}; body=${res%$'\n'*}
}

check "API write without CSRF header is rejected" 403 "$(code -b "$jar" -X POST -H 'Content-Type: application/json' -d '{"name":"x"}' "$app/api/servers")"
api -X POST -d '{"name":"","country_code":"de"}' "$app/api/servers"; check "server name is required" 400 "$code"
api -X POST -d '{"name":"x","country_code":"DEU"}' "$app/api/servers"; check "country code is validated" 400 "$code"
api -X POST -d '{"name":"test-box","country_code":"de","note":"<b>x</b>"}' "$app/api/servers"
check "server is created" 201 "$code"
sid=$(printf '%s' "$body" | json '$j["id"]'); agent_token=$(printf '%s' "$body" | json '$j["token"]')

(cd "$work" && php index.php agent --url="$app" --token=bad-token --once >/dev/null 2>&1); check "agent with a bad token fails" 1 "$?"
(cd "$work" && php index.php agent --url="$app" --token="$agent_token" --once >/dev/null 2>&1); check "agent reports successfully" 0 "$?"

api "$app/api/servers"
check "server is online with live metrics" yes "$(printf '%s' "$body" | json '(function($j){$s=$j["servers"][0]??[];return (($s["online"]??false)&&is_numeric($s["live"]["cpu"]??null)&&!empty($s["host"]["hostname"]))?"yes":"no";})($j)')"
check "country code is normalised" DE "$(printf '%s' "$body" | json '$j["servers"][0]["country_code"] ?? ""')"
api "$app/api/servers/$sid/history?range=1h"
check "history has at least one point" yes "$(printf '%s' "$body" | json 'count($j["points"] ?? []) >= 1 ? "yes" : "no"')"
check "bad token is not stored as a plain value" no "$(curl -s -H "Authorization: Bearer $token" "$pb/api/collections/servers/records" | grep -q "$agent_token" && echo yes || echo no)"

for path in /dashboard /monitors /settings "/server?id=$sid"; do
  check "page $path renders when logged in" 200 "$(code -b "$jar" "$app$path")"
done
hdr=$(curl -s -D - -o "$work/page.html" -b "$jar" "$app/dashboard" | tr -d '\r')
nonce=$(printf '%s' "$hdr" | sed -n "s/.*script-src 'nonce-\([^']*\)'.*/\1/p" | head -1)
check "CSP nonce matches the page scripts" yes "$([ -n "$nonce" ] && grep -q "nonce=\"$nonce\"" "$work/page.html" && echo yes || echo no)"
check "pages are not framable" yes "$(printf '%s' "$hdr" | grep -qi '^X-Frame-Options: DENY' && echo yes || echo no)"

# monitors + worker
api -X POST -d '{"name":"bad","type":"http","target":"ftp://nope"}' "$app/api/monitors"; check "invalid monitor target is rejected" 400 "$code"
api -X POST -d "{\"name\":\"app\",\"type\":\"http\",\"target\":\"$app/login\",\"interval\":10}" "$app/api/monitors"; check "http monitor is created" 201 "$code"
api -X POST -d '{"name":"pocketbase","type":"tcp","target":"127.0.0.1:8090","interval":10}' "$app/api/monitors"; check "tcp monitor is created" 201 "$code"
api -X POST -d '{"name":"closed port","type":"tcp","target":"127.0.0.1:9","interval":10}' "$app/api/monitors"
(cd "$work" && exec php index.php worker >"$work/worker.log" 2>&1) & worker_pid=$!
monitors_done() { api "$app/api/monitors"; [ "$(printf '%s' "$body" | json 'count(array_filter($j["monitors"], fn($m) => $m["last_run"] > 0))')" = 3 ]; }
check "worker runs every monitor" yes "$(wait_for 40 monitors_done && echo yes || echo no)"
api "$app/api/monitors"
check "reachable monitors are up, the closed port is down" "app:1,closed port:0,pocketbase:1" "$(printf '%s' "$body" | json '(function($j){$o=[];foreach($j["monitors"] as $m){$o[]=$m["name"].":".($m["last_ok"]?1:0);}sort($o);return implode(",",$o);})($j)')"
check "uptime is computed" yes "$(printf '%s' "$body" | json '(function($j){foreach($j["monitors"] as $m){if($m["name"]==="app")return $m["uptime24"]==100?"yes":"no:".json_encode($m["uptime24"]);}return "missing";})($j)')"

# alerts + notifications
cat > "$work/hook.php" <<'PHP'
<?php file_put_contents(getenv('HOOK_LOG'), file_get_contents('php://input') . "\n", FILE_APPEND); echo 'ok';
PHP
(HOOK_LOG="$work/hooks.log" exec php -S 127.0.0.1:8099 "$work/hook.php" >/dev/null 2>&1) & hook_pid=$!
sleep 1
api -X PATCH -d '{"notify":{"type":"webhook","url":"http://127.0.0.1:8099/hook"},"alerts":{"offline_after":15}}' "$app/api/settings"
check "notification settings are saved" 200 "$code"
check "webhook secret is masked in the API" '********' "$(printf '%s' "$body" | json '$j["notify"]["url"]')"
api -X POST -d '{}' "$app/api/settings/test"; check "test notification is delivered" 200 "$code"
check "webhook received the test message" yes "$(wait_for 5 grep -q 'SIMPEDA test' "$work/hooks.log" && echo yes || echo no)"
check "server goes offline: alert is sent" yes "$(wait_for 45 grep -q '\[ALERT\] test-box' "$work/hooks.log" && echo yes || echo no)"
check "closed-port monitor alert is sent" yes "$(wait_for 45 grep -q '\[ALERT\] closed port' "$work/hooks.log" && echo yes || echo no)"
(cd "$work" && php index.php agent --url="$app" --token="$agent_token" --once >/dev/null 2>&1)
check "server comes back: recovery is sent" yes "$(wait_for 30 grep -q '\[OK\] test-box' "$work/hooks.log" && echo yes || echo no)"
api "$app/api/alerts"
check "alert history records resolved and open alerts" yes "$(printf '%s' "$body" | json '(function($j){$r=array_column($j["alerts"],"resolved");return (in_array(true,$r,true)&&in_array(false,$r,true))?"yes":"no";})($j)')"

# public status page
check "public status is off by default" 404 "$(code "$app/api/public")"
api -X PATCH -d '{"public_status":true,"site_title":"E2E Status"}' "$app/api/settings"
check "public page still hides servers not marked public" 0 "$(curl -s "$app/api/public" | json 'count($j["servers"])')"
api -X PATCH -d '{"public":true}' "$app/api/servers/$sid"; check "server can be marked public" 200 "$code"
pub=$(curl -s "$app/api/public")
check "public status lists the public server" test-box "$(printf '%s' "$pub" | json '$j["servers"][0]["name"] ?? ""')"
check "public status leaks no host details or tokens" no "$(printf '%s' "$pub" | grep -qiE 'hostname|token|kernel|cpu_model|note' && echo yes || echo no)"
check "status page renders without login" 200 "$(code "$app/status")"
api -X PATCH -d '{"public_status":false}' "$app/api/settings"

# agent download, migrate, delete
check "agent script is downloadable" yes "$(curl -s "$app/download/agent" | grep -q 'function runAgent' && echo yes || echo no)"
out=$(printf '%s\n' "$password" | (cd "$work" && php index.php migrate --email="$email" 2>&1)); check "migrate is idempotent" "Schema is up to date." "$out"
api -X DELETE "$app/api/servers/$sid"; check "server is deleted" 200 "$code"
left=$(curl -s -H "Authorization: Bearer $token" "$pb/api/collections/metrics/records?filter=server%3D%22$sid%22" | json '$j["totalItems"] ?? "?"')
check "its history is removed with it" 0 "$left"
api "$app/api/servers"; check "no servers are left" 0 "$(printf '%s' "$body" | json 'count($j["servers"])')"


[ $fail -eq 0 ] && echo "e2e tests passed" || { echo "e2e tests FAILED"; tail -15 "$work/app.log"; }
exit $fail
