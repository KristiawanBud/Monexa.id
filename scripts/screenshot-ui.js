import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(__dirname, '..');

function readEnvUrl() {
    try {
        const envPath = path.join(projectRoot, '.env');
        const content = fs.readFileSync(envPath, 'utf8');
        const match = content.match(/^APP_URL=(.*)$/m);
        if (match) {
            return match[1].trim();
        }
    } catch {
        // .env tidak terbaca, pakai fallback
    }
    return null;
}

function trimTrailingSlash(url) {
    return url.replace(/\/+$/, '');
}

const route = process.argv[2] || '/';

const baseUrl = trimTrailingSlash(
    process.env.SCREENSHOT_BASE_URL || readEnvUrl() || 'http://127.0.0.1:8000'
);
const email = process.env.SCREENSHOT_EMAIL || 'admin@catatcuan.id';
const password = process.env.SCREENSHOT_PASSWORD || 'admin123';
const outputDir = process.env.SCREENSHOT_OUTPUT_DIR
    ? path.resolve(projectRoot, process.env.SCREENSHOT_OUTPUT_DIR)
    : path.join(projectRoot, 'public/storage/screenshots');
const publicBaseUrl = trimTrailingSlash(process.env.SCREENSHOT_PUBLIC_BASE_URL || baseUrl);

async function main() {
    console.error(`Base URL: ${baseUrl}`);
    console.error(`Route: ${route}`);
    console.error(`Login sebagai: ${email}`);

    console.error('Launching browser...');
    const browser = await chromium.launch({ headless: true });

    try {
        const page = await browser.newPage();

        console.error('Membuka halaman login...');
        await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle', timeout: 30000 });

        console.error('Mengisi form login...');
        await page.fill('input[type="email"]', email);
        await page.fill('input[type="password"]', password);

        console.error('Submit form login...');
        await Promise.all([
            page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15000 }).catch(() => {}),
            page.click('button[type="submit"]'),
        ]);

        const currentUrl = new URL(page.url());
        if (currentUrl.pathname.startsWith('/login')) {
            throw new Error('Login gagal: masih berada di halaman /login setelah submit');
        }

        console.error('Login berhasil.');

        if (route && route !== currentUrl.pathname) {
            console.error(`Navigasi ke route: ${route}`);
            await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle', timeout: 30000 });
        }

        const filename = `ui-${Date.now()}.png`;

        fs.mkdirSync(outputDir, { recursive: true });

        const outputPath = path.join(outputDir, filename);
        console.error(`Mengambil screenshot ke: ${outputPath}`);
        await page.screenshot({ path: outputPath, fullPage: true });

        const publicUrl = `${publicBaseUrl}/storage/screenshots/${filename}`;
        console.log(publicUrl);
        process.exitCode = 0;
    } finally {
        await browser.close();
    }
}

main().catch((error) => {
    console.error(`Error: ${error.message}`);
    process.exitCode = 1;
});
