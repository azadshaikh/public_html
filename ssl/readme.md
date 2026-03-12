# SSL Folder

This folder stores SSL certificates and private keys used for local HTTPS development.

## Why this folder exists

- The `ssl` directory is required to hold self-signed SSL certificates and private keys.
- These files enable secure HTTPS connections for local development environments.
- **It is primarily required for the Vite dev server to enable HTTPS on localhost.**
- Keeping SSL assets in a dedicated folder helps organize sensitive files and simplifies configuration.

## How files are created and used

- The script [`scripts/generate-ssl-cert.sh`](../scripts/generate-ssl-cert.sh) automates the creation of a self-signed certificate and private key.
- When you run the script, it generates:
    - `ssl/cert.pem` (the certificate)
    - `ssl/key.pem` (the private key)
- These files are used by development servers (like Vite) to enable HTTPS on `localhost`.

## Usage

1. Run the certificate generation script:
    ```bash
    ./scripts/generate-ssl-cert.sh
    ```
2. Start your development server (e.g., `npm run dev`).
3. Access your app via `https://localhost:5173` or your local IP.

> Note: Browsers will warn about self-signed certificates. Proceed by accepting the warning for local development.

## Security

- The private key (`key.pem`) is set to be readable only by the owner for security.
- Do not use these certificates in production.
