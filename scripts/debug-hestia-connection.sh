#!/bin/bash

# HestiaCP Connection Debugging Script
# Run this on your production server to diagnose connection issues

HESTIA_IP="${1:-188.245.239.133}"
HESTIA_PORT="${2:-8443}"

echo "========================================="
echo "HestiaCP Connection Debugging"
echo "========================================="
echo "Target: ${HESTIA_IP}:${HESTIA_PORT}"
echo ""

# 1. Check DNS resolution (if using FQDN)
echo "1. DNS Resolution Check:"
if [[ $HESTIA_IP =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "   ✓ Using direct IP address: $HESTIA_IP"
else
    echo "   Resolving hostname..."
    host $HESTIA_IP || echo "   ✗ DNS resolution failed"
fi
echo ""

# 2. Check basic network connectivity
echo "2. ICMP Ping Test:"
if ping -c 3 -W 2 $HESTIA_IP > /dev/null 2>&1; then
    echo "   ✓ Server is reachable via ICMP"
else
    echo "   ✗ Server is NOT reachable via ICMP (may be normal if ICMP is blocked)"
fi
echo ""

# 3. Check if port is accessible
echo "3. Port ${HESTIA_PORT} Connectivity:"
if command -v nc > /dev/null 2>&1; then
    if timeout 5 nc -zv $HESTIA_IP $HESTIA_PORT 2>&1 | grep -q succeeded; then
        echo "   ✓ Port ${HESTIA_PORT} is OPEN and accessible"
    else
        echo "   ✗ Port ${HESTIA_PORT} is CLOSED or FILTERED"
        echo "   This is the likely cause of your connection error!"
    fi
else
    echo "   ⚠ netcat (nc) not available, trying telnet..."
    if command -v telnet > /dev/null 2>&1; then
        timeout 5 telnet $HESTIA_IP $HESTIA_PORT 2>&1 | head -5
    else
        echo "   ✗ Neither nc nor telnet available"
    fi
fi
echo ""

# 4. Check route to server
echo "4. Network Route:"
traceroute -m 10 -w 2 $HESTIA_IP 2>&1 | head -10 || echo "   traceroute not available"
echo ""

# 5. Test HTTPS connection
echo "5. HTTPS Connection Test:"
if command -v curl > /dev/null 2>&1; then
    echo "   Testing HTTPS connection with curl..."
    curl_output=$(curl -k -v --connect-timeout 5 "https://${HESTIA_IP}:${HESTIA_PORT}/api/" 2>&1)
    if echo "$curl_output" | grep -q "Connected to"; then
        echo "   ✓ HTTPS connection successful"
    else
        echo "   ✗ HTTPS connection failed"
        echo "$curl_output" | grep -E "(Could not|Failed to|Connection|Timeout)" | head -5
    fi
else
    echo "   ✗ curl not available"
fi
echo ""

# 6. Check firewall rules (if running with sudo)
echo "6. Firewall Status:"
if command -v ufw > /dev/null 2>&1; then
    if sudo -n true 2>/dev/null; then
        sudo ufw status | grep -E "Status|${HESTIA_PORT}|${HESTIA_IP}"
    else
        echo "   Run with sudo to check UFW firewall rules"
    fi
elif command -v iptables > /dev/null 2>&1; then
    if sudo -n true 2>/dev/null; then
        sudo iptables -L -n | grep -E "${HESTIA_PORT}|${HESTIA_IP}" | head -5
    else
        echo "   Run with sudo to check iptables rules"
    fi
else
    echo "   No common firewall tools found"
fi
echo ""

# 7. Check if running in Docker/Container
echo "7. Environment Check:"
if [ -f /.dockerenv ]; then
    echo "   ⚠ Running inside Docker container"
    echo "   Check if container has network access to external servers"
elif [ -f /proc/1/cgroup ] && grep -q docker /proc/1/cgroup; then
    echo "   ⚠ Running inside Docker container"
    echo "   Check if container has network access to external servers"
else
    echo "   ✓ Running on host system (not containerized)"
fi
echo ""

echo "========================================="
echo "Diagnosis Summary:"
echo "========================================="
echo "If port ${HESTIA_PORT} is not accessible:"
echo "  - Check HestiaCP server firewall allows incoming on port ${HESTIA_PORT}"
echo "  - Check production server firewall allows outgoing to ${HESTIA_IP}:${HESTIA_PORT}"
echo "  - Verify HestiaCP service is running on target server"
echo "  - Check if servers are on same network/VPN"
echo ""
echo "Common fixes:"
echo "  1. On HestiaCP server: sudo ufw allow ${HESTIA_PORT}/tcp"
echo "  2. Restart HestiaCP: sudo systemctl restart hestia"
echo "  3. Check HestiaCP logs: sudo tail -f /var/log/hestia/error.log"
echo "========================================="
