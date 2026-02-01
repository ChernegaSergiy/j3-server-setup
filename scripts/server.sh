#!/data/data/com.termux/files/usr/bin/sh
#
# server.sh - Modular Server Management Script
#
# This script manages server services in a modular way using a configuration file.
# Configuration can be loaded from:
#   1. ~/.config/server/services.conf (user config)
#   2. /data/data/com.termux/files/usr/etc/server/services.conf (system config)
#   3. Built-in defaults
#

set -e

# ============================================================================
# CONFIGURATION LOADING
# ============================================================================

USER_CONFIG="$HOME/.config/server/services.conf"
SYSTEM_CONFIG="$PREFIX/etc/server/services.conf"

# Default configuration (can be overridden by config files)
SERVICES=""

# Load configuration file
load_config() {
    local config_file=""
    
    if [ -f "$USER_CONFIG" ]; then
        config_file="$USER_CONFIG"
    elif [ -f "$SYSTEM_CONFIG" ]; then
        config_file="$SYSTEM_CONFIG"
    else
        echo "[ WARN ] No configuration file found. Using built-in defaults."
        use_builtin_config
        return
    fi
    
    echo "[ INFO ] Loading configuration from: $config_file"
    # Source the configuration file
    . "$config_file"
}

# Built-in default services configuration
use_builtin_config() {
    SERVICES="nginx php-fpm cloudflared battery sshd"
}

# ============================================================================
# SERVICE MANAGEMENT FUNCTIONS
# ============================================================================

# Function to get service configuration
get_service_config() {
    local service="$1"
    local property="$2"
    
    # Use eval to get the dynamic variable name
    eval echo "\$${service}_${property}"
}

# Function to start a service
start_service() {
    local service="$1"
    local process_name=$(get_service_config "$service" "process")
    local start_cmd=$(get_service_config "$service" "start_command")
    local check_cmd=$(get_service_config "$service" "check_command")
    
    # Use custom check command if provided, otherwise use pgrep
    if [ -n "$check_cmd" ]; then
        if eval "$check_cmd" >/dev/null 2>&1; then
            echo "[  OK  ] $service is already running."
            return 1
        fi
    else
        if pgrep -x "$process_name" >/dev/null || pgrep -f "$process_name" >/dev/null; then
            echo "[  OK  ] $service is already running."
            return 1
        fi
    fi
    
    echo "[  ..  ] Starting: $service"
    eval "$start_cmd" >/dev/null 2>&1 &
    sleep 1
    
    # Verify service started
    if [ -n "$check_cmd" ]; then
        if eval "$check_cmd" >/dev/null 2>&1; then
            echo "[  OK  ] $service started successfully."
        else
            echo "[ FAIL ] Failed to start $service."
        fi
    else
        if pgrep -x "$process_name" >/dev/null || pgrep -f "$process_name" >/dev/null; then
            echo "[  OK  ] $service started successfully."
        else
            echo "[ FAIL ] Failed to start $service."
        fi
    fi
}

# Function to stop a service
stop_service() {
    local service="$1"
    local process_name=$(get_service_config "$service" "process")
    local stop_cmd=$(get_service_config "$service" "stop_command")
    local check_cmd=$(get_service_config "$service" "check_command")
    local skip_on_normal=$(get_service_config "$service" "skip_on_normal_stop")
    
    # Check if service should be skipped during normal stop
    if [ "$skip_on_normal" = "true" ] && [ "$FORCE_MODE" != true ]; then
        echo "[ INFO ] $service is not being stopped (use --force to stop it)."
        return
    fi
    
    # Check if service is running
    if [ -n "$check_cmd" ]; then
        if ! eval "$check_cmd" >/dev/null 2>&1; then
            echo "[ SKIP ] $service is not running."
            return
        fi
    else
        if ! pgrep -x "$process_name" >/dev/null && ! pgrep -f "$process_name" >/dev/null; then
            echo "[ SKIP ] $service is not running."
            return
        fi
    fi
    
    echo "[  ..  ] Stopping: $service"
    
    # Use custom stop command if provided
    if [ -n "$stop_cmd" ]; then
        eval "$stop_cmd" >/dev/null 2>&1
    else
        pkill -TERM -x "$process_name" || pkill -TERM -f "$process_name"
    fi
    
    # Wait for service to stop
    for i in 1 2 3 4 5; do
        sleep 1
        if [ -n "$check_cmd" ]; then
            if ! eval "$check_cmd" >/dev/null 2>&1; then
                echo "[  OK  ] $service stopped successfully."
                return
            fi
        else
            if ! pgrep -x "$process_name" >/dev/null && ! pgrep -f "$process_name" >/dev/null; then
                echo "[  OK  ] $service stopped successfully."
                return
            fi
        fi
    done
    
    # Force kill if requested
    if [ "$FORCE_MODE" = true ]; then
        echo "[ WARN ] $service did not terminate, forcing kill."
        pkill -9 -x "$process_name" || pkill -9 -f "$process_name"
    else
        echo "[ FAIL ] $service did not terminate. Use '--force' to kill it."
    fi
}

# Function to check service status
status_service() {
    local service="$1"
    local process_name=$(get_service_config "$service" "process")
    local check_cmd=$(get_service_config "$service" "check_command")
    
    if [ -n "$check_cmd" ]; then
        if eval "$check_cmd" >/dev/null 2>&1; then
            echo "[  OK  ] $service is running."
        else
            echo "[ STOP ] $service is not running."
        fi
    else
        if pgrep -x "$process_name" >/dev/null || pgrep -f "$process_name" >/dev/null; then
            echo "[  OK  ] $service is running."
        else
            echo "[ STOP ] $service is not running."
        fi
    fi
}

# ============================================================================
# COMMAND PROCESSING
# ============================================================================

ACTION="$1"
FORCE_MODE=false
[ "$2" = "--force" ] && FORCE_MODE=true

# Load configuration
load_config

# Validate that required commands exist (only for start)
check_dependencies() {
    local missing=""
    for service in $SERVICES; do
        local required=$(get_service_config "$service" "required_command")
        if [ -n "$required" ] && ! command -v "$required" >/dev/null 2>&1; then
            missing="$missing $required"
        fi
    done
    
    if [ -n "$missing" ]; then
        echo "[ FAIL ] Missing required commands:$missing"
        exit 1
    fi
}

case "$ACTION" in
    start)
        echo "[ INIT ] Starting server processes..."
        check_dependencies
        
        for service in $SERVICES; do
            start_service "$service"
        done
        
        echo "[ DONE ] Server started."
        ;;
        
    stop)
        echo "[ INIT ] Stopping server processes..."
        
        for service in $SERVICES; do
            stop_service "$service"
        done
        
        echo "[ DONE ] Server stopped."
        ;;
        
    restart)
        echo "[ INIT ] Restarting server processes..."
        $0 stop $2
        sleep 2
        $0 start $2
        ;;
        
    status)
        echo "[ INFO ] Service status:"
        for service in $SERVICES; do
            status_service "$service"
        done
        ;;
        
    *)
        echo "Usage: $0 {start|stop|restart|status} [--force]"
        echo ""
        echo "Commands:"
        echo "  start    - Start all configured services"
        echo "  stop     - Stop all configured services"
        echo "  restart  - Restart all configured services"
        echo "  status   - Show status of all services"
        echo ""
        echo "Options:"
        echo "  --force  - Force stop services that don't terminate gracefully"
        exit 1
        ;;
esac
