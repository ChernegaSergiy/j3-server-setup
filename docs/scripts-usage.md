# Scripts Usage Guide

This document explains how to use the utility scripts included in the j3-server-setup repository.

## server.sh

The `server.sh` script is a powerful utility for managing server processes. It allows you to start and stop various services with a single command.

### Basic Usage

```bash
./server.sh {start|stop} [--force]
```

### Parameters

- `start`: Starts all server processes
- `stop`: Gracefully stops all server processes
- `--force`: (Optional) Forces processes to stop immediately if they don't respond to the normal termination signal

### Services Managed

The script manages the following services:

- **nginx**: Web server
- **php-fpm**: PHP FastCGI Process Manager
- **cloudflared**: Cloudflare Tunnel for exposing local services to the internet
- **sshd**: SSH server for remote access
- **battery.php**: Battery monitoring script

Ось як можна покращити розділ "Examples" у документації:

### Examples

**Starting all server processes:**
```bash
./server.sh start
```
This command will start all the managed server processes including `nginx`, `php-fpm`, `cloudflared`, `sshd`, and the `battery.php` monitoring script.

**Stopping all server processes gracefully:**
```bash
./server.sh stop
```
This command will stop all the managed server processes gracefully, ensuring they have time to shut down properly.

**Force stopping all processes (including SSH sessions):**
```bash
./server.sh stop --force
```
This command will forcefully stop all the managed server processes, including any active SSH sessions, which can be useful if the processes do not respond to the normal termination signal.

**Checking the status of server processes:**
```bash
ps aux | grep -E 'nginx|php-fpm|cloudflared|sshd|battery.php'
```
This command will list the current status of the server processes managed by the script, allowing you to verify which processes are running.

### How It Works

The script:

1. Checks if required commands are available
2. Verifies if a process is already running before attempting to start it
3. Uses graceful termination signals first, followed by forced termination if necessary
4. Provides clear status messages for each operation

### Exit Codes

- `0`: Operation completed successfully
- `1`: Invalid command or missing dependency

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

To add a new service to manage:

1. Find the section with service definitions
2. Add your new service following the same pattern
3. Include the service in both start and stop action handlers

### Modifying battery.php

To add custom actions based on battery temperature:

1. Find the `getBatteryStatus` function which retrieves the current battery status.
2. Add the following custom function to handle high battery temperature:

```php
/**
 * Monitor battery temperature and send alert if it exceeds a threshold.
 *
 * @param array $battery Battery information
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
- Check the logs if services fail to start or stop properly
- When stopping services, only use `--force` when absolutely necessary
- Consider creating a cron job to ensure `battery.php` remains running
