#!/usr/bin/env bash
# SIMPEDA agent installer. Served by your dashboard at /install/agent.sh
#
#   curl -fsSL __URL__/install/agent.sh | sudo bash -s -- --token spk_...
#
# Installs PHP if needed, downloads the agent, tests the token and starts a
# systemd service. Run with --help for options.
set -euo pipefail

URL="__URL__"
TOKEN="${SIMPEDA_AGENT_TOKEN:-}"
INTERVAL="${SIMPEDA_INTERVAL:-5}"
DIR="/opt/simpeda"
RUN_USER="simpeda"
UNIT="simpeda-agent"
SERVICE=1
DEPS=1
ACTION="install"

usage() {
    cat <<EOF
SIMPEDA agent installer

Usage: install.sh --token TOKEN [options]

  --token TOKEN     server token from Settings -> Add server (or SIMPEDA_AGENT_TOKEN)
  --url URL         dashboard address (default: ${URL:-none, required})
  --interval N      seconds between reports (default: 5)
  --dir PATH        install folder (default: /opt/simpeda)
  --user NAME       user the service runs as (default: simpeda, created if missing)
  --no-service      do not create a systemd service, only install and test
  --no-deps         do not try to install PHP, fail if it is missing
  --update          download the latest agent and restart the service
  --uninstall       stop and remove the service and the install folder
  -h, --help        show this help
EOF
}

say() { printf '==> %s\n' "$*"; }
warn() { printf 'warning: %s\n' "$*" >&2; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }

while [ $# -gt 0 ]; do
    case "$1" in
        --token) TOKEN="${2:-}"; shift 2 || die "--token needs a value" ;;
        --token=*) TOKEN="${1#*=}"; shift ;;
        --url) URL="${2:-}"; shift 2 || die "--url needs a value" ;;
        --url=*) URL="${1#*=}"; shift ;;
        --interval) INTERVAL="${2:-}"; shift 2 || die "--interval needs a value" ;;
        --interval=*) INTERVAL="${1#*=}"; shift ;;
        --dir) DIR="${2:-}"; shift 2 || die "--dir needs a value" ;;
        --dir=*) DIR="${1#*=}"; shift ;;
        --user) RUN_USER="${2:-}"; shift 2 || die "--user needs a value" ;;
        --user=*) RUN_USER="${1#*=}"; shift ;;
        --no-service) SERVICE=0; shift ;;
        --no-deps) DEPS=0; shift ;;
        --update) ACTION="update"; shift ;;
        --uninstall) ACTION="uninstall"; shift ;;
        -h|--help) usage; exit 0 ;;
        *) die "unknown option: $1 (try --help)" ;;
    esac
done

URL="${URL%/}"
case "$DIR" in /*) ;; *) die "--dir must be an absolute path" ;; esac
case "$INTERVAL" in ''|*[!0-9]*) die "--interval must be a number" ;; esac
[ "$INTERVAL" -ge 1 ] || die "--interval must be at least 1"

if [ "$SERVICE" -eq 1 ]; then
    ENV_FILE="/etc/${UNIT}.env"
    UNIT_FILE="/etc/systemd/system/${UNIT}.service"
else
    ENV_FILE="$DIR/agent.env"
    UNIT_FILE=""
fi

need_root() {
    [ "$(id -u)" -eq 0 ] || die "run this as root (for example with sudo), or add --no-service to install without a system service"
}

has_systemd() {
    command -v systemctl >/dev/null 2>&1 && [ -d /run/systemd/system ]
}

php_ok() {
    command -v php >/dev/null 2>&1 \
        && php -r 'exit(PHP_VERSION_ID >= 80200 && extension_loaded("curl") ? 0 : 1);' 2>/dev/null
}

install_php() {
    if command -v apt-get >/dev/null 2>&1; then
        apt-get update -qq
        DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-cli php-curl curl
    elif command -v dnf >/dev/null 2>&1; then
        dnf install -y -q php-cli php-common curl
    elif command -v yum >/dev/null 2>&1; then
        yum install -y -q php-cli php-common curl
    elif command -v apk >/dev/null 2>&1; then
        apk add --no-cache php83 php83-curl php83-openssl curl
        if ! command -v php >/dev/null 2>&1 && command -v php83 >/dev/null 2>&1; then
            ln -sf "$(command -v php83)" /usr/local/bin/php
        fi
    elif command -v zypper >/dev/null 2>&1; then
        zypper -n install php8 php8-curl curl
    else
        return 1
    fi
}

remove_install() {
    if [ "$SERVICE" -eq 1 ] && has_systemd; then
        systemctl disable --now "$UNIT" >/dev/null 2>&1 || true
        rm -f "$UNIT_FILE"
        systemctl daemon-reload || true
    fi
    rm -f "$ENV_FILE"
    rm -rf "$DIR"
    say "Removed the agent ($DIR${UNIT_FILE:+, $UNIT_FILE}, $ENV_FILE)."
    if [ "$SERVICE" -eq 1 ]; then
        say "The '$RUN_USER' user was left in place."
    fi
}

download_agent() {
    [[ "$URL" =~ ^https?:// ]] || die "dashboard URL missing or invalid. Pass --url https://your-dashboard"
    command -v curl >/dev/null 2>&1 || die "curl is required"
    mkdir -p "$DIR"
    local tmp
    tmp="$(mktemp "$DIR/.agent.XXXXXX")"
    # shellcheck disable=SC2064
    trap "rm -f '$tmp'" EXIT
    say "Downloading the agent from $URL/download/agent"
    curl -fsSL --connect-timeout 10 --max-time 60 "$URL/download/agent" -o "$tmp" \
        || die "could not download the agent. Is $URL reachable from this server?"
    head -c 5 "$tmp" | grep -q '^<?php' || die "the download is not the agent. Check the dashboard URL."
    php -l "$tmp" >/dev/null || die "the downloaded agent has a syntax error"
    install -m 0644 "$tmp" "$DIR/simpeda.php"
}

test_agent() {
    say "Testing the token with one report"
    SIMPEDA_URL="$URL" SIMPEDA_AGENT_TOKEN="$TOKEN" SIMPEDA_INTERVAL="$INTERVAL" \
        php "$DIR/simpeda.php" agent --once \
        || die "the test report failed (see the message above). Nothing was started."
}

# ---- uninstall ---------------------------------------------------------------------------
if [ "$ACTION" = "uninstall" ]; then
    [ "$SERVICE" -eq 0 ] || need_root
    remove_install
    exit 0
fi

# ---- update ------------------------------------------------------------------------------
if [ "$ACTION" = "update" ]; then
    [ "$SERVICE" -eq 0 ] || need_root
    php_ok || die "PHP 8.2+ with the curl extension is required"
    download_agent
    if [ "$SERVICE" -eq 1 ] && has_systemd; then
        systemctl restart "$UNIT"
        say "Updated and restarted $UNIT."
    else
        say "Updated $DIR/simpeda.php."
    fi
    exit 0
fi

# ---- install -----------------------------------------------------------------------------
[ -n "$TOKEN" ] || die "a token is required. Pass --token TOKEN (Settings -> Add server)"
[[ "$TOKEN" =~ ^[A-Za-z0-9_-]+$ ]] || die "the token looks invalid"
[ "$(uname -s)" = "Linux" ] || die "the agent only supports Linux"
[ "$SERVICE" -eq 0 ] || need_root

if ! php_ok; then
    if [ "$DEPS" -eq 1 ]; then
        need_root
        say "Installing PHP (cli and curl)"
        install_php || die "no supported package manager found. Install PHP 8.2+ with the curl extension, then run this again."
    fi
    php_ok || die "PHP 8.2+ with the curl extension is required (found: $(php -v 2>/dev/null | head -n1 || echo none))"
fi
PHP_BIN="$(command -v php)"
say "Using PHP $("$PHP_BIN" -r 'echo PHP_VERSION;') at $PHP_BIN"

download_agent
test_agent

umask 077
mkdir -p "$(dirname "$ENV_FILE")"
{
    echo "SIMPEDA_URL=$URL"
    echo "SIMPEDA_AGENT_TOKEN=$TOKEN"
    echo "SIMPEDA_INTERVAL=$INTERVAL"
} > "$ENV_FILE"
chmod 600 "$ENV_FILE"
umask 022

if [ "$SERVICE" -eq 0 ]; then
    say "Installed without a service."
    echo
    echo "Run it with:"
    echo "  set -a; . $ENV_FILE; set +a; $PHP_BIN $DIR/simpeda.php agent"
    exit 0
fi

has_systemd || die "systemd was not found. Re-run with --no-service and start the agent yourself."

if ! id "$RUN_USER" >/dev/null 2>&1; then
    if useradd --system --no-create-home --shell /usr/sbin/nologin "$RUN_USER" 2>/dev/null \
        || adduser -S -H -s /sbin/nologin "$RUN_USER" 2>/dev/null; then
        say "Created the '$RUN_USER' user"
    else
        warn "could not create the '$RUN_USER' user, the service will run as root"
        RUN_USER="root"
    fi
fi

cat > "$UNIT_FILE" <<EOF
[Unit]
Description=SIMPEDA agent
After=network-online.target
Wants=network-online.target

[Service]
User=$RUN_USER
EnvironmentFile=$ENV_FILE
ExecStart=$PHP_BIN $DIR/simpeda.php agent
Restart=always
RestartSec=5
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now "$UNIT" >/dev/null
sleep 2
if systemctl is-active --quiet "$UNIT"; then
    say "The agent is running as the '$UNIT' service."
else
    systemctl --no-pager status "$UNIT" || true
    die "the service did not start. See: journalctl -u $UNIT"
fi

cat <<EOF

Done. The server should show as online on your dashboard within a few seconds.

  status:     systemctl status $UNIT
  logs:       journalctl -u $UNIT -f
  update:     curl -fsSL $URL/install/agent.sh | sudo bash -s -- --update
  uninstall:  curl -fsSL $URL/install/agent.sh | sudo bash -s -- --uninstall
EOF
