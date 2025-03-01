#!/data/data/com.termux/files/usr/bin/sh
#
# server.sh - Server management script for j3-server-setup
#
# This script manages the starting and stopping of server services
# including nginx, php-fpm, cloudflared, and sshd.
#

ACTION="$1"
FORCE_MODE=false
[ "$2" = "--force" ] && FORCE_MODE=true

# Function to start a service
start_service() {
    local service="$1"
    local command="$2"
    if pgrep -x "$service" >/dev/null || pgrep -f "$command" >/dev/null; then
        echo "[  OK  ] $service is already running."
        return 1
    fi
    echo "[  ..  ] Starting: $service"
    $command >/dev/null 2>&1 &
    sleep 1
    if pgrep -x "$service" >/dev/null || pgrep -f "$command" >/dev/null; then
        echo "[  OK  ] $service started successfully."
    else
        echo "[ FAIL ] Failed to start $service."
    fi
}

# Function to stop a service
stop_service() {
    local service="$1"
    local process_name="$2"
    if ! pgrep -x "$process_name" >/dev/null && ! pgrep -f "$process_name" >/dev/null; then
        echo "[ SKIP ] $service is not running."
        return
    fi
    echo "[  ..  ] Stopping: $service"
    pkill -TERM -x "$process_name" || pkill -TERM -f "$process_name"
    for i in 1 2 3 4 5; do
        sleep 1
        if ! pgrep -x "$process_name" >/dev/null && ! pgrep -f "$process_name" >/dev/null; then
            echo "[  OK  ] $service stopped successfully."
            return
        fi
    done
    if [ "$FORCE_MODE" = true ]; then
        echo "[ WARN ] $service did not terminate, forcing kill."
        pkill -9 -x "$process_name" || pkill -9 -f "$process_name"
    else
        echo "[ FAIL ] $service did not terminate. Use '--force' to kill it."
    fi
}

# Start command processing
if [ "$ACTION" = "start" ]; then
    echo "[ INIT ] Starting server processes..."
    # Check for required programs
    for cmd in nginx php-fpm cloudflared sshd php; do
        if ! command -v "$cmd" >/dev/null 2>&1; then
            echo "[ FAIL ] $cmd not found!"
            exit 1
        fi
    done
    start_service "nginx" "nginx"
    start_service "php-fpm" "php-fpm"
    start_service "cloudflared" "termux-chroot cloudflared tunnel run my-tunnel"
    start_service "sshd" "sshd"

    # Start battery monitoring script
    BATTERY_PROCESS="php /data/data/com.termux/files/home/battery.php"
    if ! pgrep -f "$BATTERY_PROCESS" >/dev/null; then
        echo "[  ..  ] Starting battery.php"
        nohup $BATTERY_PROCESS >/dev/null 2>&1 &
        sleep 1
        if pgrep -f "$BATTERY_PROCESS" >/dev/null; then
            echo "[  OK  ] battery.php started successfully."
        else
            echo "[ FAIL ] Failed to start battery.php."
        fi
    else
        echo "[  OK  ] battery.php is already running."
    fi
    echo "[ DONE ] Server started."

# Stop command processing
elif [ "$ACTION" = "stop" ]; then
    echo "[ INIT ] Stopping server processes..."
    stop_service "nginx" "nginx"
    stop_service "php-fpm" "php-fpm"
    stop_service "cloudflared" "cloudflared"
    stop_service "battery.php" "php /data/data/com.termux/files/home/battery.php"

    # Handle SSH server separately
    if pgrep -f "sshd" >/dev/null; then
        if [ "$FORCE_MODE" = true ]; then
            stop_service "sshd" "sshd"
        else
            echo "[ INFO ] sshd is not being stopped as it may keep your session open."
            echo "[ INFO ] Use '--force' to stop it as well."
        fi
    else
        echo "[ SKIP ] sshd is not running."
    fi
    echo "[ DONE ] Server stopped."
else
    echo "Usage: $0 {start|stop} [--force]"
    exit 1
fi
