# Server Dashboard

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

- PHP 8 or higher installed on your system
- Git installed on your system
- Currently supports Linux or Debian-based systems

## Usage

### API Endpoint

The `api.php` file provides an API endpoint for AJAX updates. To fetch the system information in JSON format, send a GET request to `api.php?api=1`.

### Dashboard

The dashboard is an HTML page that displays the system information in a user-friendly format. It automatically updates every 30 seconds.

### Theme Toggle

You can toggle between light and dark themes using the "Toggle Theme" button. The selected theme is saved in the browser's local storage.

## File Structure

- `index.php`: Main file containing the PHP code to retrieve system information and the HTML/CSS/JavaScript for the dashboard.
- `README.md`: Documentation file.
- `build/`: Directory containing build-related files.
  - `index.php`: Build version of the main file.
  - `pocketbase/`: Directory containing PocketBase files.
    - `pb_data/`: Directory containing PocketBase data files.
    - `pocketbase`: PocketBase executable.
    - `CHANGELOG.md`: PocketBase changelog.
    - `LICENSE.md`: PocketBase license.
  - `pocketbase.log`: Log file for PocketBase.
- `build.md`: Build documentation.
- `build.php`: Build script.
- `src/`: Directory containing source files.
  - `api.php`: PHP file for API endpoints.
  - `auth.php`: PHP file for authentication handling.
  - `css_style.php`: PHP file containing CSS styles.
  - `dashboard.php`: PHP file for displaying the dashboard.
  - `error_page.php`: PHP file for displaying error pages.
  - `home.php`: PHP file for displaying the home page.
  - `install.php`: PHP file for installation.
  - `js_script.php`: PHP file containing JavaScript code.
  - `lib/`: Directory containing library files.
    - `pocketbase.php`: PHP file for interacting with PocketBase.
  - `SystemInformation.php`: PHP file with functions to retrieve system information.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Version

Current version: v2.1

