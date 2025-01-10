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

## Usage

### API Endpoint

The `index.php` file provides an API endpoint for AJAX updates. To fetch the system information in JSON format, send a GET request to `index.php?api=1`.

### Dashboard

The dashboard is an HTML page that displays the system information in a user-friendly format. It automatically updates every 30 seconds.

### Theme Toggle

You can toggle between light and dark themes using the "Toggle Theme" button. The selected theme is saved in the browser's local storage.

## File Structure

- `index.php`: Main file containing the PHP code to retrieve system information and the HTML/CSS/JavaScript for the dashboard.
- `README.md`: Documentation file.

## How to Run

1. Place the `index.php` file on your server.
2. Access the file via your web browser.
3. The dashboard will display the system information and update automatically.

## Example

To see the dashboard in action, open your web browser and navigate to the URL where `index.php` is hosted.
