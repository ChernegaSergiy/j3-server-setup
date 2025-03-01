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

## Customizing the Scripts

You can modify both scripts to fit your specific needs:

### Modifying server.sh

To add a new service to manage:

1. Find the section with service definitions
2. Add your new service following the same pattern
3. Include the service in both start and stop action handlers

### Modifying battery.php

To add custom actions based on battery level:

1. Find the appropriate condition check (critical, warning, etc.)
2. Add your custom code within the conditional block

## Best Practices

- Always use the script's built-in commands rather than managing services manually
- Check the logs if services fail to start or stop properly
- When stopping services, only use `--force` when absolutely necessary
- Consider creating a cron job to ensure `battery.php` remains running
