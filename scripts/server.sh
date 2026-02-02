#!/data/data/com.termux/files/usr/bin/bash
#
# Modular Server Management Script
#
# This script manages server services in a modular way using a configuration
# file. Configuration can be loaded from:
#   1. ~/.config/server/services.conf (user config)
#   2. /data/data/com.termux/files/usr/etc/server/services.conf (system config)
#
# NOTE: Requires Bash 4.0+ for associative arrays.

set -euo pipefail

readonly SCRIPT_NAME="$(basename "${BASH_SOURCE[0]}")"
readonly USER_CONFIG="${HOME}/.config/server/services.conf"
readonly SYSTEM_CONFIG="${PREFIX}/etc/server/services.conf"

# Declare associative arrays for configuration
declare -A CONF_PROCESS
declare -A CONF_PIDFILE
declare -A CONF_START
declare -A CONF_STOP
declare -A CONF_CHECK
declare -A CONF_REQUIRED
declare -A CONF_SKIP_STOP

# Order of services execution
declare -a SERVICE_ORDER

FORCE_MODE=false

#######################################
# Load configuration file from user or system location.
# Globals:
#   USER_CONFIG
#   SYSTEM_CONFIG
#   SERVICE_ORDER
# Arguments:
#   None
# Outputs:
#   Writes status messages to stdout
# Returns:
#   0 on success, exits with 1 if no config found
#######################################
load_config() {
  local config_file=""
  
  if [[ -f "${USER_CONFIG}" ]]; then
    config_file="${USER_CONFIG}"
  elif [[ -f "${SYSTEM_CONFIG}" ]]; then
    config_file="${SYSTEM_CONFIG}"
  else
    echo "[ FAIL ] No configuration file found." >&2
    echo "         Please create ~/.config/server/services.conf" >&2
    exit 1
  fi
  
  echo "[ INFO ] Loading configuration from: ${config_file}"
  
  # Source the configuration file
  # shellcheck source=/dev/null
  source "${config_file}"
  
  # Check if services are defined
  if [[ ${#SERVICE_ORDER[@]} -eq 0 ]]; then
    echo "[ FAIL ] No services defined in SERVICE_ORDER array." >&2
    exit 1
  fi
}

#######################################
# Start a service.
# Globals:
#   CONF_PROCESS
#   CONF_START
#   CONF_CHECK
# Arguments:
#   Service name
# Outputs:
#   Writes status messages to stdout
# Returns:
#   1 if already running, 0 otherwise
#######################################
start_service() {
  local service="${1}"
  local process_name="${CONF_PROCESS[${service}]:-}"
  local start_cmd="${CONF_START[${service}]:-}"
  local check_cmd="${CONF_CHECK[${service}]:-}"
  
  # Use custom check command if provided, otherwise use pgrep
  if [[ -n "${check_cmd}" ]]; then
    if eval "${check_cmd}" >/dev/null 2>&1; then
      echo "[  OK  ] ${service} is already running."
      return 1
    fi
  elif [[ -n "${process_name}" ]]; then
    if pgrep -x "${process_name}" >/dev/null || \
       pgrep -f "${process_name}" >/dev/null; then
      echo "[  OK  ] ${service} is already running."
      return 1
    fi
  fi
  
  echo "[  ..  ] Starting: ${service}"
  eval "${start_cmd}" >/dev/null 2>&1 &
  sleep 1
  
  # Verify service started
  if [[ -n "${check_cmd}" ]]; then
    if eval "${check_cmd}" >/dev/null 2>&1; then
      echo "[  OK  ] ${service} started successfully."
    else
      echo "[ FAIL ] Failed to start ${service}." >&2
    fi
  elif [[ -n "${process_name}" ]]; then
    if pgrep -x "${process_name}" >/dev/null || \
       pgrep -f "${process_name}" >/dev/null; then
      echo "[  OK  ] ${service} started successfully."
    else
      echo "[ FAIL ] Failed to start ${service}." >&2
    fi
  fi
}

#######################################
# Stop a service.
# Globals:
#   CONF_STOP
#   CONF_PROCESS
#   CONF_PIDFILE
#   CONF_SKIP_STOP
#   FORCE_MODE
# Arguments:
#   Service name
# Outputs:
#   Writes status messages to stdout
# Returns:
#   0 on success, 1 on failure
#######################################
stop_service() {
  local service="${1}"
  local stop_cmd="${CONF_STOP[${service}]:-}"
  local process_name="${CONF_PROCESS[${service}]:-}"
  local pidfile="${CONF_PIDFILE[${service}]:-}"
  local skip_on_normal="${CONF_SKIP_STOP[${service}]:-}"

  if [[ "${skip_on_normal}" == "true" && "${FORCE_MODE}" != true ]]; then
    echo "[ INFO ] ${service} skipped (requires --force)"
    return 0
  fi

  echo "[  ..  ] Stopping: ${service}"

  # Try custom stop command first
  if [[ -n "${stop_cmd}" ]]; then
    if eval "${stop_cmd}" >/dev/null 2>&1; then
      echo "[  OK  ] ${service} stopped."
      return 0
    fi
  fi

  # Try pidfile
  if [[ -n "${pidfile}" && -r "${pidfile}" ]]; then
    local pid
    pid="$(cat "${pidfile}")"

    if kill -TERM "${pid}" 2>/dev/null; then
      echo "[  OK  ] ${service} SIGTERM sent (PID ${pid})."
      return 0
    fi
  fi

  # Try pkill by process name
  if [[ -n "${process_name}" ]]; then
    if pkill -TERM --exact "${process_name}"; then
      echo "[  OK  ] ${service} stopped."
      return 0
    fi
  fi

  # Force mode handling
  if [[ "${FORCE_MODE}" == true ]]; then
    echo "[ WARN ] Forcing termination of ${service}."

    if [[ -n "${pidfile}" && -r "${pidfile}" ]]; then
      if kill -KILL "$(cat "${pidfile}")" 2>/dev/null; then
        return 0
      fi
    fi

    if [[ -n "${process_name}" ]]; then
      if pkill -KILL --exact "${process_name}"; then
        return 0
      fi
    fi

    echo "[ FAIL ] Force kill failed for ${service}." >&2
    return 1
  fi

  echo "[ FAIL ] ${service} did not stop (use --force to override)." >&2
  return 1
}

#######################################
# Check service status.
# Globals:
#   CONF_PROCESS
#   CONF_CHECK
# Arguments:
#   Service name
# Outputs:
#   Writes status messages to stdout
#######################################
status_service() {
  local service="${1}"
  local process_name="${CONF_PROCESS[${service}]:-}"
  local check_cmd="${CONF_CHECK[${service}]:-}"
  
  if [[ -n "${check_cmd}" ]]; then
    if eval "${check_cmd}" >/dev/null 2>&1; then
      echo "[  OK  ] ${service} is running."
    else
      echo "[ STOP ] ${service} is not running."
    fi
  elif [[ -n "${process_name}" ]]; then
    if pgrep -x "${process_name}" >/dev/null || \
       pgrep -f "${process_name}" >/dev/null; then
      echo "[  OK  ] ${service} is running."
    else
      echo "[ STOP ] ${service} is not running."
    fi
  fi
}

#######################################
# Check that required commands exist.
# Globals:
#   SERVICE_ORDER
#   CONF_REQUIRED
# Outputs:
#   Writes error messages to stderr
# Returns:
#   0 if all dependencies exist, exits with 1 otherwise
#######################################
check_dependencies() {
  local missing=""
  
  for service in "${SERVICE_ORDER[@]}"; do
    local required="${CONF_REQUIRED[${service}]:-}"
    if [[ -n "${required}" ]] && \
       ! command -v "${required}" >/dev/null 2>&1; then
      missing="${missing} ${required}"
    fi
  done
  
  if [[ -n "${missing}" ]]; then
    echo "[ FAIL ] Missing required commands:${missing}" >&2
    exit 1
  fi
}

#######################################
# Display usage information.
# Globals:
#   SCRIPT_NAME
# Outputs:
#   Writes usage information to stdout
#######################################
usage() {
  cat << EOF
Usage: ${SCRIPT_NAME} {start|stop|restart|status} [--force]

Commands:
  start    - Start all configured services
  stop     - Stop all configured services
  restart  - Restart all configured services
  status   - Show status of all services

Options:
  --force  - Force stop services that don't terminate gracefully
EOF
}

#######################################
# Main function.
# Globals:
#   SERVICE_ORDER
#   FORCE_MODE
# Arguments:
#   Command line arguments
# Outputs:
#   Writes status messages to stdout
# Returns:
#   0 on success, 1 on invalid usage
#######################################
main() {
  local action="${1:-}"
  
  if [[ "${2:-}" == "--force" ]]; then
    FORCE_MODE=true
  fi
  
  # Load configuration
  load_config
  
  case "${action}" in
    start)
      echo "[ INIT ] Starting server processes..."
      check_dependencies
      
      for service in "${SERVICE_ORDER[@]}"; do
        start_service "${service}"
      done
      
      echo "[ DONE ] Server started."
      ;;
      
    stop)
      echo "[ INIT ] Stopping server processes..."
      
      for service in "${SERVICE_ORDER[@]}"; do
        stop_service "${service}"
      done
      
      echo "[ DONE ] Server stopped."
      ;;
      
    restart)
      echo "[ INIT ] Restarting server processes..."
      "${0}" stop "${2:-}"
      sleep 2
      "${0}" start "${2:-}"
      ;;
      
    status)
      echo "[ INFO ] Service status:"
      for service in "${SERVICE_ORDER[@]}"; do
        status_service "${service}"
      done
      ;;
      
    *)
      usage
      exit 1
      ;;
  esac
}

main "$@"
