#!/bin/bash
# =============================================================================
# FSM - Field Service Management System
# Deployment Script
# =============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${GREEN}==================================================${NC}"
echo -e "${GREEN}FSM + Tracker Deployment Script${NC}"
echo -e "${GREEN}==================================================${NC}"

# Check if running in Swarm mode
if ! docker info 2>/dev/null | grep -q "Swarm: active"; then
    echo -e "${RED}Error: Docker Swarm is not active${NC}"
    exit 1
fi

# Function to create secret if it doesn't exist
create_secret_if_missing() {
    local secret_name=$1
    local prompt=$2

    if docker secret inspect "$secret_name" >/dev/null 2>&1; then
        echo -e "${YELLOW}Secret '$secret_name' already exists, skipping...${NC}"
    else
        echo -e "${GREEN}Creating secret: $secret_name${NC}"
        read -sp "$prompt: " secret_value
        echo
        echo "$secret_value" | docker secret create "$secret_name" -
        echo -e "${GREEN}Secret '$secret_name' created successfully${NC}"
    fi
}

# Check for --create-secrets flag
if [[ "$1" == "--create-secrets" ]]; then
    echo ""
    echo -e "${YELLOW}Creating Docker secrets...${NC}"
    echo ""

    echo -e "${CYAN}--- Database ---${NC}"
    create_secret_if_missing "fsm_db_root_password" "Enter database ROOT password"
    create_secret_if_missing "fsm_db_password" "Enter database USER password"

    echo -e "${CYAN}--- Authentication ---${NC}"
    create_secret_if_missing "fsm_jwt_secret" "Enter JWT secret (min 32 chars)"
    create_secret_if_missing "fsm_github_token" "Enter GitHub Personal Access Token"

    echo -e "${CYAN}--- FSM Integrations ---${NC}"
    create_secret_if_missing "fsm_mumble_api_key" "Enter Mumble API key"

    echo ""
    echo -e "${GREEN}All secrets created!${NC}"
    echo ""
fi

# Build images
echo ""
echo -e "${YELLOW}Building Docker images...${NC}"
echo ""

# Build WordPress image
echo "Building WordPress image..."
docker build -t fsm-wordpress:latest ./wordpress

# Build FSM Frontend image
echo "Building FSM Frontend image..."
docker build -t fsm-frontend:latest \
    --build-arg VITE_API_URL=https://fsm-api.steinmetz.ltd/wp-json \
    ./frontend

# Build Tracker Frontend image
echo "Building Tracker Frontend image..."
docker build -t tracker-frontend:latest \
    --build-arg VITE_API_URL=https://fsm-api.steinmetz.ltd/wp-json \
    ./tracker/frontend

# Deploy stack
echo ""
echo -e "${YELLOW}Deploying FSM stack...${NC}"
echo ""

docker stack deploy -c docker-compose.yml fsm

echo ""
echo -e "${GREEN}==================================================${NC}"
echo -e "${GREEN}Deployment Complete!${NC}"
echo -e "${GREEN}==================================================${NC}"
echo ""
echo "Services:"
echo "  - WordPress API:      https://fsm-api.steinmetz.ltd"
echo "  - FSM Frontend:       https://fsm.steinmetz.ltd"
echo "  - Tracker Frontend:   https://tracker.steinmetz.ltd"
echo ""
echo "Monitor deployment:"
echo "  docker service ls | grep fsm"
echo "  docker service logs -f fsm_wordpress"
echo "  docker service logs -f fsm_tracker-frontend"
echo ""
echo -e "${YELLOW}Note: First startup may take a few minutes.${NC}"
echo -e "${YELLOW}WordPress needs to initialize and create tables.${NC}"
echo -e "${YELLOW}Tracker tables are auto-created on first request.${NC}"
