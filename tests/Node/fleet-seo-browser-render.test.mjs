import assert from 'node:assert/strict';
import test from 'node:test';

import { summarizeAccessibilityViolations, summarizeRenderedPage } from '../../scripts/fleet-seo-browser-render.mjs';

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

test('summarizeAccessibilityViolations maps axe violations to bounded Fleet evidence counts', () => {
    const result = summarizeAccessibilityViolations([
        { id: 'color-contrast', nodes: [{}, {}] },
        { id: 'label', nodes: [{}] },
        { id: 'button-name', nodes: [{}] },
        { id: 'link-name', nodes: [{}, {}, {}] },
        { id: 'duplicate-id', nodes: [{}] },
        { id: 'aria-valid-attr', nodes: [{}] },
        { id: 'heading-order', nodes: [{}] },
        { id: 'html-has-lang', nodes: [{}] },
        { id: 'some-other-rule', nodes: [{}] },
    ]);

    assert.equal(result.color_contrast_violation_count, 2);
    assert.equal(result.form_label_missing_count, 1);
    assert.equal(result.button_without_name_count, 1);
    assert.equal(result.link_without_name_count, 3);
    assert.equal(result.duplicate_id_count, 1);
    assert.equal(result.aria_invalid_count, 1);
    assert.equal(result.heading_order_issue_count, 1);
    assert.equal(result.document_language_issue_count, 1);
    assert.equal(result.axe_violation_count, 12);
    assert.equal(result.axe_rule_ids.length, 9);
});

test('summarizeAccessibilityViolations emits zero counts for a clean axe result', () => {
    const result = summarizeAccessibilityViolations([]);

    assert.equal(result.color_contrast_violation_count, 0);
    assert.equal(result.form_label_missing_count, 0);
    assert.equal(result.button_without_name_count, 0);
    assert.equal(result.link_without_name_count, 0);
    assert.equal(result.duplicate_id_count, 0);
    assert.equal(result.aria_invalid_count, 0);
    assert.equal(result.heading_order_issue_count, 0);
    assert.equal(result.document_language_issue_count, 0);
    assert.equal(result.axe_violation_count, 0);
    assert.deepEqual(result.axe_rule_ids, []);
});
