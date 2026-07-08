<template>
  <div class="expired-page">
    <div class="expired-content">
      <div class="expired-icon">⏰</div>
      <h1 class="expired-title">Trial kamu sudah habis</h1>
      <p class="expired-sub">
        Upgrade ke plan berbayar untuk terus menggunakan CatatCuan.
        Semua data kamu aman dan tidak dihapus.
      </p>

      <div class="plan-cards">
        <!-- Monthly -->
        <div class="plan-card">
          <div class="plan-name">Monthly</div>
          <div class="plan-price">Rp 25.000 <span>/ bulan</span></div>
          <ul class="plan-features">
            <li>✅ Semua fitur aktif</li>
            <li>✅ Dompet tidak terbatas</li>
            <li>✅ Notifikasi WA</li>
            <li>✅ Export laporan</li>
          </ul>
          <a href="#" class="plan-btn">Pilih Monthly</a>
        </div>

        <!-- Yearly -->
        <div class="plan-card featured">
          <div class="plan-badge">Hemat 33%</div>
          <div class="plan-name">Yearly</div>
          <div class="plan-price">Rp 200.000 <span>/ tahun</span></div>
          <ul class="plan-features">
            <li>✅ Semua fitur Monthly</li>
            <li>✅ Multi-user (maks 3)</li>
            <li>✅ Prioritas support</li>
            <li>✅ Early access fitur baru</li>
          </ul>
          <a href="#" class="plan-btn featured">Pilih Yearly</a>
        </div>
      </div>

      <Link :href="route('logout')" method="post" as="button" class="logout-btn">
        Keluar dari akun
      </Link>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<style scoped>
.expired-page { min-height:100vh;background:var(--background);padding:24px;display:flex;align-items:center;justify-content:center; }
.expired-content { max-width:400px;width:100%;text-align:center; }
.expired-icon { font-size:60px;margin-bottom:16px; }
.expired-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:800;letter-spacing:-.02em;margin-bottom:10px; }
.expired-sub { font-size:14px;color:var(--text-secondary);line-height:1.7;margin-bottom:24px; }

.plan-cards { display:flex;gap:12px;margin-bottom:20px; }
.plan-card { flex:1;background:var(--surface);border-radius:var(--radius-xl);padding:16px;box-shadow:var(--shadow-card);text-align:left;border:2px solid var(--border);position:relative; }
.plan-card.featured { border-color:var(--text-primary); }
.plan-badge { position:absolute;top:-10px;right:12px;background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white;font-size:10px;font-weight:700;padding:3px 10px;border-radius:99px; }
.plan-name { font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;margin-bottom:4px; }
.plan-price { font-family:'Plus Jakarta Sans',sans-serif;font-size:20px;font-weight:800;letter-spacing:-.02em;margin-bottom:10px; }
.plan-price span { font-size:11px;font-weight:400;color:var(--text-secondary); }
.plan-features { list-style:none;display:flex;flex-direction:column;gap:5px;margin-bottom:14px; }
.plan-features li { font-size:11px;color:var(--text-secondary); }
.plan-btn { display:block;padding:10px;text-align:center;background:var(--background);color:var(--text-primary);border-radius:var(--radius-md);font-size:12px;font-weight:700;text-decoration:none;transition:all .15s; }
.plan-btn.featured { background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white; }
.plan-btn:hover { opacity:.85; }
.logout-btn { background:none;border:none;color:var(--text-secondary);font-size:13px;cursor:pointer;text-decoration:underline; }
</style>
