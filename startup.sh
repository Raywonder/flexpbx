#!/bin/bash
# FlexPBX Service Startup Script
# This script is triggered when a remote desktop client successfully connects

echo "🚀 Starting FlexPBX services..."

# Set working directory
cd "$(dirname "$0")/.."

# Start background services
if [ -f "config/config.php" ]; then
    echo "✅ Configuration found - starting services"

    # Start PHP built-in cron system
    if [ ! -f "temp/cron.pid" ]; then
        nohup php cron/runner.php > logs/cron.log 2>&1 &
        echo $! > temp/cron.pid
        echo "⏰ Cron system started (PID: $(cat temp/cron.pid))"
    fi

    # Start main server process
    if [ ! -f "temp/server.pid" ]; then
        nohup php -S 0.0.0.0:8080 -t . > logs/server.log 2>&1 &
        echo $! > temp/server.pid
        echo "🌐 Server started (PID: $(cat temp/server.pid))"
    fi

    echo "🎉 All services started successfully"
else
    echo "❌ Configuration not found - please run installer first"
    exit 1
fi
