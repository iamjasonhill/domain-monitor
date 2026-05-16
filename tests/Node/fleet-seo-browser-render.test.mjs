import assert from 'node:assert/strict';
import test from 'node:test';

import { summarizeRenderedPage } from '../../scripts/fleet-seo-browser-render.mjs';

test('summarizeRenderedPage emits bounded browser-render evidence', async () => {
    const page = {
        url: () => 'https://example.test/final',
        title: async () => 'Rendered Example',
        viewportSize: () => ({ width: 390, height: 844 }),
        evaluate: async () => ({
            textSample: 'Rendered Example '.repeat(100),
            bodyTextLength: 1800,
            contentWidth: 420,
            h1Count: 1,
            htmlLang: 'en',
            mainLandmarkCount: 1,
            navLandmarkCount: 1,
            linkWithoutNameCount: 2,
        }),
    };

    const result = await summarizeRenderedPage(page, {
        requestedUrl: 'https://example.test/',
        consoleErrors: [
            'ReferenceError: '.concat('x'.repeat(300)),
            'TypeError: failed',
            '',
        ],
    });

    assert.equal(result.available, true);
    assert.equal(result.url, 'https://example.test/');
    assert.equal(result.final_url, 'https://example.test/final');
    assert.equal(result.title, 'Rendered Example');
    assert.equal(result.viewport.width, 390);
    assert.equal(result.content_width, 420);
    assert.equal(result.h1_count, 1);
    assert.equal(result.html_lang, 'en');
    assert.equal(result.link_without_name_count, 2);
    assert.equal(result.console_errors.length, 2);
    assert.ok(result.console_errors[0].length <= 240);
    assert.ok(result.text_sample.length <= 500);
    assert.equal(Object.hasOwn(result, 'raw_html'), false);
    assert.equal(Object.hasOwn(result, 'screenshot_path'), false);
});
