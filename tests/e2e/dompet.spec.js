import { test, expect } from '@playwright/test'

// Butuh user Monexa yang sudah subscribed & onboarded (route dompet.* pakai middleware
// auth,subscribed,onboarded). Set kredensial lewat env var sebelum menjalankan `npm run test:e2e`.
const EMAIL = process.env.E2E_TEST_EMAIL
const PASSWORD = process.env.E2E_TEST_PASSWORD

test.beforeEach(async ({ page }) => {
  test.skip(!EMAIL || !PASSWORD, 'Set E2E_TEST_EMAIL dan E2E_TEST_PASSWORD untuk menjalankan e2e Dompet.')

  await page.goto('/login')
  await page.getByPlaceholder('kamu@email.com').fill(EMAIL)
  await page.getByPlaceholder('••••••••').fill(PASSWORD)
  await page.getByRole('button', { name: 'Masuk' }).click()
  await page.waitForURL((url) => !url.pathname.startsWith('/login'))
})

const VIEWPORTS = [
  { name: 'mobile', width: 375, height: 812 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'desktop', width: 1280, height: 800 },
]

for (const viewport of VIEWPORTS) {
  test(`halaman /dompet render tanpa error console di viewport ${viewport.name}`, async ({ page }) => {
    const consoleErrors = []
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text())
    })

    await page.setViewportSize({ width: viewport.width, height: viewport.height })
    await page.goto('/dompet')

    await expect(page.locator('.hero-saldo-amount')).toBeVisible()
    await expect(page.locator('.tab-row')).toBeVisible()

    if (viewport.width >= 481) {
      await expect(page.locator('.quick-actions')).toBeVisible()
    } else {
      await expect(page.locator('.fab')).toBeVisible()
    }

    expect(consoleErrors).toEqual([])
  })
}

test('ganti tema lewat query param ?theme= mengubah document.documentElement.dataset.theme', async ({ page }) => {
  // Nilai valid persis 'blue'|'green'|'dark' (lihat resources/js/Composables/useTheme.js
  // VALID_THEMES) — nilai lain fallback ke 'blue'.
  await page.goto('/dompet?theme=green')
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'green')

  await page.goto('/dompet?theme=dark')
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
})
