# DNS Search Features

## Overview

Enhanced search functionality to search across DNS records for all domains. This allows you to find domains based on their DNS configuration, such as finding all domains using a specific MX server.

## Features

### 1. Search Modes

Three search modes available:

- **All** (default): Searches both domain fields (name, registrar, hosting, notes) AND DNS records
- **Domain Only**: Searches only domain fields, excludes DNS records
- **DNS Only**: Searches only DNS records (host, type, value)

### 2. DNS-Specific Filters

When using "DNS Only" or "All" search mode, additional filters are available:

- **DNS Type**: Filter by specific record type (A, AAAA, CNAME, MX, TXT, NS, SRV, CAA, SPF)
- **Host/Subdomain**: Filter by host/subdomain (e.g., `@` for root, `www`, `mail`)

### 3. Search Examples

#### Find all domains using MXroad.com for email:
1. Set search mode to "DNS Only"
2. Select "MX" from DNS Type dropdown
3. Enter "mxroad.com" in the search box
4. Results: All domains with MX records pointing to mxroad.com

#### Find all domains with a specific A record:
1. Set search mode to "DNS Only"
2. Select "A" from DNS Type dropdown
3. Enter the IP address in the search box
4. Results: All domains with that IP address

#### Find all domains with a CNAME to a specific domain:
1. Set search mode to "DNS Only"
2. Select "CNAME" from DNS Type dropdown
3. Enter the target domain in the search box
4. Results: All domains with CNAME records pointing to that domain

#### Find all domains with SPF records:
1. Set search mode to "DNS Only"
2. Select "TXT" from DNS Type dropdown (SPF is stored as TXT)
3. Enter "v=spf1" in the search box
4. Results: All domains with SPF records

#### Find all domains with a specific subdomain:
1. Set search mode to "DNS Only"
2. Enter the subdomain name in "Host/Subdomain" field (e.g., "www")
3. Results: All domains that have DNS records for that subdomain

## Technical Details

### Database Queries

The search uses efficient `whereHas` queries to search DNS records:
- Searches across `host`, `type`, and `value` fields
- Case-insensitive search (works with PostgreSQL and MySQL)
- Supports partial matching

### Performance

- Minimum 2 characters required for search (prevents performance issues)
- Uses indexed columns where possible
- Efficient joins to avoid N+1 queries

## Usage Tips

1. **Start broad, then narrow**: Use "All" mode first, then switch to "DNS Only" if needed
2. **Combine filters**: Use DNS Type + Host filters together for precise searches
3. **Search value patterns**: You can search for partial values (e.g., "mxroad" will match "mxroad.com")
4. **Clear filters**: Use the "Clear Filters" button to reset all search options

## Examples in Practice

### Example 1: Audit Email Configuration
**Goal**: Find all domains NOT using the company's email provider

1. Search mode: "DNS Only"
2. DNS Type: "MX"
3. Search: "company-email.com"
4. Review results - domains NOT in the list need to be updated

### Example 2: Find Domains Using Old CDN
**Goal**: Find all domains still pointing to old CDN

1. Search mode: "DNS Only"
2. DNS Type: "CNAME"
3. Search: "old-cdn.example.com"
4. Results show all domains that need CDN migration

### Example 3: Security Audit
**Goal**: Find domains missing SPF records

1. Search mode: "DNS Only"
2. DNS Type: "TXT"
3. Search: "v=spf1"
4. Compare with total domain list to find missing SPF records
