#!/bin/bash

# Generate SSL Certificate for Vite HTTPS Development
# This script creates a self-signed certificate for local development

echo "🔐 Generating SSL certificate for Vite HTTPS development..."

# Create ssl directory if it doesn't exist
mkdir -p ssl

# Generate private key
echo "📝 Generating private key..."
openssl genrsa -out ssl/key.pem 2048

# Generate certificate
echo "📜 Generating certificate..."
openssl req -new -x509 -key ssl/key.pem -out ssl/cert.pem -days 365 -subj "/C=US/ST=Dev/L=Local/O=ViteDev/CN=localhost"

# Set proper permissions
chmod 600 ssl/key.pem
chmod 644 ssl/cert.pem

echo "✅ SSL certificate generated successfully!"
echo ""
echo "📁 Files created:"
echo "   - ssl/cert.pem (Certificate)"
echo "   - ssl/key.pem (Private Key)"
echo ""
echo "🚀 Now you can run: npm run dev"
echo "   Access via: https://localhost:5173"
echo "   Or: https://172.22.34.126:5173"
echo ""
echo "⚠️  Note: Your browser will show a security warning for self-signed certificates."
echo "   Click 'Advanced' → 'Proceed to localhost (unsafe)' to continue."
