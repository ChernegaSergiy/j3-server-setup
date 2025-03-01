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

### Examples

Start all server processes:
```bash
./server.sh start
```

Stop all server processes (gracefully):
```bash
./server.sh stop
```

Force stop all processes (including SSH sessions):
```bash
./server.sh stop --force
```

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
🔋 Статус батареї:
• Рівень заряду: XX%
• Стан зарядки: Підключено до зарядного пристрою/Не підключено
• Статус: Заряджається/Розряджається/Заряд повний/Не заряджається
• Температура: YY°C
• Здоров'я: Хороший стан/Перегрів/Вмерла батарея/Не визначено
• Струм: ZZ µA
```

## Customizing the Scripts

You can modify both scripts to fit your specific needs:

### Modifying server.sh

To add a new service to manage:

1. Find the section with service definitions
2. Add your new service following the same pattern
3. Include the service in both start and stop action handlers

### Modifying battery.php

To add custom actions based on battery level:

1. Find the `getBatteryStatus` function which retrieves the current battery status.
2. Add your custom code within the `if` conditions in the `while` loop that handles updates and notifications.

For example, to add a custom action when battery percentage drops below a certain level:

```php
if ($battery['percentage'] < 15) {
    // Your custom action here
    sendToTelegram("⚠️ Battery level is critically low: {$battery['percentage']}%");
}
```

## Best Practices

- Always use the script's built-in commands rather than managing services manually
- Check the logs if services fail to start or stop properly
- When stopping services, only use `--force` when absolutely necessary
- Consider creating a cron job to ensure `battery.php` remains running
