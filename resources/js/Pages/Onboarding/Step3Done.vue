<template>
  <div class="done-page">
    <div class="done-content">
      <div class="done-emoji">🎉</div>
      <h1 class="done-title">Siap mencatat keuanganmu!</h1>
      <p class="done-sub">
        Trial 7 hari kamu sudah aktif.<br>
        Nomor bot WA kamu sudah siap dipakai!
      </p>

      <!-- Bot Gateway Card — info paling penting -->
      <div v-if="bot_gateway" class="bot-card">
        <div class="bot-card-label">📱 Nomor Bot WA Kamu</div>
        <div class="bot-number">{{ bot_gateway.phone_number }}</div>
        <div class="bot-steps">
          <div class="bot-step">
            <span class="bs-num">1</span>
            <span>Simpan nomor ini di kontak HP kamu sebagai <strong>"CatatCuan Bot"</strong></span>
          </div>
          <div class="bot-step">
            <span class="bs-num">2</span>
            <span>Kirim pesan ke nomor itu untuk mulai catat, misal: <em>"Makan 35rb"</em></span>
          </div>
          <div class="bot-step">
            <span class="bs-num">3</span>
            <span>Bot akan balas otomatis ✅</span>
          </div>
        </div>
        <div class="bot-note">
          ✉️ Cek WhatsApp kamu — kami sudah kirimkan nomor ini beserta panduan lengkapnya!
        </div>
      </div>

      <!-- Belum dapat gateway -->
      <div v-else class="no-bot-card">
        <div style="font-size:24px;margin-bottom:6px;">📵</div>
        <div style="font-size:13px;color:var(--text-secondary);">
          Nomor bot sedang disiapkan. Cek halaman Akun dalam beberapa saat.
        </div>
      </div>

      <!-- Fitur lainnya -->
      <div class="done-features">
        <div class="feat-item"><span>💸</span><span>Catat masuk &amp; keluar via WA atau web</span></div>
        <div class="feat-item"><span>🏦</span><span>Set goals tabungan</span></div>
        <div class="feat-item"><span>🧾</span><span>Kelola tagihan &amp; reminder otomatis</span></div>
        <div class="feat-item"><span>📊</span><span>Laporan bulanan dikirim ke WA</span></div>
      </div>

      <Link :href="route('dashboard')"
        class="btn-cc"
        style="display:block;text-align:center;text-decoration:none;margin-top:20px;">
        Buka Dashboard 🚀
      </Link>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
defineProps({ bot_gateway: Object })
</script>

<style scoped>
.done-page { min-height:100vh;background:var(--background);display:flex;align-items:center;justify-content:center;padding:24px; }
.done-content { max-width:400px;width:100%; }
.done-emoji { font-size:64px;margin-bottom:16px;text-align:center; }
.done-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:800;letter-spacing:-.02em;margin-bottom:8px;text-align:center; }
.done-sub { font-size:14px;color:var(--text-secondary);line-height:1.7;margin-bottom:20px;text-align:center; }

/* Bot card */
.bot-card { background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);border-radius:var(--radius-xl);padding:20px;margin-bottom:16px;color:white; }
.bot-card-label { font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:8px; }
.bot-number { font-family:"JetBrains Mono",monospace;font-size:20px;font-weight:700;color:white;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,.1); }
.bot-steps { display:flex;flex-direction:column;gap:10px;margin-bottom:14px; }
.bot-step { display:flex;gap:10px;align-items:flex-start;font-size:12px;color:rgba(255,255,255,.75);line-height:1.6; }
.bs-num { width:20px;height:20px;border-radius:50%;background:rgba(255,255,255,.15);color:white;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px; }
.bot-note { font-size:11px;color:rgba(255,255,255,.4);background:rgba(255,255,255,.07);border-radius:8px;padding:10px 12px;line-height:1.6; }

.no-bot-card { background:var(--surface);border-radius:var(--radius-xl);padding:20px;text-align:center;border:2px dashed var(--border);margin-bottom:16px; }

/* Features */
.done-features { background:var(--surface);border-radius:var(--radius-xl);padding:16px 18px;box-shadow:var(--shadow-card);margin-bottom:4px; }
.feat-item { display:flex;align-items:center;gap:10px;font-size:13px;padding:8px 0;border-bottom:1px solid var(--background); }
.feat-item:last-child { border-bottom:none; }
</style>
