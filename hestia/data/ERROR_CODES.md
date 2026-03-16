# Astero Hestia Scripts - Error Code Reference

This document provides a central reference for all error codes used across Astero Hestia scripts.

## Standard HestiaCP Codes (0-17)

These are native HestiaCP error codes used by `v-*` commands.

| Code | Description                                                  |
| ---- | ------------------------------------------------------------ |
| 0    | Command has been successfully performed                      |
| 1    | Not enough arguments provided                                |
| 2    | Object or argument is not valid                              |
| 3    | Object doesn't exist                                         |
| 4    | Object already exists                                        |
| 5    | Object is already suspended                                  |
| 6    | Object is already unsuspended                                |
| 7    | Object can't be deleted because it is used by another object |
| 8    | Object cannot be created because of hosting package limits   |
| 9    | Wrong / Invalid password                                     |
| 10   | Object cannot be accessed by this user                       |
| 11   | Subsystem is disabled                                        |
| 12   | Configuration is broken                                      |
| 13   | Not enough disk space to complete the action                 |
| 14   | Server is too busy to complete the action                    |
| 15   | Connection failed. Host is unreachable                       |
| 16   | FTP server is not responding                                 |
| 17   | Database server is not responding                            |

## Astero Custom Codes (200+)

These codes are used by Astero's custom `a-*` scripts.

### System & Validation (200-201)

| Code | Description                                                 | Used By                                                   |
| ---- | ----------------------------------------------------------- | --------------------------------------------------------- |
| 200  | System capability check failed (web/SSL system disabled)    | `a-generate-ssl-certificate`, `a-install-ssl-certificate` |
| 201  | Object validation failed (user/domain invalid or suspended) | `a-generate-ssl-certificate`, `a-install-ssl-certificate` |

### SSL Certificate Operations (202-203)

| Code | Description                                | Used By                                                   |
| ---- | ------------------------------------------ | --------------------------------------------------------- |
| 202  | Certificate generation/decode/write failed | `a-generate-ssl-certificate`, `a-install-ssl-certificate` |
| 203  | Failed to apply or enforce SSL certificate | `a-generate-ssl-certificate`, `a-install-ssl-certificate` |

### Release & Version Management (204-206, 218)

| Code | Description                                            | Used By                                                                                  |
| ---- | ------------------------------------------------------ | ---------------------------------------------------------------------------------------- |
| 204  | Releases directory not found                           | `a-list-releases`, `a-update-astero`, `a-revert-astero-updates`                          |
| 205  | No application release versions found                  | `a-install-astero`                                                                       |
| 206  | File/directory operation failed (create/write/symlink) | Generic                                                                                  |
| 218  | Target version not found locally                       | `a-prepare-astero`, `a-update-astero`, `a-revert-astero-updates`, `a-set-active-release` |

### Dependencies & External Services (207-209, 211)

| Code | Description                                              | Used By                        |
| ---- | -------------------------------------------------------- | ------------------------------ |
| 207  | Required dependency missing (curl, jq, wget, unzip, php) | All scripts with external deps |
| 208  | API connection to release server failed                  | `a-sync-releases`              |
| 209  | Failed to parse API response                             | `a-sync-releases`              |
| 211  | Application file download failed                         | `a-sync-releases`              |

### Directory & File Operations (210, 212-213)

| Code | Description                                    | Used By                                                   |
| ---- | ---------------------------------------------- | --------------------------------------------------------- |
| 210  | Directory operation failed (create/chdir/copy) | `a-prepare-astero`, `a-update-astero`, `a-install-astero` |
| 212  | Application archive extraction failed          | `a-prepare-astero`, `a-update-astero`                     |
| 213  | File/directory ownership or move failed        | `a-prepare-astero`                                        |

### Application Installation (214-217)

| Code | Description                     | Used By                                                          |
| ---- | ------------------------------- | ---------------------------------------------------------------- |
| 214  | Invalid or incomplete JSON data | `a-install-astero`                                               |
| 215  | Composer install failed         | `a-update-astero`, `a-revert-astero-updates`                     |
| 216  | Artisan install/update failed   | `a-install-astero`, `a-update-astero`, `a-revert-astero-updates` |
| 217  | Invalid application type        | `a-revert-astero-updates`, `a-revert-installation-step`          |

### Revert Operations (219)

| Code | Description                      | Used By                      |
| ---- | -------------------------------- | ---------------------------- |
| 219  | Generic revert operation failure | `a-revert-installation-step` |

## Scripts Using Each Code

### a-create-web-domain

- 3: User does not exist
- 4: Domain already exists
- 220: Failed to create domain
- 221: Failed to set web template
- 222: Failed to set backend template

### a-generate-ssl-certificate

- 1: Invalid arguments
- 200: Web/SSL system disabled
- 201: User/domain validation failed
- 202: Certificate generation failed
- 203: Failed to apply certificate

### a-install-ssl-certificate

- 1: Invalid arguments
- 200: Web/SSL system disabled
- 201: User/domain validation failed
- 202: Failed to write/decode/validate certificate
- 203: Failed to install SSL

### a-sync-releases

- 1: Invalid arguments
- 207: Missing curl/jq/wget
- 208: API connection failed
- 209: Failed to parse API response
- 210: Failed to create directory
- 211: Download failed

### a-prepare-astero

- 1: Invalid arguments
- 3: User/domain not found
- 207: Missing unzip/sed
- 210: Directory operation failed
- 212: Archive extraction failed
- 213: Ownership/move failed
- 218: No local release available

### a-install-astero

- 1: Invalid arguments
- 3: User/domain not found
- 205: No release versions found
- 207: Missing php/jq
- 210: Directory operation failed
- 214: Invalid JSON data
- 216: Artisan install failed

### a-update-astero

- 1: Invalid arguments
- 3: User/domain not found
- 204: Releases directory missing
- 207: Missing dependencies
- 210: Directory operation failed
- 212: Archive extraction failed
- 216: Artisan update failed
- 218: Version not found locally

### a-revert-astero-updates

- 1: Invalid arguments
- 3: User/domain not found
- 204: Releases directory missing
- 207: Missing dependency
- 210: Directory operation failed
- 215: Composer install failed
- 216: Artisan revert failed
- 217: Invalid app type
- 218: Target version not found

### a-revert-installation-step

- 1: Invalid arguments
- 3: User/domain not found
- 217: Invalid command type
- 219: Revert operation failed

### a-set-active-release

- 1: Invalid arguments
- 204: Releases directory missing
- 210: Failed to update symlink
- 218: Version not found locally

### a-list-releases

- 204: Releases directory missing

## Adding New Error Codes

When adding new error codes:

1. Use codes 220+ for new functionality
2. Keep codes grouped by category
3. Update this file
4. Update `HestiaClient::getResponseCodeMap()` in PHP
5. Update `get_error_message()` in `a-exec` if needed

## Related Files

- `/usr/local/hestia/bin/a-exec` - Error message resolution
- `modules/Platform/app/Libs/HestiaClient.php` - PHP error code map
- `/usr/local/hestia/data/astero/logs/astero-scripts.log` - Error logs
