# Scripts Usage Guide

This document explains how to use the utility scripts included in the j3-server-setup repository.

## server.sh

The `server.sh` script is a modular, configuration-driven utility for managing server processes. It allows you to start, stop, restart, and check the status of various services with a single command.

### Basic Usage

```bash
./server.sh {start|stop|restart|status} [--force]
```

### Commands

- `start`: Starts all configured server processes
- `stop`: Gracefully stops all configured server processes
- `restart`: Restarts all configured server processes
- `status`: Shows the current status of all services
- `--force`: (Optional) Forces processes to stop immediately if they don't respond to the normal termination signal

### Configuration

The script uses a configuration file to define which services to manage. Configuration files are loaded in the following priority order:

1. `~/.config/server/services.conf` (user configuration)
2. `$PREFIX/etc/server/services.conf` (system configuration)
3. Built-in defaults

#### Setting Up Configuration

```bash
# Create configuration directory
mkdir -p ~/.config/server

# Copy example configuration
cp scripts/server/services.conf.example ~/.config/server/services.conf

# Edit configuration
nano ~/.config/server/services.conf
```

### Default Services Managed

The script manages the following services by default:

- **nginx**: Web server
- **php-fpm**: PHP FastCGI Process Manager
- **cloudflared**: Cloudflare Tunnel for exposing local services to the internet
- **battery**: Battery monitoring script
- **sshd**: SSH server for remote access (protected by default)

### Examples

**Starting all server processes:**

```bash
./server.sh start
```

This command will start all configured server processes including `nginx`, `php-fpm`, `cloudflared`, `battery.php`, and `sshd`.

**Stopping all server processes gracefully:**

```bash
./server.sh stop
```

This command will stop all configured server processes gracefully, ensuring they have time to shut down properly. Note that `sshd` is protected by default and won't be stopped unless `--force` is used.

**Force stopping all processes (including SSH sessions):**

```bash
./server.sh stop --force
```

This command will forcefully stop all managed server processes, including any active SSH sessions and protected services.

**Restarting all server processes:**

```bash
./server.sh restart
```

This command will stop and then start all configured services.

**Checking the status of server processes:**

```bash
./server.sh status
```

This command will display the current status of all configured services.

### How It Works

The script:

1. Loads configuration from the highest priority config file available
2. Checks if required commands are available before starting services
3. Verifies if a process is already running before attempting to start it
4. Uses graceful termination signals first, followed by forced termination if necessary
5. Provides clear status messages for each operation
6. Supports custom start/stop commands per service

### Exit Codes

- `0`: Operation completed successfully
- `1`: Invalid command or missing dependency

## Adding New Services

To add a new service to the server management script, edit your configuration file (`~/.config/server/services.conf`):

### Step 1: Add Service to SERVICES List

```bash
SERVICES="nginx php-fpm mynewservice cloudflared battery sshd"
```

### Step 2: Define Service Properties

```bash
# Service name configuration
mynewservice_process="mynewservice"
mynewservice_start_command="/path/to/mynewservice --daemon"
mynewservice_required_command="mynewservice"
```

### Available Service Properties

- `<service>_process`: Process name for detection (required)
- `<service>_start_command`: Command to start the service (required)
- `<service>_stop_command`: Custom stop command (optional, defaults to pkill)
- `<service>_check_command`: Custom check if running (optional, defaults to pgrep)
- `<service>_required_command`: Binary to check before starting (optional)
- `<service>_skip_on_normal_stop`: Set to "true" to skip during normal stop (optional)

### Example: Adding Alpine PHP-FPM

To add PHP-FPM running in Alpine Linux via proot:

```bash
# Add to SERVICES list
SERVICES="nginx php-fpm alpine-php-fpm cloudflared battery sshd"

# Configure Alpine PHP-FPM
alpine_php_fpm_process="php-fpm84"
alpine_php_fpm_start_command="proot-distro login alpine --bind /data/data/com.termux/files/home:/root/home -- php-fpm84 -D"
alpine_php_fpm_check_command="proot-distro login alpine -- pgrep php-fpm84"
alpine_php_fpm_stop_command="proot-distro login alpine -- pkill php-fpm84"
```

### Example: Service with Custom Health Check

```bash
myapp_process="myapp"
myapp_start_command="cd ~/myapp && ./start.sh"
myapp_stop_command="cd ~/myapp && ./stop.sh"
myapp_check_command="curl -s http://localhost:8080/health | grep -q OK"
myapp_required_command="myapp"
```

## battery.php

The `battery.php` script monitors the device's battery level and sends notifications based on battery status.

### Basic Usage

Usually, this script is started automatically by `server.sh`, but you can also run it directly:

```bash
php battery.php
```

### Features

- Monitors battery level in real-time
- Sends notifications to a Telegram chat when the battery status changes

### Configuration

You can customize the script's behavior by editing the following values at the top of the file:

```php
const TELEGRAM_API_TOKEN = 'Ваш_Telegram_API_Token';
const CHAT_ID = 'Ваш_Chat_ID';
```

### Telegram Integration

The script sends messages to a specified Telegram chat using the Telegram Bot API. The message includes the current battery status, level, temperature, and health.

### Example Notification Message

```text
🔋 Battery Status:
• Charge Level: XX%
• Charging State: Connected to charger/Not connected
• Status: Charging/Discharging/Full/Not charging
• Temperature: YY°C
• Health: Good condition/Overheating/Battery dead/Unspecified
• Current: ZZ µA
```

## Customizing the Scripts

You can modify both scripts to fit your specific needs:

### Modifying server.sh

The modular design means you typically don't need to modify `server.sh` itself. Instead, you add new services via the configuration file:

1. Edit `~/.config/server/services.conf`
2. Add your new service to the `SERVICES` list
3. Define the service properties (see "Adding New Services" above)
4. Save the file and run `./server.sh start`

If you need to modify the core script behavior, the main functions are:

- `load_config()`: Loads configuration files
- `start_service()`: Handles service startup
- `stop_service()`: Handles service shutdown
- `status_service()`: Checks service status

### Modifying battery.php

To add custom actions based on battery temperature:

1. Find the `getBatteryStatus` function which retrieves the current battery status.
2. Add the following custom function to handle high battery temperature:

```php
/**
 * Monitor battery temperature and send alert if it exceeds a threshold.
 *
 * @param  array  $battery  Battery information
 * @return bool Success status
 */
function handleHighTemperature(array $battery) : bool
{
    $temperatureThreshold = 45.0; // Temperature threshold in Celsius
    if ($battery['temperature'] > $temperatureThreshold) {
        $message = "🔥 <b>High Temperature Alert</b> 🔥\n" .
                   "Battery temperature is at {$battery['temperature']}°C.\n" .
                   "This is above the safe threshold of {$temperatureThreshold}°C.\n" .
                   'Please check the device immediately!';
        $result = sendTelegramRequestAsync('sendMessage', [
            'chat_id' => CHAT_ID,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
        logMessage("High battery temperature detected: {$battery['temperature']}°C");
        return $result;
    }
    return true;
}
```

3. Call this function within the `while` loop that handles updates and notifications:

```php
while (true) {
    // Existing code...
    $battery = getBatteryStatus();
    if (null !== $battery) {
        handleHighTemperature($battery);
        // Other code...
    }
    // Existing code...
}
```

## Best Practices

- Always use the script's built-in commands rather than managing services manually
- Keep your service configuration in `~/.config/server/services.conf` for easy updates
- Use `./server.sh status` to verify services are running as expected
- Check the logs if services fail to start or stop properly
- When stopping services, only use `--force` when absolutely necessary
- Consider creating a cron job or using Termux:Boot to ensure services start automatically
- Document any custom services you add in your configuration file with comments

## Troubleshooting

### Service won't start

```bash
# Check if the service command exists
which nginx

# Check configuration
cat ~/.config/server/services.conf

# Try starting manually
nginx
```

### Service won't stop

```bash
# Use force mode
./server.sh stop --force
```

### Configuration not loading

```bash
# Check which config file is being used
./server.sh start
# Look for "Loading configuration from: ..." message

# Verify file exists
ls -la ~/.config/server/services.conf
```

### Service status shows wrong information

Some services may need custom check commands. For example, services running in proot environments:

```bash
myservice_check_command="proot-distro login alpine -- pgrep myservice"
```
