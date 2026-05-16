#!/usr/bin/env node

import { chromium } from 'playwright';
import process from 'node:process';

const DEFAULT_VIEWPORT = { width: 390, height: 844 };
const NAVIGATION_TIMEOUT_MS = 20000;

export async function summarizeRenderedPage(page, { requestedUrl, consoleErrors }) {
    const rendered = await page.evaluate(() => {
        const visibleText = document.body?.innerText?.replace(/\s+/g, ' ').trim() ?? '';
        const accessibleName = (element) => {
            const labelledBy = element.getAttribute('aria-labelledby');
            if (labelledBy) {
                return labelledBy
                    .split(/\s+/)
                    .map((id) => document.getElementById(id)?.innerText ?? '')
                    .join(' ')
                    .trim();
            }

            return (
                element.getAttribute('aria-label')
                ?? element.getAttribute('title')
                ?? element.innerText
                ?? element.textContent
                ?? ''
            ).trim();
        };

        return {
            textSample: visibleText.slice(0, 500),
            bodyTextLength: visibleText.length,
            contentWidth: Math.ceil(Math.max(
                document.documentElement?.scrollWidth ?? 0,
                document.body?.scrollWidth ?? 0,
                document.documentElement?.clientWidth ?? 0,
            )),
            h1Count: document.querySelectorAll('h1').length,
            htmlLang: document.documentElement?.getAttribute('lang') ?? '',
            mainLandmarkCount: document.querySelectorAll('main, [role="main"]').length,
            navLandmarkCount: document.querySelectorAll('nav, [role="navigation"]').length,
            linkWithoutNameCount: Array.from(document.querySelectorAll('a[href]'))
                .filter((link) => accessibleName(link) === '').length,
        };
    });

    return {
        available: true,
        url: requestedUrl,
        final_url: page.url(),
        title: await page.title(),
        text_sample: String(rendered.textSample ?? '').slice(0, 500),
        body_text_length: rendered.bodyTextLength,
        console_errors: boundedConsoleErrors(consoleErrors),
        viewport: page.viewportSize() ?? DEFAULT_VIEWPORT,
        content_width: rendered.contentWidth,
        h1_count: rendered.h1Count,
        html_lang: rendered.htmlLang,
        main_landmark_count: rendered.mainLandmarkCount,
        nav_landmark_count: rendered.navLandmarkCount,
        link_without_name_count: rendered.linkWithoutNameCount,
    };
}

export function boundedConsoleErrors(errors) {
    return errors
        .filter((error) => typeof error === 'string' && error.trim() !== '')
        .map((error) => error.trim().slice(0, 240))
        .slice(0, 10);
}

function unavailable(url, reason) {
    return {
        available: false,
        url,
        reason,
    };
}

async function render(url) {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: DEFAULT_VIEWPORT,
        userAgent: 'DomainMonitor/1.0 FleetTechnicalSeoBrowserRender',
    });
    const page = await context.newPage();
    const consoleErrors = [];

    page.on('console', (message) => {
        if (message.type() === 'error') {
            consoleErrors.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        consoleErrors.push(error.message);
    });

    try {
        await page.goto(url, {
            waitUntil: 'networkidle',
            timeout: NAVIGATION_TIMEOUT_MS,
        });

        return await summarizeRenderedPage(page, { requestedUrl: url, consoleErrors });
    } finally {
        await browser.close();
    }
}

async function main() {
    const url = process.env.FLEET_SEO_RENDER_URL;

    if (typeof url !== 'string' || url.trim() === '') {
        console.log(JSON.stringify(unavailable('', 'FLEET_SEO_RENDER_URL is required.')));
        process.exitCode = 1;

        return;
    }

    try {
        console.log(JSON.stringify(await render(url.trim())));
    } catch (error) {
        console.log(JSON.stringify(unavailable(url.trim(), error instanceof Error ? error.message : String(error))));
        process.exitCode = 1;
    }
}

if (import.meta.url === `file://${process.argv[1]}`) {
    await main();
}
