import assert from 'node:assert/strict';
import test from 'node:test';

import { summarizeLighthouseResult } from '../../scripts/fleet-seo-lighthouse.mjs';

test('summarizeLighthouseResult emits bounded lab evidence', () => {
    const result = summarizeLighthouseResult({
        requestedUrl: 'https://example.test/',
        finalUrl: 'https://example.test/final',
        categories: {
            performance: { score: 0.91 },
            accessibility: { score: 0.98 },
            'best-practices': { score: 0.87 },
            seo: { score: 1 },
        },
        audits: {
            'largest-contentful-paint': { numericValue: 1800 },
            'first-contentful-paint': { numericValue: 900 },
            'cumulative-layout-shift': { numericValue: 0.03 },
            'total-blocking-time': { numericValue: 80 },
            'render-blocking-resources': {
                details: {
                    items: [
                        { url: 'https://www.googletagmanager.com/gtm.js?id=G-123' },
                        { url: 'https://example.test/app.css' },
                    ],
                },
            },
        },
    });

    assert.equal(result.available, true);
    assert.equal(result.url, 'https://example.test/');
    assert.equal(result.final_url, 'https://example.test/final');
    assert.equal(result.scores.performance, 0.91);
    assert.equal(result.scores.best_practices, 0.87);
    assert.equal(result.metrics.lcp_ms, 1800);
    assert.equal(result.metrics.fcp_ms, 900);
    assert.equal(result.metrics.cls, 0.03);
    assert.equal(result.metrics.tbt_ms, 80);
    assert.equal(result.analytics_blocking_first_paint, true);
    assert.deepEqual(result.analytics_blocking_resources, ['https://www.googletagmanager.com/gtm.js?id=G-123']);
    assert.equal(result.threshold_source, 'Fleet technical SEO catalog');
    assert.equal(Object.hasOwn(result, 'audits'), false);
    assert.equal(Object.hasOwn(result, 'categories'), false);
});

test('summarizeLighthouseResult handles absent metric evidence conservatively', () => {
    const result = summarizeLighthouseResult({
        requestedUrl: 'https://example.test/',
        finalUrl: 'https://example.test/',
        categories: {},
        audits: {},
    });

    assert.equal(result.available, true);
    assert.equal(result.metrics.lcp_ms, null);
    assert.equal(result.analytics_blocking_first_paint, false);
    assert.deepEqual(result.analytics_blocking_resources, []);
});
