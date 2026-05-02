# Live SEO Verification Packet

`domain-monitor` owns live URL and domain verification for Search Intelligence
handoffs. It does not own Search Console interpretation, content strategy, site
repo writes, Fleet gates, or PR creation.

## Endpoint

`GET /api/web-properties/{slug}/live-seo-verification`

Authentication uses the normal Domain Monitor API bearer token.

## Packet Input

Supported query fields:

- `measurement_key`: upstream MM-Google or Search Intelligence measurement key
- `evidence_ref`: upstream evidence reference, artifact path, or note
- `site_key`: upstream site or property key
- `url` or `target_url`: exact live URL to verify
- `url_pattern` plus `sample_url`: pattern label represented by one sample URL
- `expected_canonical`: absolute canonical URL expected on the live page
- `owning_repo`: expected site repository, when known
- `reason`: plain-English reason for this verification
- `requested_checks`: comma-separated checks or repeated array values
- `timeout`: request timeout, 1 to 15 seconds

## Packet Output

The response includes:

- `verification_key`
- `checked_at`
- `target` metadata copied from the input
- `status`
- `verdict`
- `summary`
- `evidence.page` for requested URL, final URL, status, redirects, and content type
- `evidence.canonical` for discovered and expected canonical comparison
- `evidence.basic_meta` for page title and meta description when present
- `evidence.indexability` for meta robots, X-Robots-Tag, and noindex signals
- `evidence.fetchability` for blocked signals and robots.txt evidence
- `evidence.links` for a small page-local link sample
- `evidence.evidence_limits`

Verdicts are limited to:

- `passes_live_verification`
- `needs_attention`
- `inconclusive`

## Moving Again Pilot Shape

```text
/api/web-properties/moving-again/live-seo-verification
  ?measurement_key=mm-google:search-intelligence:moving-again-pilot
  &evidence_ref=MM-Google/search-intelligence/opportunities/moving-again
  &site_key=moving-again
  &target_url=https://movingagain.com.au/example
  &expected_canonical=https://movingagain.com.au/example
  &owning_repo=iamjasonhill/moving-again
  &reason=Search%20Intelligence%20pilot%20URL%20live-truth%20check
  &requested_checks=http,redirects,canonical,robots,indexability,title_meta
```

This packet separates live truth from MM-Google measurement truth: upstream
systems provide the measurement key and reason, while `domain-monitor` reports
only what it can fetch from the live URL.
