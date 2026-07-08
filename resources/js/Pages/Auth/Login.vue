<template>
  <div class="auth-page">
    <div class="auth-container">

      <!-- Logo -->
      <div class="auth-logo">
        <div class="logo-icon">CC</div>
        <div>
          <div class="logo-name">CatatCuan</div>
          <div class="logo-sub">Catat keuangan, hidup lebih tenang.</div>
        </div>
      </div>

      <!-- Trial Badge -->
      <div class="trial-badge">
        🎁 <strong>Coba gratis 7 hari</strong> — tidak perlu kartu kredit
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="auth-form">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="form-input-cc"
            :class="{ 'input-error': form.errors.email }"
            placeholder="kamu@email.com"
            autocomplete="email"
          />
          <p v-if="form.errors.email" class="error-text">{{ form.errors.email }}</p>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <input
            v-model="form.password"
            type="password"
            class="form-input-cc"
            :class="{ 'input-error': form.errors.password }"
            placeholder="••••••••"
            autocomplete="current-password"
          />
          <p v-if="form.errors.password" class="error-text">{{ form.errors.password }}</p>
        </div>

        <button type="submit" class="btn-cc" :disabled="form.processing">
          {{ form.processing ? 'Masuk...' : 'Masuk' }}
        </button>
      </form>

      <div class="auth-divider"><span>atau</span></div>

      <button class="social-btn" type="button">
        <span>🇬</span> Lanjut dengan Google
      </button>
      <button class="social-btn" type="button">
        📱 Lanjut dengan Nomor HP (OTP)
      </button>

      <div class="auth-footer">
        Belum punya akun?
        <Link :href="route('register')" class="auth-link">Daftar sekarang</Link>
      </div>

    </div>
  </div>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  background: var(--surface);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.auth-container {
  width: 100%;
  max-width: 400px;
}

.auth-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 32px;
}

.logo-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Syne', sans-serif;
  font-size: 14px;
  font-weight: 800;
  flex-shrink: 0;
}

.logo-name {
  font-family: 'Syne', sans-serif;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -.02em;
}

.logo-sub {
  font-size: 12px;
  color: var(--text-secondary);
  margin-top: 2px;
}

.trial-badge {
  background: var(--amber-bg);
  border-radius: var(--radius-md);
  padding: 12px 14px;
  font-size: 13px;
  color: #7a5a00;
  line-height: 1.6;
  margin-bottom: 24px;
  text-align: center;
}

.auth-form { margin-bottom: 16px; }

.form-group { margin-bottom: 14px; }

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  display: block;
  margin-bottom: 6px;
}

.input-error { border-color: var(--danger) !important; }
.error-text { font-size: 11px; color: var(--danger); margin-top: 4px; }

.auth-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 16px 0;
  color: var(--text-secondary);
  font-size: 12px;
}

.auth-divider::before,
.auth-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

.social-btn {
  width: 100%;
  padding: 13px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-md);
  background: var(--surface);
  font-size: 14px;
  font-family: inherit;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: all .15s;
  margin-bottom: 10px;
  color: var(--text-primary);
}

.social-btn:hover { border-color: var(--text-primary); }

.auth-footer {
  text-align: center;
  margin-top: 20px;
  font-size: 13px;
  color: var(--text-secondary);
}

.auth-link {
  color: var(--text-primary);
  font-weight: 700;
  text-decoration: none;
}
</style>
