<template>
  <div class="onboard-page">
    <!-- Progress -->
    <div class="onboard-progress">
      <div class="step-label">Langkah 1 dari 3</div>
      <div class="step-bars">
        <div class="step-bar active"></div>
        <div class="step-bar"></div>
        <div class="step-bar"></div>
      </div>
    </div>

    <div class="onboard-body">
      <div class="onboard-icon">👋</div>
      <h1 class="onboard-title">Halo! Kenalin dulu yuk</h1>
      <p class="onboard-sub">Isi info dasar profilmu. Dipakai untuk sapaan dan laporan kamu.</p>

      <form @submit.prevent="submit">
        <div class="form-group">
          <label class="form-label">Nomor WhatsApp</label>
          <input v-model="form.wa_number" type="tel" class="form-input-cc"
            :class="{ 'input-error': form.errors.wa_number }"
            placeholder="+62 812-xxxx-xxxx" />
          <p class="hint-text">Dipakai untuk notifikasi dan laporan otomatis via WA Bot</p>
          <p v-if="form.errors.wa_number" class="error-text">{{ form.errors.wa_number }}</p>
        </div>

        <div class="form-group">
          <label class="form-label">Mata Uang</label>
          <select v-model="form.currency" class="form-input-cc">
            <option value="IDR">🇮🇩 IDR — Rupiah Indonesia</option>
            <option value="USD">🇺🇸 USD — US Dollar</option>
            <option value="SGD">🇸🇬 SGD — Singapore Dollar</option>
          </select>
        </div>

        <button type="submit" class="btn-cc" :disabled="form.processing">
          {{ form.processing ? 'Menyimpan...' : 'Lanjut →' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  wa_number: '',
  currency: 'IDR',
})

const submit = () => {
  form.post(route('onboarding.save-step1'))
}
</script>

<style scoped>
.onboard-page { min-height:100vh;background:var(--background);padding:24px;max-width:480px;margin:0 auto; }
.onboard-progress { margin-bottom:28px; }
.step-label { font-size:11px;font-weight:600;color:var(--text-secondary);letter-spacing:.05em;text-transform:uppercase;margin-bottom:8px; }
.step-bars { display:flex;gap:6px; }
.step-bar { flex:1;height:4px;border-radius:99px;background:var(--border); }
.step-bar.active { background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
.onboard-icon { font-size:48px;margin-bottom:16px; }
.onboard-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:800;letter-spacing:-.02em;margin-bottom:8px; }
.onboard-sub { font-size:14px;color:var(--text-secondary);line-height:1.6;margin-bottom:28px; }
.onboard-body { padding:8px 0; }
.form-group { margin-bottom:16px; }
.form-label { font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px; }
.hint-text { font-size:11px;color:var(--text-secondary);margin-top:5px;line-height:1.5; }
.input-error { border-color:var(--danger) !important; }
.error-text { font-size:11px;color:var(--danger);margin-top:4px; }
</style>
