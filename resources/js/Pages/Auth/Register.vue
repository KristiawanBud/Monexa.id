<template>
  <div class="auth-page">
    <div class="auth-container">

      <div class="auth-logo">
        <div class="logo-icon">CC</div>
        <div>
          <div class="logo-name">CatatCuan</div>
          <div class="logo-sub">Daftar sekarang, gratis 7 hari!</div>
        </div>
      </div>

      <form @submit.prevent="submit" class="auth-form">
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input v-model="form.name" type="text" class="form-input-cc"
            :class="{ 'input-error': form.errors.name }"
            placeholder="Nama lengkap kamu" autocomplete="name" />
          <p v-if="form.errors.name" class="error-text">{{ form.errors.name }}</p>
        </div>

        <div class="form-group">
          <label class="form-label">Email</label>
          <input v-model="form.email" type="email" class="form-input-cc"
            :class="{ 'input-error': form.errors.email }"
            placeholder="kamu@email.com" />
          <p v-if="form.errors.email" class="error-text">{{ form.errors.email }}</p>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <input v-model="form.password" type="password" class="form-input-cc"
            :class="{ 'input-error': form.errors.password }"
            placeholder="Minimal 8 karakter" />
          <p v-if="form.errors.password" class="error-text">{{ form.errors.password }}</p>
        </div>

        <div class="form-group">
          <label class="form-label">Konfirmasi Password</label>
          <input v-model="form.password_confirmation" type="password" class="form-input-cc"
            placeholder="Ulangi password" />
        </div>

        <button type="submit" class="btn-cc" :disabled="form.processing">
          {{ form.processing ? 'Mendaftar...' : 'Daftar & Mulai Trial' }}
        </button>
      </form>

      <div class="auth-footer">
        Sudah punya akun?
        <Link :href="route('login')" class="auth-link">Masuk</Link>
      </div>

    </div>
  </div>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<style scoped>
.auth-page { min-height:100vh;background:var(--surface);display:flex;align-items:center;justify-content:center;padding:24px; }
.auth-container { width:100%;max-width:400px; }
.auth-logo { display:flex;align-items:center;gap:12px;margin-bottom:28px; }
.logo-icon { width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white;display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;flex-shrink:0; }
.logo-name { font-family:'Plus Jakarta Sans',sans-serif;font-size:20px;font-weight:800;letter-spacing:-.02em; }
.logo-sub { font-size:12px;color:var(--text-secondary);margin-top:2px; }
.auth-form { margin-bottom:16px; }
.form-group { margin-bottom:14px; }
.form-label { font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px; }
.input-error { border-color:var(--danger) !important; }
.error-text { font-size:11px;color:var(--danger);margin-top:4px; }
.auth-footer { text-align:center;margin-top:20px;font-size:13px;color:var(--text-secondary); }
.auth-link { color:var(--text-primary);font-weight:700;text-decoration:none; }
</style>
