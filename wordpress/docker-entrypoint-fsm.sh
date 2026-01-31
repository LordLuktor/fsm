#!/bin/bash
# =============================================================================
# FSM WordPress Custom Entrypoint
# =============================================================================
# Handles Docker secrets and environment variable expansion
# before passing to the standard WordPress entrypoint.
# =============================================================================

set -e

# Function to read secret from file
read_secret() {
    local var_name=$1
    local file_var="${var_name}_FILE"

    if [ -n "${!file_var}" ] && [ -f "${!file_var}" ]; then
        export "$var_name"="$(cat "${!file_var}")"
    fi
}

# Read secrets from files
read_secret "WORDPRESS_DB_PASSWORD"
read_secret "JWT_SECRET"
read_secret "GITHUB_ACCESS_TOKEN"

# Ensure mu-plugins directory exists with correct permissions
mkdir -p /var/www/html/wp-content/mu-plugins
chown www-data:www-data /var/www/html/wp-content/mu-plugins

# Ensure uploads directory exists with correct permissions
mkdir -p /var/www/html/wp-content/uploads
chown -R www-data:www-data /var/www/html/wp-content/uploads

# Call the original WordPress entrypoint
exec docker-entrypoint.sh "$@"
