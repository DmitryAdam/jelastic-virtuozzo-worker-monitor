#!/bin/bash

# Smart Laravel Queue Worker Manager
# Supports: check-only mode, force restart, and intelligent detection

# Get script directory (Laravel root)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Parse command line arguments
FORCE_RESTART=false
CHECK_ONLY=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --force)
            FORCE_RESTART=true
            shift
            ;;
        --check-only)
            CHECK_ONLY=true
            shift
            ;;
        --help)
            echo "Usage: $0 [--force] [--check-only]"
            echo "  --force      Force restart even if worker is running"
            echo "  --check-only Check status only, don't start worker"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Read APP_NAME from .env and transform to clean worker name
if [ -f "$SCRIPT_DIR/.env" ]; then
    APP_NAME=$(grep "^APP_NAME=" "$SCRIPT_DIR/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    if [ -n "$APP_NAME" ]; then
        WORKER_NAME=$(echo "$APP_NAME" | tr '[:upper:]' '[:lower:]' | tr -d ' ' | sed 's/[^a-z0-9-]//g' | sed 's/--*/-/g' | sed 's/^-\|-$//g')
    else
        WORKER_NAME="laravel-worker"
    fi
else
    echo "Warning: .env file not found, using default worker name"
    WORKER_NAME="laravel-worker"
fi

echo "Using worker name: $WORKER_NAME"

# Set log file path
LOG_FILE="$HOME/${WORKER_NAME}-worker.log"

# Function to check if worker is already running
check_worker_status() {
    local queue_processes screen_sessions worker_healthy
    
    # Check for queue:work processes
    queue_processes=$(ps aux | grep "artisan.*queue:work" | grep -v grep | wc -l)
    
    # Check for screen sessions
    screen_sessions=0
    if command -v screen >/dev/null 2>&1; then
        screen_sessions=$(screen -ls 2>/dev/null | grep -c "$WORKER_NAME" || echo "0")
    fi
    
    # Check if PID file exists and process is running
    pid_file="/tmp/${WORKER_NAME}-worker.pid"
    pid_running=false
    if [ -f "$pid_file" ]; then
        local pid=$(cat "$pid_file")
        if kill -0 "$pid" 2>/dev/null; then
            pid_running=true
        else
            rm -f "$pid_file" # Clean up stale PID file
        fi
    fi
    
    # Worker is healthy if we have processes AND (screen sessions OR valid PID)
    if [ "$queue_processes" -gt 0 ] && ([ "$screen_sessions" -gt 0 ] || [ "$pid_running" = true ]); then
        worker_healthy=true
    else
        worker_healthy=false
    fi
    
    echo "Worker Status Check:"
    echo "  Queue processes: $queue_processes"
    echo "  Screen sessions: $screen_sessions"
    echo "  PID tracking:    $pid_running"
    echo "  Overall status:  $([ "$worker_healthy" = true ] && echo "HEALTHY ✅" || echo "UNHEALTHY ❌")"
    
    return $([ "$worker_healthy" = true ] && echo 0 || echo 1)
}

# Function to cleanup existing processes
cleanup_processes() {
    echo "Cleaning up existing processes..."
    
    # Log cleanup attempt
    if [ -f "$LOG_FILE" ]; then
        echo "" >> "$LOG_FILE"
        echo "===== CLEANUP PROCESS =====" >> "$LOG_FILE"
        echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG_FILE"
    fi
    
    # Kill existing screen session
    if screen -S "$WORKER_NAME" -X quit 2>/dev/null; then
        echo "Stopped existing screen session: $WORKER_NAME"
        [ -f "$LOG_FILE" ] && echo "Stopped screen session: $WORKER_NAME" >> "$LOG_FILE"
    fi
    
    # Kill any existing queue workers
    KILLED_PROCESSES=$(pkill -f "queue:work" 2>/dev/null && echo "yes" || echo "no")
    if [ "$KILLED_PROCESSES" = "yes" ]; then
        echo "Killed existing queue:work processes"
        [ -f "$LOG_FILE" ] && echo "Killed existing queue:work processes" >> "$LOG_FILE"
    fi
    
    pkill -f "artisan queue" 2>/dev/null
    
    # Clean up PID file
    if [ -f "/tmp/${WORKER_NAME}-worker.pid" ]; then
        rm -f "/tmp/${WORKER_NAME}-worker.pid"
        echo "Cleaned up PID file"
        [ -f "$LOG_FILE" ] && echo "Cleaned up PID file" >> "$LOG_FILE"
    fi
    
    # Wait for cleanup
    sleep 3
    
    echo "Cleanup completed"
    [ -f "$LOG_FILE" ] && echo "Cleanup completed" >> "$LOG_FILE"
}

# Function to start worker
start_worker() {
    echo "Starting worker in directory: $SCRIPT_DIR"
    cd "$SCRIPT_DIR"
    
    # Clear old log
    > "$LOG_FILE"
    
    # Add initial log entry with proper timestamp
    echo "===== WORKER STARTUP =====" >> "$LOG_FILE"
    echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG_FILE"
    echo "Worker name: $WORKER_NAME" >> "$LOG_FILE"
    echo "Working directory: $SCRIPT_DIR" >> "$LOG_FILE"
    echo "============================" >> "$LOG_FILE"
    echo "" >> "$LOG_FILE"
    
    # Create the worker command
    QUEUE_CMD="php artisan queue:work --queue=default --verbose --timeout=600 --tries=3 --delay=30"
    
    # Method 1: Try with screen (recommended)
    if command -v screen >/dev/null 2>&1; then
        echo "Starting with screen session..."
        
        # Fix: Use current timestamp and proper variable expansion
        START_TIME=$(date '+%Y-%m-%d %H:%M:%S')
        
        screen -dmS "$WORKER_NAME" bash -c "
            cd '$SCRIPT_DIR'
            echo 'Worker started at $START_TIME' >> '$LOG_FILE'
            echo '===================================================' >> '$LOG_FILE'
            $QUEUE_CMD 2>&1 | tee -a '$LOG_FILE'
        "
        
        # Wait and check if screen session was created
        sleep 3
        if screen -list 2>/dev/null | grep -q "$WORKER_NAME"; then
            echo "Worker '$WORKER_NAME' started successfully with screen"
            echo "Start time logged: $START_TIME"
            return 0
        else
            echo "Screen session failed, trying background method..."
        fi
    fi
    
    # Method 2: Fallback to background process
    echo "Starting as background process..."
    
    # Fix: Use current timestamp
    START_TIME=$(date '+%Y-%m-%d %H:%M:%S')
    
    nohup bash -c "
        cd '$SCRIPT_DIR'
        echo 'Worker started at $START_TIME' >> '$LOG_FILE'
        echo '===================================================' >> '$LOG_FILE'
        $QUEUE_CMD 2>&1 | tee -a '$LOG_FILE'
    " > /dev/null 2>&1 &
    
    # Get the background process PID
    WORKER_PID=$!
    
    # Wait a moment and check if process is still running
    sleep 3
    if kill -0 $WORKER_PID 2>/dev/null; then
        echo "Worker started as background process (PID: $WORKER_PID)"
        echo $WORKER_PID > "/tmp/${WORKER_NAME}-worker.pid"
        return 0
    else
        echo "Failed to start worker as background process"
        return 1
    fi
}

# Main execution
echo "=== Laravel Queue Worker Manager ==="
echo "Timestamp: $(date)"

# Check current worker status
check_worker_status
worker_is_healthy=$?

# Handle check-only mode
if [ "$CHECK_ONLY" = true ]; then
    echo ""
    if [ $worker_is_healthy -eq 0 ]; then
        echo "✅ Worker is healthy - no action needed"
        exit 0
    else
        echo "❌ Worker is unhealthy - would restart if not in check-only mode"
        exit 1
    fi
fi

# Handle normal execution
if [ $worker_is_healthy -eq 0 ] && [ "$FORCE_RESTART" = false ]; then
    echo ""
    echo "✅ Worker is already healthy and running"
    echo "📄 Log file: $LOG_FILE"
    echo "💡 Use --force to restart anyway, or --check-only to just check status"
    echo ""
    exit 0
fi

# Worker is unhealthy or force restart requested
if [ "$FORCE_RESTART" = true ]; then
    echo ""
    echo "🔄 Force restart requested"
else
    echo ""
    echo "🚨 Worker is unhealthy - starting restart process"
fi

cleanup_processes
start_worker

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Worker started successfully!"
    echo "📁 Working directory: $SCRIPT_DIR"
    echo "📄 Log file: $LOG_FILE"
    echo ""
    echo "Management commands:"
    echo "  Check status:  $0 --check-only"
    echo "  Force restart: $0 --force"
    echo "  View log:      tail -f '$LOG_FILE'"
    echo "  Stop worker:   screen -S '$WORKER_NAME' -X quit"
    echo ""
    
    # Final verification
    sleep 2
    echo "" >> "$LOG_FILE"
    echo "===== FINAL VERIFICATION =====" >> "$LOG_FILE"
    echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG_FILE"
    
    check_worker_status
    final_status=$?
    
    if [ $final_status -eq 0 ]; then
        echo "🎉 Final verification: Worker is healthy!"
        echo "Final verification: Worker is healthy!" >> "$LOG_FILE"
        echo "Worker successfully started and verified" >> "$LOG_FILE"
        exit 0
    else
        echo "⚠️  Final verification: Worker may need attention"
        echo "Final verification: Worker may need attention" >> "$LOG_FILE"
        echo "Please check worker status manually" >> "$LOG_FILE"
        exit 2
    fi
else
    echo ""
    echo "❌ Failed to start worker!"
    echo "💡 Try manual start: cd '$SCRIPT_DIR' && php artisan queue:work --queue=default --verbose"
    echo ""
    
    # Log failure
    echo "" >> "$LOG_FILE"
    echo "===== STARTUP FAILED =====" >> "$LOG_FILE"
    echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG_FILE"
    echo "Failed to start worker with all methods" >> "$LOG_FILE"
    echo "Manual intervention required" >> "$LOG_FILE"
    echo "Try: cd '$SCRIPT_DIR' && php artisan queue:work --queue=default --verbose" >> "$LOG_FILE"
    
    exit 1
fi