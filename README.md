# SIMPEDA

<div style="text-align: center;">
  <img src="https://github.com/user-attachments/assets/a07ba3fb-5b64-49fa-8ccc-fc78d3321570" alt="Server Dashboard" style="width: 50%; height: auto;">
</div>
This project provides a simplified system information display for a server dashboard. It retrieves and displays various system information such as CPU usage, memory usage, disk usage, network interfaces, and process list.

## Features

- Basic server information (Hostname, OS, PHP Version, Server Software)
- CPU information (Model, Cores, Active Cores, CPU Usage)
- Memory usage (Total, Used, Available, Usage)
- Disk usage (Total, Used, Available, Usage)
- System uptime
- Load average (1min, 5min, 15min)
- Network interfaces (IP Address, MAC Address, RX Data, TX Data)
- Process list (User, PID, CPU, Memory, Command)
- Light/Dark theme toggle

## Prerequisites

- PHP 8.2 or higher with the curl extension (CI tests 8.2 to 8.5)
- Git installed on your system
- Currently supports Linux or Debian-based systems

## Usage

### API Endpoint

`GET /?api=1` returns the system information as JSON. It requires a logged-in session and answers `401` otherwise.

### Dashboard

The dashboard is an HTML page that displays the system information in a user-friendly format. It automatically updates every 30 seconds.

### Theme Toggle

You can toggle between light and dark themes using the "Toggle Theme" button. The selected theme is saved in the browser's local storage.

## Development

```bash
composer install            # only needed for PHPStan (dev dependency)
composer check              # lint, PHPStan, build, smoke tests
composer lint | analyse | build | test   # or run each step alone
php build.php serve 8000    # build, watch, and serve on http://localhost:8000
```

Without Composer you can still build with `php build.php build`.

`tests/smoke.sh` builds the app and runs it against a stub PocketBase (`tests/stub_pocketbase.php`, port 8090), so no real PocketBase is needed. It checks that the API is closed to anonymous and forged-cookie requests, that reinstalling is refused and that login works.

### Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `SIMPEDA_POCKETBASE_URL` | `http://127.0.0.1:8090` | PocketBase URL used for authentication |
| `SIMPEDA_VERSION` | `git describe` | Version stamped into `build/index.php` |

## CI and releases

- `.github/workflows/ci.yml` lints on PHP 8.2 to 8.5, runs PHPStan, builds and smoke-tests on every push to `master` and every PR.
- Pushing a `v*` tag runs the same checks, builds `build/index.php` and creates a draft GitHub release with a SHA-256 checksum and a build provenance attestation (`gh attestation verify index.php --repo Zxce3/simpeda`).
- Actions and Composer dependencies are kept current by Dependabot; actions are pinned by commit SHA.

## File Structure

- `src/`: source files, concatenated and minified into one file by the build.
  - `api.php`: request handling, PocketBase install/start.
  - `error_page.php`: error pages.
  - `components/`: page layout (`header.php`, `body.php`).
  - `pages/`: pages (`home`, `login`, `dashboard`, `install`, `404`).
  - `lib/`: `auth.php`, `pocketbase.php` (API client), `schema.php`, `SystemInformation.php`, `helper.php`, `css_style.php`, `js_script.php`.
- `build.php`: build script (`build`, `watch`, `serve`).
- `build/index.php`: build output (git-ignored).
- `tests/`: smoke test and stub PocketBase.
- `composer.json`, `phpstan.neon`: scripts and static analysis.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Version

Current version: v2.1

