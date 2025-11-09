#!/bin/bash

# FlexPBX Authentication Configuration Script
# Configures multiple authentication methods with desktop client compatibility
# Version: 1.0.0

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
FLEXPBX_VERSION="1.0.0"
CONFIG_DIR="/home/flexpbxuser/apps/flexpbx/config"
AUTH_CONFIG_FILE="$CONFIG_DIR/auth-config.json"
WEB_CONFIG_FILE="/home/flexpbxuser/public_html/config/auth.php"

# Authentication methods
PINCODE_AUTH=true          # Default - Compatible with desktop clients
WHM_AUTH=false
CPANEL_AUTH=false
LDAP_AUTH=false
OAUTH_AUTH=false
JWT_AUTH=true              # Default - For API access
APIKEY_AUTH=true           # Default - For service access
RADIUS_AUTH=false
DATABASE_AUTH=true         # Default - Local user database
EXTERNAL_API_AUTH=false

# Authentication settings
PINCODE_LENGTH=6
PINCODE_EXPIRY=3600        # 1 hour
SESSION_TIMEOUT=86400      # 24 hours
MAX_LOGIN_ATTEMPTS=3
LOCKOUT_DURATION=1800      # 30 minutes

# Integration settings
WHM_DETECTED=false
CPANEL_DETECTED=false
LDAP_SERVER=""
OAUTH_PROVIDER=""

# Multi-path detection arrays
WHMCS_PATHS=()
WORDPRESS_PATHS=()
MAGENTO_PATHS=()
DRUPAL_PATHS=()
JOOMLA_PATHS=()
CUSTOM_APP_PATHS=()
DETECTED_DATABASES=()

# Function to print colored output
print_status() {
    echo -e "${BLUE}ℹ️  [INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}✅ [SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠️  [WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}❌ [ERROR]${NC} $1"
}

print_config() {
    echo -e "${PURPLE}🔧 [CONFIG]${NC} $1"
}

print_auth() {
    echo -e "${CYAN}🔐 [AUTH]${NC} $1"
}

# Function to detect existing authentication systems
detect_authentication_systems() {
    print_status "Detecting existing authentication systems..."

    # Detect cPanel/WHM
    if [ -f "/usr/local/cpanel/cpanel" ] || [ -d "/usr/local/cpanel" ]; then
        CPANEL_DETECTED=true
        print_success "cPanel detected"

        if [ -f "/usr/local/cpanel/whm" ] || [ -d "/usr/local/cpanel/whm" ]; then
            WHM_DETECTED=true
            print_success "WHM detected"
        fi
    fi

    # Multi-path WHMCS detection
    print_status "Scanning for WHMCS installations across all user accounts..."
    WHMCS_PATHS=()

    # Common WHMCS locations
    local whmcs_search_paths=(
        "/var/www/html/whmcs"
        "/usr/local/apache/htdocs/whmcs"
        "/var/www/whmcs"
        "/home/*/public_html/whmcs"
        "/home/*/www/whmcs"
        "/home/*/domains/*/public_html/whmcs"
        "/opt/whmcs"
    )

    for search_path in "${whmcs_search_paths[@]}"; do
        # Use find with proper permissions handling
        while IFS= read -r -d '' whmcs_dir; do
            if [ -f "$whmcs_dir/init.php" ] && [ -f "$whmcs_dir/configuration.php" ]; then
                WHMCS_PATHS+=("$whmcs_dir")
                whmcs_owner=$(stat -c %U "$whmcs_dir" 2>/dev/null || stat -f %Su "$whmcs_dir" 2>/dev/null)
                print_success "WHMCS found: $whmcs_dir (owner: $whmcs_owner)"
            fi
        done < <(find $(dirname "$search_path") -maxdepth 3 -name $(basename "$search_path") -type d -print0 2>/dev/null)
    done

    # Detect WordPress installations (for SSO integration)
    print_status "Scanning for WordPress installations..."
    WORDPRESS_PATHS=()

    local wp_search_paths=(
        "/var/www/html"
        "/home/*/public_html"
        "/home/*/www"
        "/home/*/domains/*/public_html"
    )

    for search_path in "${wp_search_paths[@]}"; do
        while IFS= read -r -d '' wp_dir; do
            if [ -f "$wp_dir/wp-config.php" ] && [ -f "$wp_dir/wp-load.php" ]; then
                WORDPRESS_PATHS+=("$wp_dir")
                wp_owner=$(stat -c %U "$wp_dir" 2>/dev/null || stat -f %Su "$wp_dir" 2>/dev/null)
                print_success "WordPress found: $wp_dir (owner: $wp_owner)"
            fi
        done < <(find $(dirname "$search_path") -maxdepth 3 -name "wp-config.php" -exec dirname {} \; -print0 2>/dev/null)
    done

    # Detect other hosting applications
    detect_hosting_applications

    # Detect LDAP
    if command -v ldapsearch >/dev/null 2>&1; then
        print_success "LDAP tools detected"
    fi

    # Detect existing authentication databases
    if command -v mysql >/dev/null 2>&1; then
        print_success "MySQL detected - can use for user authentication"
        detect_mysql_databases
    fi

    echo
    print_status "Detection Summary:"
    echo "  WHMCS installations: ${#WHMCS_PATHS[@]}"
    echo "  WordPress installations: ${#WORDPRESS_PATHS[@]}"
    if [ ${#WHMCS_PATHS[@]} -gt 0 ]; then
        echo "  WHMCS paths found:"
        for whmcs_path in "${WHMCS_PATHS[@]}"; do
            echo "    - $whmcs_path"
        done
    fi
    echo
}

# Function to detect hosting applications under different accounts
detect_hosting_applications() {
    print_status "Detecting hosting applications under different user accounts..."

    # Detect Magento installations
    MAGENTO_PATHS=()
    while IFS= read -r -d '' magento_dir; do
        if [ -f "$magento_dir/app/etc/env.php" ] || [ -f "$magento_dir/app/Mage.php" ]; then
            MAGENTO_PATHS+=("$magento_dir")
            magento_owner=$(stat -c %U "$magento_dir" 2>/dev/null || stat -f %Su "$magento_dir" 2>/dev/null)
            print_success "Magento found: $magento_dir (owner: $magento_owner)"
        fi
    done < <(find /home/*/public_html /home/*/www /var/www -maxdepth 2 -name "app" -type d -print0 2>/dev/null)

    # Detect Drupal installations
    DRUPAL_PATHS=()
    while IFS= read -r -d '' drupal_dir; do
        if [ -f "$drupal_dir/sites/default/settings.php" ] && [ -f "$drupal_dir/index.php" ]; then
            DRUPAL_PATHS+=("$drupal_dir")
            drupal_owner=$(stat -c %U "$drupal_dir" 2>/dev/null || stat -f %Su "$drupal_dir" 2>/dev/null)
            print_success "Drupal found: $drupal_dir (owner: $drupal_owner)"
        fi
    done < <(find /home/*/public_html /home/*/www /var/www -maxdepth 2 -name "sites" -type d -exec dirname {} \; -print0 2>/dev/null)

    # Detect Joomla installations
    JOOMLA_PATHS=()
    while IFS= read -r -d '' joomla_dir; do
        if [ -f "$joomla_dir/configuration.php" ] && [ -f "$joomla_dir/libraries/joomla/factory.php" ]; then
            JOOMLA_PATHS+=("$joomla_dir")
            joomla_owner=$(stat -c %U "$joomla_dir" 2>/dev/null || stat -f %Su "$joomla_dir" 2>/dev/null)
            print_success "Joomla found: $joomla_dir (owner: $joomla_owner)"
        fi
    done < <(find /home/*/public_html /home/*/www /var/www -maxdepth 2 -name "configuration.php" -exec dirname {} \; -print0 2>/dev/null)

    # Detect custom applications with databases
    detect_custom_applications
}

# Function to detect custom applications and their databases
detect_custom_applications() {
    print_status "Scanning for custom applications with authentication systems..."

    # Look for common authentication config files
    while IFS= read -r -d '' config_file; do
        config_dir=$(dirname "$config_file")
        config_owner=$(stat -c %U "$config_file" 2>/dev/null || stat -f %Su "$config_file" 2>/dev/null)

        # Check if it's a substantial application (not just a config file)
        if [ $(find "$config_dir" -name "*.php" | wc -l) -gt 5 ]; then
            print_success "Custom application found: $config_dir (owner: $config_owner)"
            CUSTOM_APP_PATHS+=("$config_dir")
        fi
    done < <(find /home/*/public_html /home/*/www -name "config.php" -o -name "settings.php" -o -name "database.php" -print0 2>/dev/null)
}

# Function to detect MySQL databases across different users
detect_mysql_databases() {
    print_status "Detecting MySQL databases for potential authentication integration..."

    if command -v mysql >/dev/null 2>&1; then
        # Try to list databases (requires appropriate MySQL permissions)
        mysql -e "SHOW DATABASES;" 2>/dev/null | grep -E "(whmcs|wordpress|wp_|magento|drupal|joomla)" | while read db_name; do
            print_success "Detected authentication database: $db_name"
            DETECTED_DATABASES+=("$db_name")
        done 2>/dev/null || true
    fi
}

# Function to show authentication menu
show_authentication_menu() {
    clear
    echo "========================================"
    echo "🔐 FlexPBX Authentication Configuration"
    echo "========================================"
    echo

    # Show detected applications
    if [ ${#WHMCS_PATHS[@]} -gt 0 ] || [ ${#WORDPRESS_PATHS[@]} -gt 0 ] || [ ${#MAGENTO_PATHS[@]} -gt 0 ]; then
        echo "🔍 Detected Applications:"
        if [ ${#WHMCS_PATHS[@]} -gt 0 ]; then
            echo "  📊 WHMCS (${#WHMCS_PATHS[@]} installations):"
            for whmcs_path in "${WHMCS_PATHS[@]}"; do
                whmcs_owner=$(stat -c %U "$whmcs_path" 2>/dev/null || stat -f %Su "$whmcs_path" 2>/dev/null)
                echo "     └─ $whmcs_path (user: $whmcs_owner)"
            done
        fi
        if [ ${#WORDPRESS_PATHS[@]} -gt 0 ]; then
            echo "  🌐 WordPress (${#WORDPRESS_PATHS[@]} installations):"
            for wp_path in "${WORDPRESS_PATHS[@]}"; do
                wp_owner=$(stat -c %U "$wp_path" 2>/dev/null || stat -f %Su "$wp_path" 2>/dev/null)
                echo "     └─ $wp_path (user: $wp_owner)"
            done
        fi
        if [ ${#MAGENTO_PATHS[@]} -gt 0 ]; then
            echo "  🛒 Magento (${#MAGENTO_PATHS[@]} installations):"
            for magento_path in "${MAGENTO_PATHS[@]}"; do
                magento_owner=$(stat -c %U "$magento_path" 2>/dev/null || stat -f %Su "$magento_path" 2>/dev/null)
                echo "     └─ $magento_path (user: $magento_owner)"
            done
        fi
        echo
    fi

    echo "Select authentication methods to enable:"
    echo
    echo "📱 Desktop Client Compatible (Recommended):"
    echo "  [$(if $PINCODE_AUTH; then echo "✅"; else echo "  "; fi)] 1. Pincode Authentication (Default)"
    echo "  [$(if $JWT_AUTH; then echo "✅"; else echo "  "; fi)] 2. JWT Token Authentication"
    echo "  [$(if $APIKEY_AUTH; then echo "✅"; else echo "  "; fi)] 3. API Key Authentication"
    echo
    echo "🌐 Web-Based Authentication:"
    echo "  [$(if $DATABASE_AUTH; then echo "✅"; else echo "  "; fi)] 4. Database Authentication (Local Users)"
    echo "  [$(if $WHM_AUTH; then echo "✅"; else echo "  "; fi)] 5. WHM Authentication $(if ! $WHM_DETECTED; then echo "(Not Available)"; fi)"
    echo "  [$(if $CPANEL_AUTH; then echo "✅"; else echo "  "; fi)] 6. cPanel Authentication $(if ! $CPANEL_DETECTED; then echo "(Not Available)"; fi)"
    echo
    echo "🏢 Enterprise Authentication:"
    echo "  [$(if $LDAP_AUTH; then echo "✅"; else echo "  "; fi)] 7. LDAP/Active Directory"
    echo "  [$(if $OAUTH_AUTH; then echo "✅"; else echo "  "; fi)] 8. OAuth/SSO (Google, Microsoft, etc.)"
    echo "  [$(if $RADIUS_AUTH; then echo "✅"; else echo "  "; fi)] 9. RADIUS Authentication"
    echo "  [$(if $EXTERNAL_API_AUTH; then echo "✅"; else echo "  "; fi)]10. External API Authentication"
    echo
    echo "🔗 Application Integration:"
    if [ ${#WHMCS_PATHS[@]} -gt 0 ]; then
        echo "  15. Configure WHMCS Integration (${#WHMCS_PATHS[@]} detected)"
    fi
    if [ ${#WORDPRESS_PATHS[@]} -gt 0 ]; then
        echo "  16. Configure WordPress SSO (${#WORDPRESS_PATHS[@]} detected)"
    fi
    echo
    echo "📋 Configuration Options:"
    echo "  11. Configure Authentication Settings"
    echo "  12. Test Authentication Methods"
    echo "  13. Save and Apply Configuration"
    echo "  14. Reset to Default Configuration"
    echo "   0. Exit"
    echo
}

# Function to toggle authentication method
toggle_auth_method() {
    case $1 in
        1)
            PINCODE_AUTH=$(! $PINCODE_AUTH && echo true || echo false)
            print_auth "Pincode Authentication: $(if $PINCODE_AUTH; then echo "ENABLED"; else echo "DISABLED"; fi)"
            ;;
        2)
            JWT_AUTH=$(! $JWT_AUTH && echo true || echo false)
            print_auth "JWT Authentication: $(if $JWT_AUTH; then echo "ENABLED"; else echo "DISABLED"; fi)"
            ;;
        3)
            APIKEY_AUTH=$(! $APIKEY_AUTH && echo true || echo false)
            print_auth "API Key Authentication: $(if $APIKEY_AUTH; then echo "ENABLED"; else echo "DISABLED"; fi)"
            ;;
        4)
            DATABASE_AUTH=$(! $DATABASE_AUTH && echo true || echo false)
            print_auth "Database Authentication: $(if $DATABASE_AUTH; then echo "ENABLED"; else echo "DISABLED"; fi)"
            ;;
        5)
            if $WHM_DETECTED; then
                WHM_AUTH=$(! $WHM_AUTH && echo true || echo false)
                print_auth "WHM Authentication: $(if $WHM_AUTH; then echo "ENABLED"; else echo "DISABLED"; fi)"
            else
                print_error "WHM not detected on this system"
            fi
            ;;
        6)
            if $CPANEL_DETECTED; then
                CPANEL_AUTH=$(! $CPANEL_AUTH && echo true || echo false)
                print_auth "cPanel Authentication: $(if $CPANEL_AUTH; then echo "ENABLED"; else echo "DISABLED"; fi)"
            else
                print_error "cPanel not detected on this system"
            fi
            ;;
        7)
            LDAP_AUTH=$(! $LDAP_AUTH && echo true || echo false)
            print_auth "LDAP Authentication: $(if $LDAP_AUTH; then echo "ENABLED"; else echo "DISABLED"; fi)"
            if $LDAP_AUTH && [ -z "$LDAP_SERVER" ]; then
                configure_ldap
            fi
            ;;
        8)
            OAUTH_AUTH=$(! $OAUTH_AUTH && echo true || echo false)
            print_auth "OAuth Authentication: $(if $OAUTH_AUTH; then echo "ENABLED"; else echo "DISABLED"; fi)"
            if $OAUTH_AUTH && [ -z "$OAUTH_PROVIDER" ]; then
                configure_oauth
            fi
            ;;
        9)
            RADIUS_AUTH=$(! $RADIUS_AUTH && echo true || echo false)
            print_auth "RADIUS Authentication: $(if $RADIUS_AUTH; then echo "ENABLED"; else echo "DISABLED"; fi)"
            ;;
        10)
            EXTERNAL_API_AUTH=$(! $EXTERNAL_API_AUTH && echo true || echo false)
            print_auth "External API Authentication: $(if $EXTERNAL_API_AUTH; then echo "ENABLED"; else echo "DISABLED"; fi)"
            ;;
    esac
}

# Function to configure authentication settings
configure_auth_settings() {
    clear
    echo "🔧 Authentication Settings Configuration"
    echo "========================================"
    echo

    echo "Current Settings:"
    echo "  Pincode Length: $PINCODE_LENGTH digits"
    echo "  Pincode Expiry: $PINCODE_EXPIRY seconds ($(($PINCODE_EXPIRY / 60)) minutes)"
    echo "  Session Timeout: $SESSION_TIMEOUT seconds ($(($SESSION_TIMEOUT / 3600)) hours)"
    echo "  Max Login Attempts: $MAX_LOGIN_ATTEMPTS"
    echo "  Lockout Duration: $LOCKOUT_DURATION seconds ($(($LOCKOUT_DURATION / 60)) minutes)"
    echo

    read -p "Enter new pincode length (4-8) [$PINCODE_LENGTH]: " new_pincode_length
    if [[ "$new_pincode_length" =~ ^[4-8]$ ]]; then
        PINCODE_LENGTH=$new_pincode_length
    fi

    read -p "Enter pincode expiry in minutes [$(($PINCODE_EXPIRY / 60))]: " new_expiry_minutes
    if [[ "$new_expiry_minutes" =~ ^[0-9]+$ ]] && [ "$new_expiry_minutes" -gt 0 ]; then
        PINCODE_EXPIRY=$(($new_expiry_minutes * 60))
    fi

    read -p "Enter session timeout in hours [$(($SESSION_TIMEOUT / 3600))]: " new_session_hours
    if [[ "$new_session_hours" =~ ^[0-9]+$ ]] && [ "$new_session_hours" -gt 0 ]; then
        SESSION_TIMEOUT=$(($new_session_hours * 3600))
    fi

    read -p "Enter max login attempts [$MAX_LOGIN_ATTEMPTS]: " new_max_attempts
    if [[ "$new_max_attempts" =~ ^[0-9]+$ ]] && [ "$new_max_attempts" -gt 0 ]; then
        MAX_LOGIN_ATTEMPTS=$new_max_attempts
    fi

    read -p "Enter lockout duration in minutes [$(($LOCKOUT_DURATION / 60))]: " new_lockout_minutes
    if [[ "$new_lockout_minutes" =~ ^[0-9]+$ ]] && [ "$new_lockout_minutes" -gt 0 ]; then
        LOCKOUT_DURATION=$(($new_lockout_minutes * 60))
    fi

    print_success "Authentication settings updated"
    read -p "Press Enter to continue..."
}

# Function to configure LDAP settings
configure_ldap() {
    echo
    print_config "Configuring LDAP Authentication"

    read -p "Enter LDAP server (e.g., ldap://your-server.com): " ldap_server
    read -p "Enter LDAP base DN (e.g., dc=company,dc=com): " ldap_base_dn
    read -p "Enter LDAP bind DN (e.g., cn=admin,dc=company,dc=com): " ldap_bind_dn
    read -s -p "Enter LDAP bind password: " ldap_bind_pass
    echo

    LDAP_SERVER="$ldap_server"
    LDAP_BASE_DN="$ldap_base_dn"
    LDAP_BIND_DN="$ldap_bind_dn"
    LDAP_BIND_PASS="$ldap_bind_pass"

    print_success "LDAP configuration saved"
}

# Function to configure OAuth settings
configure_oauth() {
    echo
    print_config "Configuring OAuth Authentication"

    echo "Select OAuth provider:"
    echo "1. Google"
    echo "2. Microsoft Azure AD"
    echo "3. GitHub"
    echo "4. Custom OAuth Provider"

    read -p "Enter choice [1-4]: " oauth_choice

    case $oauth_choice in
        1)
            OAUTH_PROVIDER="google"
            read -p "Enter Google Client ID: " oauth_client_id
            read -s -p "Enter Google Client Secret: " oauth_client_secret
            ;;
        2)
            OAUTH_PROVIDER="microsoft"
            read -p "Enter Azure Application ID: " oauth_client_id
            read -s -p "Enter Azure Client Secret: " oauth_client_secret
            ;;
        3)
            OAUTH_PROVIDER="github"
            read -p "Enter GitHub Client ID: " oauth_client_id
            read -s -p "Enter GitHub Client Secret: " oauth_client_secret
            ;;
        4)
            OAUTH_PROVIDER="custom"
            read -p "Enter OAuth Provider Name: " oauth_provider_name
            read -p "Enter OAuth Client ID: " oauth_client_id
            read -s -p "Enter OAuth Client Secret: " oauth_client_secret
            read -p "Enter OAuth Authorization URL: " oauth_auth_url
            read -p "Enter OAuth Token URL: " oauth_token_url
            ;;
    esac
    echo

    OAUTH_CLIENT_ID="$oauth_client_id"
    OAUTH_CLIENT_SECRET="$oauth_client_secret"

    print_success "OAuth configuration saved"
}

# Function to create authentication configuration
create_auth_config() {
    print_status "Creating authentication configuration..."

    mkdir -p "$CONFIG_DIR"
    mkdir -p "$(dirname "$WEB_CONFIG_FILE")"

    # Create JSON configuration for backend
    cat > "$AUTH_CONFIG_FILE" << EOF
{
  "version": "$FLEXPBX_VERSION",
  "timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "authentication": {
    "methods": {
      "pincode": {
        "enabled": $PINCODE_AUTH,
        "length": $PINCODE_LENGTH,
        "expiry_seconds": $PINCODE_EXPIRY,
        "algorithm": "numeric",
        "case_sensitive": false,
        "desktop_compatible": true
      },
      "jwt": {
        "enabled": $JWT_AUTH,
        "algorithm": "HS256",
        "expiry_seconds": $SESSION_TIMEOUT,
        "refresh_enabled": true,
        "desktop_compatible": true
      },
      "api_key": {
        "enabled": $APIKEY_AUTH,
        "length": 32,
        "prefix": "flx_",
        "desktop_compatible": true
      },
      "database": {
        "enabled": $DATABASE_AUTH,
        "table": "flexpbx_users",
        "password_hash": "bcrypt",
        "salt_rounds": 12
      },
      "whm": {
        "enabled": $WHM_AUTH,
        "api_url": "https://$(hostname):2087/json-api/",
        "verify_ssl": true
      },
      "cpanel": {
        "enabled": $CPANEL_AUTH,
        "api_url": "https://$(hostname):2083/execute/",
        "verify_ssl": true
      },
      "ldap": {
        "enabled": $LDAP_AUTH,
        "server": "${LDAP_SERVER:-}",
        "base_dn": "${LDAP_BASE_DN:-}",
        "bind_dn": "${LDAP_BIND_DN:-}",
        "user_filter": "(uid=%s)",
        "group_filter": "(cn=flexpbx_users)"
      },
      "oauth": {
        "enabled": $OAUTH_AUTH,
        "provider": "${OAUTH_PROVIDER:-}",
        "client_id": "${OAUTH_CLIENT_ID:-}",
        "redirect_uri": "https://$(hostname)/flexpbx/oauth/callback"
      },
      "radius": {
        "enabled": $RADIUS_AUTH,
        "server": "",
        "port": 1812,
        "secret": "",
        "timeout": 5
      },
      "external_api": {
        "enabled": $EXTERNAL_API_AUTH,
        "endpoint": "",
        "method": "POST",
        "timeout": 10
      }
    },
    "settings": {
      "max_login_attempts": $MAX_LOGIN_ATTEMPTS,
      "lockout_duration": $LOCKOUT_DURATION,
      "session_timeout": $SESSION_TIMEOUT,
      "require_2fa": false,
      "password_complexity": {
        "min_length": 8,
        "require_uppercase": true,
        "require_lowercase": true,
        "require_numbers": true,
        "require_symbols": false
      }
    },
    "desktop_client": {
      "primary_method": "pincode",
      "fallback_methods": ["jwt", "api_key"],
      "auto_discovery_auth": "pincode",
      "device_registration": true,
      "remember_device": true
    }
  }
}
EOF

    # Create PHP configuration for web interface
    cat > "$WEB_CONFIG_FILE" << 'EOF'
<?php
/**
 * FlexPBX Authentication Configuration
 * Generated by FlexPBX Authentication Configurator
 */

// Load authentication configuration
$auth_config_file = '/home/flexpbxuser/apps/flexpbx/config/auth-config.json';
$auth_config = json_decode(file_get_contents($auth_config_file), true);

// Authentication methods configuration
define('AUTH_PINCODE_ENABLED', $auth_config['authentication']['methods']['pincode']['enabled']);
define('AUTH_JWT_ENABLED', $auth_config['authentication']['methods']['jwt']['enabled']);
define('AUTH_APIKEY_ENABLED', $auth_config['authentication']['methods']['api_key']['enabled']);
define('AUTH_DATABASE_ENABLED', $auth_config['authentication']['methods']['database']['enabled']);
define('AUTH_WHM_ENABLED', $auth_config['authentication']['methods']['whm']['enabled']);
define('AUTH_CPANEL_ENABLED', $auth_config['authentication']['methods']['cpanel']['enabled']);
define('AUTH_LDAP_ENABLED', $auth_config['authentication']['methods']['ldap']['enabled']);
define('AUTH_OAUTH_ENABLED', $auth_config['authentication']['methods']['oauth']['enabled']);
define('AUTH_RADIUS_ENABLED', $auth_config['authentication']['methods']['radius']['enabled']);
define('AUTH_EXTERNAL_API_ENABLED', $auth_config['authentication']['methods']['external_api']['enabled']);

// Authentication settings
define('AUTH_PINCODE_LENGTH', $auth_config['authentication']['methods']['pincode']['length']);
define('AUTH_PINCODE_EXPIRY', $auth_config['authentication']['methods']['pincode']['expiry_seconds']);
define('AUTH_SESSION_TIMEOUT', $auth_config['authentication']['settings']['session_timeout']);
define('AUTH_MAX_ATTEMPTS', $auth_config['authentication']['settings']['max_login_attempts']);
define('AUTH_LOCKOUT_DURATION', $auth_config['authentication']['settings']['lockout_duration']);

// Desktop client compatibility
define('AUTH_DESKTOP_PRIMARY', $auth_config['authentication']['desktop_client']['primary_method']);
define('AUTH_DESKTOP_FALLBACK', implode(',', $auth_config['authentication']['desktop_client']['fallback_methods']));
define('AUTH_DEVICE_REGISTRATION', $auth_config['authentication']['desktop_client']['device_registration']);

/**
 * Authentication method helper functions
 */
class FlexPBXAuth {

    public static function getEnabledMethods() {
        $methods = [];

        if (AUTH_PINCODE_ENABLED) $methods[] = 'pincode';
        if (AUTH_JWT_ENABLED) $methods[] = 'jwt';
        if (AUTH_APIKEY_ENABLED) $methods[] = 'api_key';
        if (AUTH_DATABASE_ENABLED) $methods[] = 'database';
        if (AUTH_WHM_ENABLED) $methods[] = 'whm';
        if (AUTH_CPANEL_ENABLED) $methods[] = 'cpanel';
        if (AUTH_LDAP_ENABLED) $methods[] = 'ldap';
        if (AUTH_OAUTH_ENABLED) $methods[] = 'oauth';
        if (AUTH_RADIUS_ENABLED) $methods[] = 'radius';
        if (AUTH_EXTERNAL_API_ENABLED) $methods[] = 'external_api';

        return $methods;
    }

    public static function getDesktopCompatibleMethods() {
        $compatible = [];

        if (AUTH_PINCODE_ENABLED) $compatible[] = 'pincode';
        if (AUTH_JWT_ENABLED) $compatible[] = 'jwt';
        if (AUTH_APIKEY_ENABLED) $compatible[] = 'api_key';

        return $compatible;
    }

    public static function generatePincode() {
        $length = AUTH_PINCODE_LENGTH;
        $pincode = '';

        for ($i = 0; $i < $length; $i++) {
            $pincode .= random_int(0, 9);
        }

        return $pincode;
    }

    public static function validateAuthMethod($method) {
        return in_array($method, self::getEnabledMethods());
    }

    public static function isDesktopCompatible($method) {
        return in_array($method, self::getDesktopCompatibleMethods());
    }
}

// Configuration validation
if (!AUTH_PINCODE_ENABLED && !AUTH_JWT_ENABLED && !AUTH_APIKEY_ENABLED) {
    error_log('FlexPBX Warning: No desktop-compatible authentication methods enabled');
}

if (!AUTH_DATABASE_ENABLED && !AUTH_WHM_ENABLED && !AUTH_CPANEL_ENABLED && !AUTH_LDAP_ENABLED) {
    error_log('FlexPBX Warning: No user authentication backend enabled');
}
?>
EOF

    print_success "Authentication configuration created"
}

# Function to test authentication methods
test_authentication() {
    print_status "Testing authentication methods..."

    echo "Testing enabled authentication methods:"

    if $PINCODE_AUTH; then
        echo "✅ Pincode Authentication - Desktop Compatible"
        # Generate test pincode
        test_pincode=$(printf "%0${PINCODE_LENGTH}d" $((RANDOM % $((10**$PINCODE_LENGTH)))))
        echo "   Sample pincode: $test_pincode (expires in $(($PINCODE_EXPIRY / 60)) minutes)"
    fi

    if $JWT_AUTH; then
        echo "✅ JWT Authentication - Desktop Compatible"
        echo "   Token expiry: $(($SESSION_TIMEOUT / 3600)) hours"
    fi

    if $APIKEY_AUTH; then
        echo "✅ API Key Authentication - Desktop Compatible"
        test_api_key="flx_$(openssl rand -hex 16)"
        echo "   Sample API key: $test_api_key"
    fi

    if $DATABASE_AUTH; then
        echo "✅ Database Authentication - Web Interface"
        echo "   User table: flexpbx_users"
    fi

    if $WHM_AUTH; then
        echo "✅ WHM Authentication - Web Interface"
        echo "   WHM API: https://$(hostname):2087/json-api/"
    fi

    if $CPANEL_AUTH; then
        echo "✅ cPanel Authentication - Web Interface"
        echo "   cPanel API: https://$(hostname):2083/execute/"
    fi

    if $LDAP_AUTH; then
        echo "✅ LDAP Authentication - Enterprise"
        echo "   LDAP Server: $LDAP_SERVER"
    fi

    if $OAUTH_AUTH; then
        echo "✅ OAuth Authentication - SSO"
        echo "   Provider: $OAUTH_PROVIDER"
    fi

    echo
    echo "Desktop Client Configuration:"
    echo "  Primary Method: $(if $PINCODE_AUTH; then echo "pincode"; elif $JWT_AUTH; then echo "jwt"; else echo "api_key"; fi)"
    echo "  Auto-Discovery: pincode authentication"
    echo "  Device Registration: enabled"

    read -p "Press Enter to continue..."
}

# Function to reset to default configuration
reset_to_default() {
    print_warning "Resetting to default authentication configuration"

    PINCODE_AUTH=true
    WHM_AUTH=false
    CPANEL_AUTH=false
    LDAP_AUTH=false
    OAUTH_AUTH=false
    JWT_AUTH=true
    APIKEY_AUTH=true
    RADIUS_AUTH=false
    DATABASE_AUTH=true
    EXTERNAL_API_AUTH=false

    PINCODE_LENGTH=6
    PINCODE_EXPIRY=3600
    SESSION_TIMEOUT=86400
    MAX_LOGIN_ATTEMPTS=3
    LOCKOUT_DURATION=1800

    print_success "Configuration reset to defaults (desktop client compatible)"
}

# Function to apply configuration
apply_configuration() {
    print_status "Applying authentication configuration..."

    create_auth_config

    # Restart FlexPBX service if running
    if systemctl is-active flexpbx >/dev/null 2>&1; then
        print_status "Restarting FlexPBX service..."
        systemctl restart flexpbx
        print_success "FlexPBX service restarted"
    fi

    print_success "Authentication configuration applied successfully"
    echo
    echo "Configuration Summary:"
    echo "  Enabled Methods: $(if $PINCODE_AUTH; then echo -n "Pincode "; fi)$(if $JWT_AUTH; then echo -n "JWT "; fi)$(if $APIKEY_AUTH; then echo -n "API-Key "; fi)$(if $DATABASE_AUTH; then echo -n "Database "; fi)$(if $WHM_AUTH; then echo -n "WHM "; fi)$(if $CPANEL_AUTH; then echo -n "cPanel "; fi)$(if $LDAP_AUTH; then echo -n "LDAP "; fi)$(if $OAUTH_AUTH; then echo -n "OAuth "; fi)"
    echo "  Desktop Compatible: $(if $PINCODE_AUTH || $JWT_AUTH || $APIKEY_AUTH; then echo "Yes"; else echo "No"; fi)"
    echo "  Configuration File: $AUTH_CONFIG_FILE"
    echo "  Web Configuration: $WEB_CONFIG_FILE"
}

# Main function
main() {
    echo "========================================"
    echo "🔐 FlexPBX Authentication Configurator v$FLEXPBX_VERSION"
    echo "========================================"
    echo

    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root"
        exit 1
    fi

    # Detect existing systems
    detect_authentication_systems

    # Main menu loop
    while true; do
        show_authentication_menu

        read -p "Enter your choice [0-14]: " choice

        case $choice in
            1|2|3|4|5|6|7|8|9|10)
                toggle_auth_method $choice
                sleep 1
                ;;
            11)
                configure_auth_settings
                ;;
            12)
                test_authentication
                ;;
            13)
                apply_configuration
                echo
                read -p "Press Enter to continue or 0 to exit..."
                read continue_choice
                if [[ "$continue_choice" == "0" ]]; then
                    exit 0
                fi
                ;;
            14)
                reset_to_default
                sleep 1
                ;;
            15)
                if [ ${#WHMCS_PATHS[@]} -gt 0 ]; then
                    configure_whmcs_integration
                else
                    print_error "No WHMCS installations detected"
                    sleep 1
                fi
                ;;
            16)
                if [ ${#WORDPRESS_PATHS[@]} -gt 0 ]; then
                    configure_wordpress_sso
                else
                    print_error "No WordPress installations detected"
                    sleep 1
                fi
                ;;
            0)
                print_success "Authentication configuration completed"
                exit 0
                ;;
            *)
                print_error "Invalid choice. Please try again."
                sleep 1
                ;;
        esac
    done
}

# Function to configure WHMCS integration
configure_whmcs_integration() {
    clear
    echo "🔗 WHMCS Integration Configuration"
    echo "================================="
    echo

    if [ ${#WHMCS_PATHS[@]} -eq 0 ]; then
        print_error "No WHMCS installations detected"
        return
    fi

    echo "Detected WHMCS installations:"
    for i in "${!WHMCS_PATHS[@]}"; do
        whmcs_path="${WHMCS_PATHS[$i]}"
        whmcs_owner=$(stat -c %U "$whmcs_path" 2>/dev/null || stat -f %Su "$whmcs_path" 2>/dev/null)
        echo "  $((i+1)). $whmcs_path (user: $whmcs_owner)"
    done
    echo

    if [ ${#WHMCS_PATHS[@]} -eq 1 ]; then
        selected_whmcs="${WHMCS_PATHS[0]}"
        whmcs_owner=$(stat -c %U "$selected_whmcs" 2>/dev/null || stat -f %Su "$selected_whmcs" 2>/dev/null)
        print_status "Auto-selected: $selected_whmcs"
    else
        read -p "Select WHMCS installation [1-${#WHMCS_PATHS[@]}]: " whmcs_choice
        if [[ "$whmcs_choice" =~ ^[1-9][0-9]*$ ]] && [ "$whmcs_choice" -le ${#WHMCS_PATHS[@]} ]; then
            selected_whmcs="${WHMCS_PATHS[$((whmcs_choice-1))]}"
            whmcs_owner=$(stat -c %U "$selected_whmcs" 2>/dev/null || stat -f %Su "$selected_whmcs" 2>/dev/null)
        else
            print_error "Invalid selection"
            return
        fi
    fi

    echo
    print_config "Configuring integration for: $selected_whmcs"
    print_config "WHMCS owner: $whmcs_owner"

    # Create WHMCS FlexPBX module
    local whmcs_modules_dir="$selected_whmcs/modules/servers/flexpbx"

    echo
    read -p "Create FlexPBX server module in WHMCS? [Y/n]: " create_module
    if [[ "$create_module" != "n" && "$create_module" != "N" ]]; then
        print_status "Creating WHMCS FlexPBX server module..."

        sudo -u "$whmcs_owner" mkdir -p "$whmcs_modules_dir"

        # Create the server module with proper user ownership
        sudo -u "$whmcs_owner" cat > "$whmcs_modules_dir/flexpbx.php" << 'EOFWHMCS'
<?php
/**
 * FlexPBX WHMCS Server Module
 * Integrates FlexPBX with WHMCS for automated provisioning
 */

function flexpbx_MetaData() {
    return array(
        'DisplayName' => 'FlexPBX PBX System',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
        'ServiceSingleSignOnLabel' => 'Login to FlexPBX',
    );
}

function flexpbx_ConfigOptions() {
    return array(
        'package_extensions' => array(
            'FriendlyName' => 'Number of Extensions',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '10',
            'Description' => 'Maximum number of extensions for this package',
        ),
        'voicemail_enabled' => array(
            'FriendlyName' => 'Voicemail Enabled',
            'Type' => 'yesno',
            'Default' => 'yes',
            'Description' => 'Enable voicemail for extensions',
        ),
        'call_recording' => array(
            'FriendlyName' => 'Call Recording',
            'Type' => 'yesno',
            'Default' => 'no',
            'Description' => 'Enable call recording features',
        ),
        'conference_rooms' => array(
            'FriendlyName' => 'Conference Rooms',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '1',
            'Description' => 'Number of conference rooms',
        ),
    );
}

function flexpbx_CreateAccount($params) {
    $serverip = $params['serverip'];
    $serverport = $params['serverport'] ?: ($params['serversecure'] ? 443 : 80);
    $serverssl = $params['serversecure'];
    $username = $params['username'];
    $password = $params['password'];
    $domain = $params['domain'];

    // Configuration options
    $extensions = $params['configoption1'] ?: 10;
    $voicemail = $params['configoption2'] === 'on';
    $call_recording = $params['configoption3'] === 'on';
    $conference_rooms = $params['configoption4'] ?: 1;

    $protocol = $serverssl ? 'https' : 'http';
    $api_url = "$protocol://$serverip:$serverport/api/whmcs/create-account";

    $postfields = array(
        'action' => 'create',
        'domain' => $domain,
        'username' => $username,
        'password' => $password,
        'client_id' => $params['clientsdetails']['id'],
        'service_id' => $params['serviceid'],
        'extensions' => $extensions,
        'voicemail' => $voicemail,
        'call_recording' => $call_recording,
        'conference_rooms' => $conference_rooms,
        'whmcs_auth' => 'enabled'
    );

    try {
        $response = flexpbx_api_call($api_url, $postfields);

        if ($response['success']) {
            return 'success';
        } else {
            return 'Error: ' . $response['message'];
        }
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function flexpbx_TerminateAccount($params) {
    $serverip = $params['serverip'];
    $serverport = $params['serverport'] ?: ($params['serversecure'] ? 443 : 80);
    $serverssl = $params['serversecure'];
    $username = $params['username'];

    $protocol = $serverssl ? 'https' : 'http';
    $api_url = "$protocol://$serverip:$serverport/api/whmcs/terminate-account";

    $postfields = array(
        'action' => 'terminate',
        'username' => $username,
        'service_id' => $params['serviceid']
    );

    try {
        $response = flexpbx_api_call($api_url, $postfields);
        return $response['success'] ? 'success' : 'Error: ' . $response['message'];
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function flexpbx_SuspendAccount($params) {
    return flexpbx_account_action($params, 'suspend');
}

function flexpbx_UnsuspendAccount($params) {
    return flexpbx_account_action($params, 'unsuspend');
}

function flexpbx_account_action($params, $action) {
    $serverip = $params['serverip'];
    $serverport = $params['serverport'] ?: ($params['serversecure'] ? 443 : 80);
    $serverssl = $params['serversecure'];
    $username = $params['username'];

    $protocol = $serverssl ? 'https' : 'http';
    $api_url = "$protocol://$serverip:$serverport/api/whmcs/$action-account";

    $postfields = array(
        'action' => $action,
        'username' => $username,
        'service_id' => $params['serviceid']
    );

    try {
        $response = flexpbx_api_call($api_url, $postfields);
        return $response['success'] ? 'success' : 'Error: ' . $response['message'];
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
}

function flexpbx_ClientArea($params) {
    $serverip = $params['serverip'];
    $serverport = $params['serverport'] ?: ($params['serversecure'] ? 443 : 80);
    $serverssl = $params['serversecure'];
    $username = $params['username'];
    $domain = $params['domain'];

    $protocol = $serverssl ? 'https' : 'http';
    $flexpbx_url = "$protocol://$serverip:$serverport/";
    $admin_url = "$protocol://$serverip:$serverport/admin/";

    return array(
        'templatefile' => 'clientarea',
        'vars' => array(
            'flexpbx_url' => $flexpbx_url,
            'admin_url' => $admin_url,
            'username' => $username,
            'domain' => $domain,
            'extensions' => $params['configoption1'],
            'voicemail' => $params['configoption2'] === 'on' ? 'Enabled' : 'Disabled',
            'call_recording' => $params['configoption3'] === 'on' ? 'Enabled' : 'Disabled',
            'conference_rooms' => $params['configoption4'],
        ),
    );
}

function flexpbx_AdminCustomButtonArray() {
    return array(
        "Login to FlexPBX" => "login",
        "View Extensions" => "extensions",
        "System Status" => "status",
    );
}

function flexpbx_login($params) {
    $serverip = $params['serverip'];
    $serverport = $params['serverport'] ?: ($params['serversecure'] ? 443 : 80);
    $serverssl = $params['serversecure'];

    $protocol = $serverssl ? 'https' : 'http';
    $login_url = "$protocol://$serverip:$serverport/admin/";

    header("Location: $login_url");
    exit;
}

function flexpbx_api_call($url, $postfields) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: WHMCS-FlexPBX-Module/1.0'
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_error($ch)) {
        throw new Exception('CURL Error: ' . curl_error($ch));
    }

    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: $httpCode");
    }

    $decoded = json_decode($response, true);
    if ($decoded === null) {
        throw new Exception('Invalid JSON response');
    }

    return $decoded;
}
?>
EOFWHMCS

        # Create client area template
        sudo -u "$whmcs_owner" mkdir -p "$whmcs_modules_dir/templates"
        sudo -u "$whmcs_owner" cat > "$whmcs_modules_dir/templates/clientarea.tpl" << 'EOFTEMPLATE'
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">FlexPBX PBX System</h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <h4>Service Information</h4>
                <p><strong>Username:</strong> {$username}</p>
                <p><strong>Domain:</strong> {$domain}</p>
                <p><strong>Extensions:</strong> {$extensions}</p>
                <p><strong>Voicemail:</strong> {$voicemail}</p>
                <p><strong>Call Recording:</strong> {$call_recording}</p>
                <p><strong>Conference Rooms:</strong> {$conference_rooms}</p>
            </div>
            <div class="col-md-6">
                <h4>Quick Access</h4>
                <p><a href="{$flexpbx_url}" target="_blank" class="btn btn-primary">Open FlexPBX</a></p>
                <p><a href="{$admin_url}" target="_blank" class="btn btn-success">Admin Panel</a></p>
                <p><a href="{$flexpbx_url}phone/" target="_blank" class="btn btn-info">FlexPhone</a></p>
            </div>
        </div>
    </div>
</div>
EOFTEMPLATE

        print_success "WHMCS FlexPBX module created in $whmcs_modules_dir"
        print_status "Module owner: $whmcs_owner"
    fi

    echo
    print_config "WHMCS Integration Summary:"
    echo "  WHMCS Path: $selected_whmcs"
    echo "  Owner: $whmcs_owner"
    echo "  Module Path: $whmcs_modules_dir"
    echo "  Authentication: WHMCS users can access FlexPBX"
    echo
    read -p "Press Enter to continue..."
}

# Function to configure WordPress SSO
configure_wordpress_sso() {
    clear
    echo "🌐 WordPress SSO Configuration"
    echo "=============================="
    echo

    if [ ${#WORDPRESS_PATHS[@]} -eq 0 ]; then
        print_error "No WordPress installations detected"
        return
    fi

    echo "Detected WordPress installations:"
    for i in "${!WORDPRESS_PATHS[@]}"; do
        wp_path="${WORDPRESS_PATHS[$i]}"
        wp_owner=$(stat -c %U "$wp_path" 2>/dev/null || stat -f %Su "$wp_path" 2>/dev/null)
        echo "  $((i+1)). $wp_path (user: $wp_owner)"
    done
    echo

    if [ ${#WORDPRESS_PATHS[@]} -eq 1 ]; then
        selected_wp="${WORDPRESS_PATHS[0]}"
        wp_owner=$(stat -c %U "$selected_wp" 2>/dev/null || stat -f %Su "$selected_wp" 2>/dev/null)
        print_status "Auto-selected: $selected_wp"
    else
        read -p "Select WordPress installation [1-${#WORDPRESS_PATHS[@]}]: " wp_choice
        if [[ "$wp_choice" =~ ^[1-9][0-9]*$ ]] && [ "$wp_choice" -le ${#WORDPRESS_PATHS[@]} ]; then
            selected_wp="${WORDPRESS_PATHS[$((wp_choice-1))]}"
            wp_owner=$(stat -c %U "$selected_wp" 2>/dev/null || stat -f %Su "$selected_wp" 2>/dev/null)
        else
            print_error "Invalid selection"
            return
        fi
    fi

    echo
    print_config "Configuring SSO for: $selected_wp"
    print_config "WordPress owner: $wp_owner"

    # Create WordPress FlexPBX SSO plugin
    local wp_plugin_dir="$selected_wp/wp-content/plugins/flexpbx-sso"

    echo
    read -p "Create FlexPBX SSO plugin for WordPress? [Y/n]: " create_plugin
    if [[ "$create_plugin" != "n" && "$create_plugin" != "N" ]]; then
        print_status "Creating WordPress FlexPBX SSO plugin..."

        sudo -u "$wp_owner" mkdir -p "$wp_plugin_dir"

        # Create the main plugin file
        sudo -u "$wp_owner" cat > "$wp_plugin_dir/flexpbx-sso.php" << 'EOFWP'
<?php
/**
 * Plugin Name: FlexPBX SSO Integration
 * Description: Single Sign-On integration between WordPress and FlexPBX
 * Version: 1.0.0
 * Author: FlexPBX Team
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FlexPBX_SSO {

    private $flexpbx_url;
    private $api_key;

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_login', array($this, 'wp_login_handler'), 10, 2);
        add_action('wp_logout', array($this, 'wp_logout_handler'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_shortcode('flexpbx_access', array($this, 'flexpbx_access_shortcode'));

        $this->flexpbx_url = get_option('flexpbx_url', 'https://localhost/');
        $this->api_key = get_option('flexpbx_api_key', '');
    }

    public function init() {
        // Handle FlexPBX SSO callback
        if (isset($_GET['flexpbx_sso']) && isset($_GET['token'])) {
            $this->handle_sso_callback($_GET['token']);
        }
    }

    public function wp_login_handler($user_login, $user) {
        // Generate FlexPBX access token for logged-in user
        $token = $this->generate_flexpbx_token($user);
        if ($token) {
            setcookie('flexpbx_sso_token', $token, time() + 3600, '/');
        }
    }

    public function wp_logout_handler() {
        // Clear FlexPBX SSO token
        setcookie('flexpbx_sso_token', '', time() - 3600, '/');
    }

    public function admin_menu() {
        add_options_page(
            'FlexPBX SSO Settings',
            'FlexPBX SSO',
            'manage_options',
            'flexpbx-sso',
            array($this, 'admin_page')
        );
    }

    public function admin_page() {
        if ($_POST['submit']) {
            update_option('flexpbx_url', sanitize_url($_POST['flexpbx_url']));
            update_option('flexpbx_api_key', sanitize_text_field($_POST['flexpbx_api_key']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }

        $flexpbx_url = get_option('flexpbx_url', '');
        $flexpbx_api_key = get_option('flexpbx_api_key', '');
        ?>
        <div class="wrap">
            <h1>FlexPBX SSO Settings</h1>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">FlexPBX URL</th>
                        <td><input type="url" name="flexpbx_url" value="<?php echo esc_attr($flexpbx_url); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row">API Key</th>
                        <td><input type="text" name="flexpbx_api_key" value="<?php echo esc_attr($flexpbx_api_key); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function flexpbx_access_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please <a href="' . wp_login_url() . '">login</a> to access FlexPBX.</p>';
        }

        $current_user = wp_get_current_user();
        $sso_url = $this->flexpbx_url . '?wp_sso=1&user=' . urlencode($current_user->user_login);

        return '<p><a href="' . esc_url($sso_url) . '" target="_blank" class="button button-primary">Access FlexPBX</a></p>';
    }

    private function generate_flexpbx_token($user) {
        $data = array(
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'roles' => $user->roles,
            'timestamp' => time()
        );

        $token = base64_encode(json_encode($data));

        // Send token to FlexPBX for validation
        $response = wp_remote_post($this->flexpbx_url . 'api/wordpress/sso', array(
            'body' => array(
                'action' => 'register_token',
                'token' => $token,
                'api_key' => $this->api_key
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return $token;
    }

    private function handle_sso_callback($token) {
        // Validate token with FlexPBX
        $response = wp_remote_post($this->flexpbx_url . 'api/wordpress/validate', array(
            'body' => array(
                'token' => $token,
                'api_key' => $this->api_key
            ),
            'timeout' => 15
        ));

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($data['valid']) {
                // Redirect to FlexPBX with authentication
                wp_redirect($this->flexpbx_url . '?authenticated=1');
                exit;
            }
        }

        wp_die('Invalid SSO token');
    }
}

new FlexPBX_SSO();
?>
EOFWP

        print_success "WordPress FlexPBX SSO plugin created in $wp_plugin_dir"
        print_status "Plugin owner: $wp_owner"
        print_warning "Remember to activate the plugin in WordPress admin"
    fi

    echo
    print_config "WordPress SSO Integration Summary:"
    echo "  WordPress Path: $selected_wp"
    echo "  Owner: $wp_owner"
    echo "  Plugin Path: $wp_plugin_dir"
    echo "  Authentication: WordPress users can SSO to FlexPBX"
    echo "  Shortcode: [flexpbx_access] for frontend access"
    echo
    read -p "Press Enter to continue..."
}

# Run main function
main "$@"