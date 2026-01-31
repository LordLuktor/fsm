# =============================================================================
# FSM WordPress - Custom Docker Image
# =============================================================================
# Based on official WordPress image with:
# - Git Updater plugin for GitHub-based updates
# - Redis Object Cache plugin
# - JWT Authentication plugin
# - WP-CLI for management
# =============================================================================

FROM wordpress:latest

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
RUN curl -L https://github.com/afragen/git-updater/releases/latest/download/git-updater.zip -o /tmp/git-updater.zip \
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

# Download and install Application Passwords (built into WP 5.6+, but plugin provides admin UI)
# Skip - built into WordPress 5.6+

# Create mu-plugins directory for must-use plugins
RUN mkdir -p /var/www/html/wp-content/mu-plugins

# Add FSM loader mu-plugin to auto-load FSM plugins
COPY mu-fsm-loader.php /var/www/html/wp-content/mu-plugins/

# Add headless mode configuration
COPY headless-mode.php /var/www/html/wp-content/mu-plugins/

# Custom PHP configuration for larger uploads and better performance
RUN { \
    echo 'upload_max_filesize = 64M'; \
    echo 'post_max_size = 64M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_vars = 3000'; \
} > /usr/local/etc/php/conf.d/fsm-custom.ini

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/wp-content

# Custom entrypoint to handle secrets
COPY docker-entrypoint-fsm.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint-fsm.sh

ENTRYPOINT ["docker-entrypoint-fsm.sh"]
CMD ["apache2-foreground"]
