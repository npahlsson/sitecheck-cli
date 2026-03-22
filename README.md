# Sitecheck CLI

A Laravel Zero-based CLI tool for checking website health, DNS propagation, and SEO-related information.

## Installation

### Requirements
- PHP 8.2+
- Composer

### Setup

```bash
git clone https://github.com/YOUR_USERNAME/sitecheck-cli.git
cd sitecheck-cli
composer install
```

### Build (optional)

To build a standalone executable:

```bash
./vendor/bin/pest        # Run tests
./vendor/bin/pint        # Format code
./sitecheck app:build    # Build executable
```

## Commands

### urlinfo

Check URL status, robots.txt allowance, canonical tag, and TTFB (Time To First Byte).

```bash
# Basic usage
./sitecheck urlinfo https://example.com

# With staging mode (inverts robots.txt colors)
./sitecheck urlinfo https://example.com --staging

# URL without protocol (auto-adds https://)
./sitecheck urlinfo example.com
```

**Output:**
```
+-------------+-------------------+------------+-----------+-----------------------------+
| HTTP Status | Friendly Name     | robots.txt | Canonical | Server response time (TTFB) |
+-------------+-------------------+------------+-----------+-----------------------------+
| 200         | OK                | ALLOWED    | YES       | 85 ms                      |
+-------------+-------------------+------------+-----------+-----------------------------+
```

**Columns:**
- **HTTP Status** - Color-coded: green (2xx), yellow (3xx), red (4xx/5xx)
- **Friendly Name** - Human-readable status (OK, Not Found, etc.)
- **robots.txt** - ALLOWED/DISALLOWED based on robots.txt rules for current URL
- **Canonical** - YES if canonical tag points to same domain, NO if pointing elsewhere
- **Server response time (TTFB)** - Color-coded: green (<500ms), yellow (<1000ms), red (>1000ms)

### httpstatus

Check HTTP status codes and redirect chains for a URL.

```bash
./sitecheck httpstatus https://example.com
```

**Output:**
```
+-------------+-------------------+-------------------------+-------------------------+---------------+
| HTTP Status | Friendly Name     | URL                     | Redirect to             | X-Redirect-By |
+-------------+-------------------+-------------------------+-------------------------+---------------+
| 301         | Moved Permanently | https://example.com      | https://www.example.com/ | -             |
| 200         | OK                | https://www.example.com/ | -                       | -             |
+-------------+-------------------+-------------------------+-------------------------+---------------+
```

### domaininfo

Get domain information including IP, hosting, registrar, and nameservers.

```bash
# Basic usage
./sitecheck domaininfo example.com

# With DNS propagation check
./sitecheck domaininfo example.com --expected 93.184.216.34
```

**Output:**
```
example.com
──────────────────────────────────────────────────

+----------------------+-----------------------------+------------------+--------------------------------+
| IP                   | Hosting                     | Registrar        | Namnservrar                    |
+----------------------+-----------------------------+------------------+--------------------------------+
| 93.184.216.34        | Cloudflare, Inc. · City     | Nominet          | a.iana-servers.net (199.43.135.53)|
+----------------------+-----------------------------+------------------+--------------------------------+
```

**With DNS Propagation:**
```
DNS Propagation
──────────────────────────────────────────────────
+------------------------+----------------+-------+
| DNS Server             | A Record       | Match |
+------------------------+----------------+-------+
| Google (8.8.8.8)     | 93.184.216.34  | ✓     |
| Cloudflare (1.1.1.1)  | 93.184.216.34  | ✓     |
| AdGuard (94.140.14.14)| 93.184.216.34  | ✓     |
+------------------------+----------------+-------+
```

**Columns:**
- **IP** - Primary A record with reverse DNS in parentheses
- **Hosting** - Provider name, city, and country
- **Registrar** - Domain registrar
- **Namnservrar** - Nameservers with IP addresses

## Testing

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/UrlInfoCommandTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

## Code Quality

```bash
# Format code with Laravel Pint
./vendor/bin/pint
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests: `./vendor/bin/pest`
5. Format code: `./vendor/bin/pint`
6. Submit a pull request

## License

MIT License
