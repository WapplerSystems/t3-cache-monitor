# TYPO3 Extension: Cache Monitor

Monitor all registered TYPO3 caches with statistics on entry count, size and backend type.

## Features

- **Backend Module** (System > Cache Monitor): Overview of all caches with entries, size, backend type, groups and flush action per cache
- **Dashboard Widget**: Compact cache overview table for the TYPO3 Dashboard
- **CLI Command** `cache:stats`: Full cache statistics on the command line
- **Zabbix Monitoring Operation** `GetTypo3CacheStats`: Exposes per-cache entries/size for Zabbix via `wapplersystems/zabbix_client` (optional)

### Screenshots

#### Backend Module

![Backend Module](Documentation/Images/backend-module.png)

#### Dashboard Widget

![Dashboard Widget](Documentation/Images/dashboard-widget.png)

### Supported Cache Backends

| Backend | Entries | Size |
|---|---|---|
| FileBackend | yes | yes |
| SimpleFileBackend | yes | yes |
| Typo3DatabaseBackend | yes | yes |
| RedisBackend | yes | no |
| ApcuBackend | yes | no |
| NullBackend | 0 | 0 |

## Installation

```bash
composer require wapplersystems/cache-monitor
```

Then activate the extension:

```bash
vendor/bin/typo3 extension:setup
```

## Usage

### Backend Module

Navigate to **System > Cache Monitor** in the TYPO3 backend. Requires admin access.

### Dashboard Widget

Go to Dashboard, click **+ Add widget**, select **Cache Overview** from the System Info group.

### CLI

```bash
# Show all caches
vendor/bin/typo3 cache:stats

# Filter by group
vendor/bin/typo3 cache:stats --group=pages

# Sort by size (descending)
vendor/bin/typo3 cache:stats --sort=size

# Sort by entries (descending)
vendor/bin/typo3 cache:stats --sort=entries
```

Example output:

```
TYPO3 Cache Statistics
======================

+-------------------+----------------------+---------+----------+--------+
| Cache             | Backend              | Entries | Size     | Groups |
+-------------------+----------------------+---------+----------+--------+
| core              | SimpleFileBackend    | 12      | 911 KB   | system |
| pages             | Typo3DatabaseBackend | 3       | 35.8 KB  | pages  |
| proxy_responses   | FileBackend          | 1       | 4.77 KB  | all    |
| ...               |                      |         |          |        |
| Total (25 caches) |                      | 270     | 10.41 MB |        |
+-------------------+----------------------+---------+----------+--------+
```

## Zabbix Monitoring

If [`wapplersystems/zabbix_client`](https://github.com/WapplerSystems/zabbix_client) is installed alongside this extension, the operation `GetTypo3CacheStats` is automatically registered. Query it from Zabbix via the standard zabbix_client endpoint:

```
POST /zabbixclient/?operation=GetTypo3CacheStats
key=<TYPO3_CLIENT_KEY>
```

Response shape (truncated):

```json
{
  "status": true,
  "value": {
    "totals": {"caches": 25, "entries": 1234, "size_bytes": 10923456},
    "caches": [
      {"identifier": "proxy_responses", "backend": "FileBackend", "entries": 440, "size_bytes": 33518592, "groups": ["all"]},
      {"identifier": "pages", "backend": "Typo3DatabaseBackend", "entries": 51, "size_bytes": 4263936, "groups": ["pages"]}
    ]
  }
}
```

Use a low-level discovery rule (LLD) on `$.value.caches[*]` to auto-create per-cache items, then dependent items for entries/size.

If `zabbix_client` is not installed, the operation class is a no-op (soft dependency).

## Requirements

- TYPO3 v13
- PHP 8.1+
- Optional: `typo3/cms-dashboard` for the dashboard widget
- Optional: `wapplersystems/zabbix_client` for the Zabbix monitoring operation

## License

GPL-2.0-or-later
