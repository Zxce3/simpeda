# SIMPEDA

<div style="text-align: center;">
  <img src="https://github.com/user-attachments/assets/a07ba3fb-5b64-49fa-8ccc-fc78d3321570" alt="Server Dashboard" style="width: 50%; height: auto;">
</div>
SIMPEDA is a self-hosted, single-file server monitoring dashboard in the spirit of [Nezha](https://github.com/nezhahq/nezha): watch many servers from one place, with live metrics, history charts, service monitors, alerts and an optional public status page. It is one PHP file plus [PocketBase](https://pocketbase.io) for storage.

## Features

- **Multi-server dashboard**: cards or table view with CPU, memory, disk, network speed, load, uptime and traffic per server, online/offline status, flags, search and filters. Light/dark theme and a mobile layout.
- **History charts**: CPU, memory/swap, network, load and disk over 1h, 6h, 24h and 7d (one sample per minute, kept 7 days by default).
- **Agents**: any Linux server with PHP 8.2+ reports to the dashboard with `php simpeda.php agent`. Per-server tokens, stored hashed.
- **Service monitors**: HTTP(S), TCP and ping checks with latency, uptime percentage and a result timeline.
- **Alerts**: server offline, sustained CPU/memory, disk, and monitor failures, with recovery messages, sent to Discord, Telegram or any webhook.
- **Public status page** (opt-in, `/status`): shows only the servers and monitors you mark as public, with coarse data (no IPs, processes or logs).
- Security: PocketBase superuser created from the CLI, CSRF tokens, Content-Security-Policy, session verification against PocketBase.

## Prerequisites

- PHP 8.2 or higher with the curl extension (CI tests 8.2 to 8.5) and `unzip`
- Linux (metrics come from `/proc`)

## Setup

```bash
php build.php build                         # or take build/index.php from a release
php index.php install                       # first superuser: prompts for email + hidden password
php -S 0.0.0.0:8000 index.php               # or serve index.php with nginx/Apache + PHP-FPM
php index.php worker                        # monitors, alerts, pruning (keep it running)
```

`install` downloads the pinned PocketBase release (checksum-verified), starts it, creates the superuser, the collections and a service account (`pocketbase/service.json`, mode 0600). There is no web installer: `/install` only shows these instructions. PocketBase's own `superuser create` takes the password as an argument, so it is briefly visible in the process list during install.

Keep the `pocketbase/` directory out of the web root or deny it in your web server (it holds the database and service credentials), or relocate it with `SIMPEDA_PB_DIR`. After upgrading SIMPEDA, run `php index.php migrate` to apply schema changes.

### Adding servers

1. Sign in, open **Settings → Add server** and give it a name.
2. The dialog shows the token **once** with the commands to run on that server:

```bash
curl -fsSL https://dash.example.com/download/agent -o simpeda.php
php simpeda.php agent --url=https://dash.example.com --token=spk_...   # add --once to test
```

The dialog also shows a systemd unit to keep the agent running. To monitor the dashboard host itself, add it like any other server.

### Running the worker

`php index.php worker` runs service monitors, evaluates alerts and prunes old data. It must stay running (systemd, supervisor, `nohup`). Without it monitors do not run and no alerts are sent. Run it from the same directory as `index.php`.

## Usage

- `/dashboard` servers, `/server?id=` detail and charts, `/monitors`, `/settings` (servers, public page, alert thresholds, notification channel, recent alerts).
- `/status` is the public page once enabled in Settings.
- JSON API under `/api/*` (session + `X-CSRF-Token` for writes, agent token for `/api/agent/report`).
- `GET /?api=1` is the legacy single-host snapshot (login required).

## Development

```bash
composer install            # only needed for PHPStan (dev dependency)
composer check              # lint, PHPStan, build, smoke tests
composer lint | analyse | build | test   # or run each step alone
php build.php serve 8000    # build, watch, and serve on http://localhost:8000
```

`composer test:e2e` (`tests/e2e.sh`) goes further: it runs the CLI installer (which downloads the pinned PocketBase release), then checks the schema, login, agents, history, monitors, the worker, alerts (via a local webhook), the public page and CSP against the real thing. It needs network access, ports 8000, 8090 and 8099, and takes about two minutes.

Without Composer you can still build with `php build.php build`.

`tests/smoke.sh` builds the app and runs it against a stub PocketBase (`tests/stub_pocketbase.php`, port 8090), so no real PocketBase is needed. It checks that the API is closed to anonymous and forged-cookie requests, that reinstalling is refused and that login works.

### Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `SIMPEDA_POCKETBASE_URL` | `http://127.0.0.1:8090` | PocketBase URL used for authentication |
| `SIMPEDA_VERSION` | `git describe` | Version stamped into `build/index.php` |
| `SIMPEDA_PB_DIR` | `pocketbase` | Directory holding PocketBase, its data and the service credentials |
| `SIMPEDA_RETENTION_DAYS` | `7` | Days of history and results the worker keeps (1 to 90) |
| `SIMPEDA_VERIFY_INTERVAL` | `300` | Seconds a verified login token is trusted before PocketBase is asked again |
| `SIMPEDA_AGENT_TOKEN` | – | Agent token, as an alternative to `--token` |

## CI and releases

- `.github/workflows/ci.yml` lints on PHP 8.2 to 8.5, runs PHPStan, builds, smoke-tests and runs the end-to-end test on every push to `master` and every PR.
- Pushing a `v*` tag runs the same checks, builds `build/index.php` and creates a draft GitHub release with a SHA-256 checksum and a build provenance attestation (`gh attestation verify index.php --repo Zxce3/simpeda`).
- Actions and Composer dependencies are kept current by Dependabot; actions are pinned by commit SHA.

## File Structure

- `src/api.php`: request routing, PocketBase install/start, CLI commands (`install`, `migrate`, `worker`, `agent`).
- `src/lib/`: `agent.php` (metrics, agent loop, ingest), `worker.php` (monitors, alerts, pruning), `http_api.php` (JSON API), `store.php` and `schema.php` (PocketBase access and collections), `auth.php`, `pocketbase.php` (API client), `SystemInformation.php` (legacy snapshot), `helper.php`, `css_style.php`, `js_script.php`.
- `src/components/`, `src/pages/`: layout and pages.
- `build.php`: build script (`build`, `watch`, `serve`); output in `build/index.php` (git-ignored). Files are concatenated, so files in `src/` may only declare functions and classes (`api.php` runs first).
- `tests/`: `smoke.sh` (stub PocketBase), `e2e.sh` (real PocketBase).
- `composer.json`, `phpstan.neon`: scripts and static analysis.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Version

Current version: v2.1

