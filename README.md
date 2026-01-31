# FSM - Field Service Management System

A complete Field Service Management system built with headless WordPress and React.

## Architecture

- **WordPress (Headless)**: REST API backend, authentication, business logic
- **React Frontend**: Technician and dispatch dashboards
- **MariaDB**: Database
- **Redis**: Object caching
- **Mumble**: PTT communication (integration with existing server)

## Plugins

Each major subsystem is its own WordPress plugin:

| Plugin | Description |
|--------|-------------|
| `fsm-core` | Core post types, roles, capabilities, utilities |
| `fsm-work-orders` | Work order CRUD, status lifecycle, notes |
| `fsm-dispatch` | Scheduling, technician assignment |
| `fsm-inventory` | Global and technician-level inventory tracking |
| `fsm-gps` | Location tracking with role-based visibility |
| `fsm-ptt` | Mumble integration for PTT communications |

## Deployment

### Prerequisites

- Docker Swarm cluster
- Traefik reverse proxy with Let's Encrypt
- DNS records for `fsm.steinmetz.ltd` and `fsm-api.steinmetz.ltd`

### Create Docker Secrets

```bash
echo "your_db_root_password" | docker secret create fsm_db_root_password -
echo "your_db_password" | docker secret create fsm_db_password -
echo "your_jwt_secret_min_32_chars" | docker secret create fsm_jwt_secret -
echo "your_github_token" | docker secret create fsm_github_token -
```

### Deploy

```bash
cd /path/to/fsm
./deploy.sh --create-secrets  # First time only
./deploy.sh                    # Subsequent deploys
```

### Manual Deployment

```bash
# Build images
docker build -t fsm-wordpress:latest ./wordpress
docker build -t fsm-frontend:latest ./frontend

# Deploy stack
docker stack deploy -c docker-compose.yml fsm
```

## Development

### Frontend

```bash
cd frontend
npm install
npm run dev
```

### WordPress Plugins

Plugins are mounted into WordPress at `/var/www/html/wp-content/plugins/fsm/`.

## API Endpoints

All API endpoints are under `https://fsm-api.steinmetz.ltd/wp-json/fsm/v1/`:

- `GET /info` - System information
- `GET /me` - Current user
- `GET /me/capabilities` - User capabilities
- `GET /work-orders` - List work orders
- `POST /work-orders` - Create work order
- `GET /work-orders/{id}` - Get work order
- `PATCH /work-orders/{id}` - Update work order
- `PATCH /work-orders/{id}/status` - Update status
- `GET /dispatch/board` - Dispatch board
- `POST /dispatch/assign` - Assign work order
- `GET /inventory` - List inventory
- `POST /inventory/consume` - Consume inventory
- `POST /gps/update` - Update location
- `GET /gps/all` - All technician locations (dispatchers only)
- `GET /ptt/channels` - Get PTT channels
- `GET /ptt/connection` - Get Mumble connection info

## Roles

- **FSM Administrator**: Full access to all features
- **FSM Dispatcher**: Manages work orders, schedules, views all technicians
- **FSM Technician**: Views assigned work orders, updates status, limited access

## Updates

Plugins are configured for automatic updates via Git Updater.
Add your GitHub Personal Access Token to `wp-config.php`:

```php
define('GITHUB_ACCESS_TOKEN', 'your_token_here');
```

## License

GPL v2 or later
