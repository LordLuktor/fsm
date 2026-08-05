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
read_secret "FSM_MUMBLE_API_KEY"
read_secret "REDIS_PASSWORD"
read_secret "SMTP_PASSWORD"

# Ensure mu-plugins directory exists with correct permissions
mkdir -p /var/www/html/wp-content/mu-plugins
chown www-data:www-data /var/www/html/wp-content/mu-plugins

# Always copy mu-plugins from staging (image always has latest version)
if [ -d /opt/mu-plugins-staging ]; then
    cp -f /opt/mu-plugins-staging/*.php /var/www/html/wp-content/mu-plugins/
    chown www-data:www-data /var/www/html/wp-content/mu-plugins/*.php
fi

# Ensure uploads directory exists with correct permissions
mkdir -p /var/www/html/wp-content/uploads
chown -R www-data:www-data /var/www/html/wp-content/uploads

# Enforce .htaccess in uploads to block PHP execution (defense-in-depth)
cat > /var/www/html/wp-content/uploads/.htaccess << 'HTEOF'
<FilesMatch "\.php$">
    Require all denied
</FilesMatch>
HTEOF
chown www-data:www-data /var/www/html/wp-content/uploads/.htaccess

# Enforce clean root .htaccess (prevent attacker modifications from persisting)
cat > /var/www/html/.htaccess << 'HTEOF'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /

# Pass Authorization header to PHP (required for JWT authentication)
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]

RewriteRule ^index.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
</IfModule>
# END WordPress
HTEOF
chown www-data:www-data /var/www/html/.htaccess

# Copy PMPro from staging into the volume if not already present
if [ -d /opt/pmpro-staging/paid-memberships-pro ] && [ ! -f /var/www/html/wp-content/plugins/paid-memberships-pro/paid-memberships-pro.php ]; then
    cp -r /opt/pmpro-staging/paid-memberships-pro /var/www/html/wp-content/plugins/
    chown -R www-data:www-data /var/www/html/wp-content/plugins/paid-memberships-pro
fi

# Activate PMPro if not already active
wp plugin activate paid-memberships-pro --allow-root 2>/dev/null || true

# Call the original WordPress entrypoint
exec docker-entrypoint.sh "$@"
