#!/bin/bash
# FlexPBX Module Manager
# Handles individual module reloading

handle_module_reload() {
    local module=$1

    case $module in
        "asterisk")
            systemctl reload asterisk
            ;;
        "nginx")
            nginx -t && systemctl reload nginx
            ;;
        "api")
            systemctl restart flexpbx-api
            ;;
        "all")
            systemctl reload asterisk
            nginx -t && systemctl reload nginx
            systemctl restart flexpbx-api
            ;;
        *)
            echo "Unknown module: $module"
            exit 1
            ;;
    esac
}

# Listen for module reload requests
while true; do
    if [[ -f /tmp/flexpbx-reload-request ]]; then
        module=$(cat /tmp/flexpbx-reload-request)
        rm -f /tmp/flexpbx-reload-request

        echo "$(date): Reloading module: $module"
        handle_module_reload "$module"
        echo "$(date): Module $module reloaded successfully"
    fi
    sleep 1
done
