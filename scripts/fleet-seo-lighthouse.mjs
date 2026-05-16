#!/usr/bin/env node

import lighthouse from 'lighthouse';
import * as chromeLauncher from 'chrome-launcher';
import process from 'node:process';

const LIGHTHOUSE_TIMEOUT_MS = 60000;
const ANALYTICS_HOST_PATTERNS = [
    'googletagmanager.com',
    'google-analytics.com',
    'analytics.google.com',
    'matomo',
    'plausible.io',
    'clarity.ms',
    'hotjar.com',
];

export function summarizeLighthouseResult(lhr) {
    const audits = lhr.audits ?? {};
    const categories = lhr.categories ?? {};
    const renderBlockingResources = resourceUrlsFromAudit(audits['render-blocking-resources'])
        .filter((url) => ANALYTICS_HOST_PATTERNS.some((pattern) => url.toLowerCase().includes(pattern)))
        .slice(0, 10);

    return {
        available: true,
        url: lhr.requestedUrl ?? lhr.finalUrl ?? '',
        final_url: lhr.finalUrl ?? lhr.requestedUrl ?? '',
        scores: {
            performance: score(categories.performance),
            accessibility: score(categories.accessibility),
            best_practices: score(categories['best-practices']),
            seo: score(categories.seo),
        },
        metrics: {
            lcp_ms: numericAuditValue(audits['largest-contentful-paint']),
            fcp_ms: numericAuditValue(audits['first-contentful-paint']),
            cls: numericAuditValue(audits['cumulative-layout-shift']),
            tbt_ms: numericAuditValue(audits['total-blocking-time']),
        },
        analytics_blocking_first_paint: renderBlockingResources.length > 0,
        analytics_blocking_resources: renderBlockingResources,
        threshold_source: 'Fleet technical SEO catalog',
    };
}

function score(category) {
    return typeof category?.score === 'number' ? category.score : null;
}

function numericAuditValue(audit) {
    return typeof audit?.numericValue === 'number' ? audit.numericValue : null;
}

function resourceUrlsFromAudit(audit) {
    const items = audit?.details?.items;
    if (!Array.isArray(items)) {
        return [];
    }

    return items
        .map((item) => typeof item?.url === 'string' ? item.url : null)
        .filter((url) => typeof url === 'string' && url.trim() !== '')
        .map((url) => url.slice(0, 240));
}

function unavailable(url, reason) {
    return {
        available: false,
        url,
        reason,
    };
}

async function runLighthouse(url) {
    const chrome = await chromeLauncher.launch({
        chromeFlags: ['--headless=new', '--no-sandbox', '--disable-gpu'],
    });

    try {
        const result = await lighthouse(url, {
            port: chrome.port,
            output: 'json',
            logLevel: 'error',
            onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
            maxWaitForLoad: LIGHTHOUSE_TIMEOUT_MS,
            throttlingMethod: 'provided',
            formFactor: 'mobile',
            screenEmulation: {
                mobile: true,
                width: 390,
                height: 844,
                deviceScaleFactor: 1,
                disabled: false,
            },
        });

        return summarizeLighthouseResult(result?.lhr ?? {});
    } finally {
        await chrome.kill();
    }
}

async function main() {
    const url = process.env.FLEET_SEO_LIGHTHOUSE_URL;

    if (typeof url !== 'string' || url.trim() === '') {
        console.log(JSON.stringify(unavailable('', 'FLEET_SEO_LIGHTHOUSE_URL is required.')));
        process.exitCode = 1;

        return;
    }

    try {
        console.log(JSON.stringify(await runLighthouse(url.trim())));
    } catch (error) {
        console.log(JSON.stringify(unavailable(url.trim(), error instanceof Error ? error.message : String(error))));
        process.exitCode = 1;
    }
}

if (import.meta.url === `file://${process.argv[1]}`) {
    await main();
}
