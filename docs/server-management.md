# Modular Server Management Script

A flexible, configuration-driven server management script for Termux.

## Features

- Modular service configuration
- Start, stop, restart, and status commands
- Custom start/stop commands per service
- Graceful shutdown with force option
- Configuration file support

## Installation

```bash
# Copy the main script
cp server.sh $PREFIX/bin/server
chmod +x $PREFIX/bin/server

# Create configuration directory
mkdir -p ~/.config/server

# Copy example configuration
cp services.conf ~/.config/server/services.conf
```

## Configuration

Edit `~/.config/server/services.conf` to customize your services.

### Configuration Locations (priority order)

1. `~/.config/server/services.conf` (user configuration)
2. `$PREFIX/etc/server/services.conf` (system configuration)
3. Built-in defaults

### Adding a New Service

To add a new service, edit the configuration file:

```bash
# 1. Add service name to SERVICES list
SERVICES="nginx php-fpm mynewservice"

# 2. Define service properties
mynewservice_process="mynewservice"
mynewservice_start_command="/path/to/mynewservice --daemon"
mynewservice_required_command="mynewservice"
```

### Service Properties

- `<service>_process` - Process name for detection (required)
- `<service>_start_command` - Command to start the service (required)
- `<service>_stop_command` - Custom stop command (optional, defaults to pkill)
- `<service>_check_command` - Custom check if running (optional, defaults to pgrep)
- `<service>_required_command` - Binary to check before starting (optional)
- `<service>_skip_on_normal_stop` - Set to "true" to skip during normal stop (optional)

### Example: Adding PHP-FPM in Alpine

```bash
# Add to SERVICES list
SERVICES="nginx php-fpm alpine-php-fpm"

# Configure Alpine PHP-FPM
alpine_php_fpm_process="php-fpm84"
alpine_php_fpm_start_command="proot-distro login alpine --bind /data/data/com.termux/files/home:/root/home -- php-fpm84 -D"
alpine_php_fpm_check_command="proot-distro login alpine -- pgrep php-fpm84"
alpine_php_fpm_stop_command="proot-distro login alpine -- pkill php-fpm84"
```

## Usage

```bash
# Start all services
server start

# Stop all services
server stop

# Stop all services including protected ones (like sshd)
server stop --force

# Restart all services
server restart

# Check status of all services
server status
```

## Examples

### Minimal Configuration

```bash
SERVICES="nginx php-fpm"

nginx_process="nginx"
nginx_start_command="nginx"

php_fpm_process="php-fpm"
php_fpm_start_command="php-fpm"
```

### Advanced Configuration with Custom Commands

```bash
SERVICES="myapp"

myapp_process="myapp"
myapp_start_command="cd ~/myapp && ./start.sh"
myapp_stop_command="cd ~/myapp && ./stop.sh"
myapp_check_command="curl -s http://localhost:8080/health | grep -q OK"
myapp_required_command="myapp"
```

## Troubleshooting

### Service Won't Start

1. Check if the required command exists:
   ```bash
   which nginx
   ```

2. Check the configuration:
   ```bash
   cat ~/.config/server/services.conf
   ```

3. Try starting manually:
   ```bash
   nginx
   ```

### Service Won't Stop

Use the `--force` option:

```bash
server stop --force
```

### Configuration Not Loading

Check which config file is being used:

```bash
server start
# Look for "Loading configuration from: ..." message
```

## Contributing

To add new features or services, edit the configuration file. The script is designed to be extended without modifying the main script.
