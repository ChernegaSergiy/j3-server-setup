#!/data/data/com.termux/files/usr/bin/bash
#
# server.sh - Modular Server Management Script
#
# This script manages server services in a modular way using a configuration file.
# Configuration can be loaded from:
#   1. ~/.config/server/services.conf (user config)
#   2. /data/data/com.termux/files/usr/etc/server/services.conf (system config)
#
# NOTE: Requires Bash 4.0+ for associative arrays.
#

set -e

# ============================================================================
# INITIALIZATION
# ============================================================================

# Declare associative arrays for configuration
declare -A CONF_PROCESS
declare -A CONF_START
declare -A CONF_STOP
declare -A CONF_CHECK
declare -A CONF_REQUIRED
declare -A CONF_SKIP_STOP

# Order of services execution
declare -a SERVICE_ORDER

USER_CONFIG="$HOME/.config/server/services.conf"
SYSTEM_CONFIG="$PREFIX/etc/server/services.conf"

# ============================================================================
# CONFIGURATION LOADING
# ============================================================================

# Load configuration file
load_config() {
    local config_file=""
    
    if [ -f "$USER_CONFIG" ]; then
        config_file="$USER_CONFIG"
    elif [ -f "$SYSTEM_CONFIG" ]; then
        config_file="$SYSTEM_CONFIG"
    else
        echo "[ FAIL ] No configuration file found."
        echo "         Please create ~/.config/server/services.conf"
        exit 1
    fi
    
    echo "[ INFO ] Loading configuration from: $config_file"
    # Source the configuration file
    . "$config_file"
    
    # Check if services are defined
    if [ ${#SERVICE_ORDER[@]} -eq 0 ]; then
        echo "[ FAIL ] No services defined in SERVICE_ORDER array."
        exit 1
    fi
}

# ============================================================================
# SERVICE MANAGEMENT FUNCTIONS
# ============================================================================

# Function to start a service
start_service() {
    local service="$1"
    local process_name="${CONF_PROCESS[$service]}"
    local start_cmd="${CONF_START[$service]}"
    local check_cmd="${CONF_CHECK[$service]}"
    
    # Use custom check command if provided, otherwise use pgrep
    if [ -n "$check_cmd" ]; then
        if eval "$check_cmd" >/dev/null 2>&1; then
            echo "[  OK  ] $service is already running."
            return 1
        fi
    elif [ -n "$process_name" ]; then
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
    elif [ -n "$process_name" ]; then
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
    local process_name="${CONF_PROCESS[$service]}"
    local stop_cmd="${CONF_STOP[$service]}"
    local check_cmd="${CONF_CHECK[$service]}"
    local skip_on_normal="${CONF_SKIP_STOP[$service]}"
    
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
    elif [ -n "$process_name" ]; then
        if ! pgrep -x "$process_name" >/dev/null && ! pgrep -f "$process_name" >/dev/null; then
            echo "[ SKIP ] $service is not running."
            return
        fi
    fi
    
    echo "[  ..  ] Stopping: $service"
    
    # Use custom stop command if provided
    if [ -n "$stop_cmd" ]; then
        eval "$stop_cmd" >/dev/null 2>&1
    elif [ -n "$process_name" ]; then
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
        elif [ -n "$process_name" ]; then
            if ! pgrep -x "$process_name" >/dev/null && ! pgrep -f "$process_name" >/dev/null; then
                echo "[  OK  ] $service stopped successfully."
                return
            fi
        fi
    done
    
    # Force kill if requested
    if [ "$FORCE_MODE" = true ] && [ -n "$process_name" ]; then
        echo "[ WARN ] $service did not terminate, forcing kill."
        pkill -9 -x "$process_name" || pkill -9 -f "$process_name"
    else
        echo "[ FAIL ] $service did not terminate. Use '--force' to kill it."
    fi
}

# Function to check service status
status_service() {
    local service="$1"
    local process_name="${CONF_PROCESS[$service]}"
    local check_cmd="${CONF_CHECK[$service]}"
    
    if [ -n "$check_cmd" ]; then
        if eval "$check_cmd" >/dev/null 2>&1; then
            echo "[  OK  ] $service is running."
        else
            echo "[ STOP ] $service is not running."
        fi
    elif [ -n "$process_name" ]; then
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
    for service in "${SERVICE_ORDER[@]}"; do
        local required="${CONF_REQUIRED[$service]}"
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
        
        for service in "${SERVICE_ORDER[@]}"; do
            start_service "$service"
        done
        
        echo "[ DONE ] Server started."
        ;;
        
    stop)
        echo "[ INIT ] Stopping server processes..."
        
        for service in "${SERVICE_ORDER[@]}"; do
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
        for service in "${SERVICE_ORDER[@]}"; do
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
