/**
 * Stub analytics/telemetry — belum ada backend consumer (lihat Temuan Penting
 * spec redesign UI Dompet). Titik panggil sudah disiapkan di seluruh UI supaya
 * saat provider analytics dipasang, hanya implementasi fungsi ini yang perlu diisi.
 */
export function trackEvent(name, payload = {}) {
  console.debug('[analytics]', name, payload)
}
