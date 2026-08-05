# =============================================================================
# FSM WordPress - Custom Docker Image
# =============================================================================
# Based on official WordPress image with:
# - Git Updater plugin for GitHub-based updates
# - Redis Object Cache plugin
# - JWT Authentication plugin
# - WP-CLI for management
# =============================================================================

FROM wordpress:6.7-php8.3-apache

# Install required packages
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    less \
    && rm -rf /var/lib/apt/lists/*

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Create plugins directory
RUN mkdir -p /var/www/html/wp-content/plugins

# Download and install Git Updater plugin
# https://github.com/afragen/git-updater
RUN curl -fsSL $(curl -s https://api.github.com/repos/afragen/git-updater/releases/latest | grep -oP '"browser_download_url": "\K[^"]+\.zip') -o /tmp/git-updater.zip \
    && unzip /tmp/git-updater.zip -d /var/www/html/wp-content/plugins/ \
    && rm /tmp/git-updater.zip

# Download and install Redis Object Cache plugin
# https://wordpress.org/plugins/redis-cache/
RUN curl -L https://downloads.wordpress.org/plugin/redis-cache.latest-stable.zip -o /tmp/redis-cache.zip \
    && unzip /tmp/redis-cache.zip -d /var/www/html/wp-content/plugins/ \
    && rm /tmp/redis-cache.zip

# Download and install JWT Authentication for WP REST API
# https://wordpress.org/plugins/jwt-authentication-for-wp-rest-api/
RUN curl -L https://downloads.wordpress.org/plugin/jwt-authentication-for-wp-rest-api.latest-stable.zip -o /tmp/jwt-auth.zip \
    && unzip /tmp/jwt-auth.zip -d /var/www/html/wp-content/plugins/ \
    && rm /tmp/jwt-auth.zip

# Download Paid Memberships Pro to staging dir (copied to volume at startup)
# https://wordpress.org/plugins/paid-memberships-pro/
RUN mkdir -p /opt/pmpro-staging \
    && curl -L https://downloads.wordpress.org/plugin/paid-memberships-pro.latest-stable.zip -o /tmp/pmpro.zip \
    && unzip /tmp/pmpro.zip -d /opt/pmpro-staging/ \
    && rm /tmp/pmpro.zip

# Download and install Application Passwords (built into WP 5.6+, but plugin provides admin UI)
# Skip - built into WordPress 5.6+

# Stage mu-plugins outside the volume (copied at startup)
RUN mkdir -p /opt/mu-plugins-staging
COPY mu-fsm-loader.php /opt/mu-plugins-staging/
COPY headless-mode.php /opt/mu-plugins-staging/
COPY security-hardening.php /opt/mu-plugins-staging/
COPY fsm-smtp.php /opt/mu-plugins-staging/

# Custom PHP configuration for larger uploads and better performance
RUN { \
    echo 'upload_max_filesize = 64M'; \
    echo 'post_max_size = 64M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_vars = 3000'; \
} > /usr/local/etc/php/conf.d/fsm-custom.ini

# Apache security: block PHP execution in directories that should never run PHP
RUN { \
    echo '<Directory /var/www/html/wp-content/uploads>'; \
    echo '    <FilesMatch "\\.php$">'; \
    echo '        Require all denied'; \
    echo '    </FilesMatch>'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/wp-content/upgrade>'; \
    echo '    <FilesMatch "\\.php$">'; \
    echo '        Require all denied'; \
    echo '    </FilesMatch>'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/wp-includes>'; \
    echo '    <FilesMatch "\\.php$">'; \
    echo '        Require all denied'; \
    echo '    </FilesMatch>'; \
    echo '    # Allow wp-includes/ms-files.php for multisite'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/wp-admin/css>'; \
    echo '    <FilesMatch "\\.php$">'; \
    echo '        Require all denied'; \
    echo '    </FilesMatch>'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/wp-admin/js>'; \
    echo '    <FilesMatch "\\.php$">'; \
    echo '        Require all denied'; \
    echo '    </FilesMatch>'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/wp-admin/images>'; \
    echo '    <FilesMatch "\\.php$">'; \
    echo '        Require all denied'; \
    echo '    </FilesMatch>'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/wp-admin/maint>'; \
    echo '    <FilesMatch "\\.php$">'; \
    echo '        Require all denied'; \
    echo '    </FilesMatch>'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/wp-admin/network>'; \
    echo '    # Allow only legitimate WP network admin files'; \
    echo '    <FilesMatch "^(?!admin|edit|index|plugins|settings|setup|site|sites|theme|themes|update|upgrade|user|users).*\\.php$">'; \
    echo '        Require all denied'; \
    echo '    </FilesMatch>'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/images>'; \
    echo '    <FilesMatch "\\.php$">'; \
    echo '        Require all denied'; \
    echo '    </FilesMatch>'; \
    echo '</Directory>'; \
} > /etc/apache2/conf-available/fsm-security.conf \
    && a2enconf fsm-security

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/wp-content

# Custom entrypoint to handle secrets
COPY docker-entrypoint-fsm.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint-fsm.sh

ENTRYPOINT ["docker-entrypoint-fsm.sh"]
CMD ["apache2-foreground"]
