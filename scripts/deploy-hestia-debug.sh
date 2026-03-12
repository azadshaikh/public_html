#!/bin/bash
#
# Script: deploy-hestia-debug.sh
#
# Description: Deploys the universal debug functions to Hestia servers for enhanced
#              debugging capabilities. This script should be run whenever you want
#              to enable full debug functionality on your Hestia servers.
#
# Usage: ./scripts/deploy-hestia-debug.sh [SERVER_IP]
#
# Example: ./scripts/deploy-hestia-debug.sh 192.168.1.100
#
# Note: If no server IP is provided, you'll be prompted to enter it.

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${BLUE}Hestia Debug Functions Deployment Script${NC}"
echo "========================================"

# Get server IP
if [ -z "$1" ]; then
    echo -e "${YELLOW}Enter the Hestia server IP address:${NC}"
    read -p "Server IP: " SERVER_IP
else
    SERVER_IP="$1"
fi

if [ -z "$SERVER_IP" ]; then
    echo -e "${RED}Error: Server IP is required${NC}"
    exit 1
fi

echo -e "${BLUE}Target server: ${SERVER_IP}${NC}"

# Validate that the debug functions file exists
DEBUG_FUNCTIONS_FILE="$PROJECT_ROOT/hestia/bin/a-debug-functions.sh"
if [ ! -f "$DEBUG_FUNCTIONS_FILE" ]; then
    echo -e "${RED}Error: Debug functions file not found at: $DEBUG_FUNCTIONS_FILE${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Debug functions file found${NC}"

# Get SSH credentials
echo -e "${YELLOW}Enter SSH credentials for the Hestia server:${NC}"
read -p "SSH username [root]: " SSH_USER
SSH_USER="${SSH_USER:-root}"

read -p "SSH port [22]: " SSH_PORT
SSH_PORT="${SSH_PORT:-22}"

echo -e "${BLUE}Deploying debug functions to $SERVER_IP...${NC}"

# Test SSH connection
echo -e "${YELLOW}Testing SSH connection...${NC}"
if ! ssh -o ConnectTimeout=10 -o BatchMode=yes -p "$SSH_PORT" "$SSH_USER@$SERVER_IP" exit 2>/dev/null; then
    echo -e "${RED}Error: Cannot connect to server via SSH${NC}"
    echo "Please ensure:"
    echo "1. The server is accessible"
    echo "2. SSH key authentication is set up"
    echo "3. The SSH port and username are correct"
    exit 1
fi

echo -e "${GREEN}✓ SSH connection successful${NC}"

# Deploy the debug functions file
echo -e "${YELLOW}Deploying a-debug-functions.sh...${NC}"
if scp -P "$SSH_PORT" "$DEBUG_FUNCTIONS_FILE" "$SSH_USER@$SERVER_IP:/usr/local/hestia/bin/"; then
    echo -e "${GREEN}✓ Debug functions deployed successfully${NC}"
else
    echo -e "${RED}Error: Failed to deploy debug functions${NC}"
    exit 1
fi

# Set correct permissions
echo -e "${YELLOW}Setting file permissions...${NC}"
if ssh -p "$SSH_PORT" "$SSH_USER@$SERVER_IP" "chmod +x /usr/local/hestia/bin/a-debug-functions.sh"; then
    echo -e "${GREEN}✓ File permissions set${NC}"
else
    echo -e "${RED}Error: Failed to set file permissions${NC}"
    exit 1
fi

# Create log directory
echo -e "${YELLOW}Creating debug log directory...${NC}"
if ssh -p "$SSH_PORT" "$SSH_USER@$SERVER_IP" "mkdir -p /usr/local/hestia/data/astero/logs && chmod 755 /usr/local/hestia/data/astero/logs"; then
    echo -e "${GREEN}✓ Debug log directory created${NC}"
else
    echo -e "${RED}Error: Failed to create debug log directory${NC}"
    exit 1
fi

# Test the debug functions
echo -e "${YELLOW}Testing debug functions...${NC}"
TEST_RESULT=$(ssh -p "$SSH_PORT" "$SSH_USER@$SERVER_IP" "
    source /usr/local/hestia/bin/a-debug-functions.sh 2>/dev/null && echo 'SUCCESS' || echo 'FAILED'
")

if [ "$TEST_RESULT" = "SUCCESS" ]; then
    echo -e "${GREEN}✓ Debug functions are working correctly${NC}"
else
    echo -e "${RED}Warning: Debug functions test failed${NC}"
    echo "The file was deployed but may have syntax errors."
fi

echo ""
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "1. Set APP_DEBUG=true in your Laravel .env file to enable debug mode"
echo "2. Debug logs will be written to: /usr/local/hestia/data/astero/logs/astero-scripts.log"
echo "3. Monitor logs in real-time with: tail -f /usr/local/hestia/data/astero/logs/astero-scripts.log"
echo ""
echo -e "${YELLOW}Note:${NC} The Hestia scripts in your project are now templates."
echo "Any changes to debug functions should be deployed using this script."

# Optional: Set up log rotation
echo ""
read -p "Do you want to set up log rotation for debug logs? (y/N): " SETUP_LOGROTATE
if [[ "$SETUP_LOGROTATE" =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Setting up log rotation...${NC}"

    LOGROTATE_CONFIG="/etc/logrotate.d/astero-scripts"
    ssh -p "$SSH_PORT" "$SSH_USER@$SERVER_IP" "cat > $LOGROTATE_CONFIG << 'EOF'
/usr/local/hestia/data/astero/logs/astero-scripts.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 644 root root
    postrotate
        # No need to restart any service
    endscript
}
EOF"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Log rotation configured${NC}"
    else
        echo -e "${RED}Warning: Failed to configure log rotation${NC}"
    fi
fi

echo ""
echo -e "${GREEN}Deployment process completed!${NC}"
