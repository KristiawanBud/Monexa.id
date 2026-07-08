<template>
  <div class="admin-shell">

    <!-- Sidebar -->
    <aside :class="['admin-sidebar', { collapsed: sidebarCollapsed }]" id="admin-sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon">CC</div>
        <div v-if="!sidebarCollapsed" class="logo-text">
          <div class="logo-name">CatatCuan</div>
          <div class="logo-sub">Admin Panel</div>
        </div>
      </div>

      <button class="hamburger" @click="sidebarCollapsed = !sidebarCollapsed">
        <span></span><span></span><span></span>
      </button>

      <nav class="sidebar-nav">
        <div v-if="!sidebarCollapsed" class="nav-section">Utama</div>
        <Link :href="route('admin.dashboard')" :class="['nav-item', { active: isActive('Admin/Dashboard') }]" data-label="Dashboard">
          <span class="ni-icon">📊</span>
          <span v-if="!sidebarCollapsed" class="ni-label">Dashboard</span>
        </Link>
        <Link :href="route('admin.users')" :class="['nav-item', { active: isActive('Admin/Users') }]" data-label="Manajemen User">
          <span class="ni-icon">👥</span>
          <span v-if="!sidebarCollapsed" class="ni-label">Manajemen User</span>
        </Link>

        <div v-if="!sidebarCollapsed" class="nav-section">Sistem</div>
        <Link :href="route('dashboard')" class="nav-item" data-label="Kembali ke App">
          <span class="ni-icon">🏠</span>
          <span v-if="!sidebarCollapsed" class="ni-label">Kembali ke App</span>
        </Link>
              <Link :href="route('admin.icons')" class="nav-item" data-label="Icons">
          <span class="ni-icon">🖼️</span><span v-if="!sc">Icon & Assets</span>
        </Link>
              <Link :href="route('admin.packages')" class="nav-item" data-label="Paket">
          <span class="ni-icon">💳</span><span v-if="!sc">Paket & Harga</span>
        </Link>
        <Link :href="route('admin.subscriptions')" class="nav-item" data-label="Subscription">
          <span class="ni-icon">📋</span><span v-if="!sc">Subscription User</span>
        </Link>
      </nav>

      <div class="sidebar-footer">
        <Link :href="route('logout')" method="post" as="button" class="logout-nav-btn">
          <span>🚪</span>
          <span v-if="!sidebarCollapsed">Keluar</span>
        </Link>
      </div>
    </aside>

    <!-- Main -->
    <div :class="['admin-main', { expanded: sidebarCollapsed }]">

      <!-- Topbar -->
      <div class="admin-topbar">
        <div class="topbar-left">
          <button class="hamburger-top" @click="sidebarCollapsed = !sidebarCollapsed">☰</button>
          <div>
            <div class="topbar-title">Dashboard</div>
            <div class="topbar-breadcrumb">Admin Panel → Dashboard</div>
          </div>
        </div>
        <div class="topbar-right">
          <span class="admin-role-badge">{{ $page.props.auth.user?.role?.replace('_',' ') }}</span>
        </div>
      </div>

      <!-- Content -->
      <div class="admin-content">

        <!-- Stat Cards -->
        <div class="stat-grid">
          <div class="stat-card">
            <div class="sc-label">Total User</div>
            <div class="sc-val">{{ totalUsers.toLocaleString('id') }}</div>
            <div class="sc-sub">+{{ newUsersToday }} hari ini</div>
          </div>
          <div class="stat-card">
            <div class="sc-label">Aktif Berbayar</div>
            <div class="sc-val" style="color:var(--green-dark)">{{ paidUsers.toLocaleString('id') }}</div>
            <div class="sc-sub">dari {{ activeUsers }} total aktif</div>
          </div>
          <div class="stat-card">
            <div class="sc-label">User Trial</div>
            <div class="sc-val" style="color:var(--amber)">{{ trialUsers }}</div>
            <div class="sc-sub">potensial konversi</div>
          </div>
          <div class="stat-card">
            <div class="sc-label">Revenue Bulan Ini</div>
            <div class="sc-val">{{ formatRupiah(revenueThisMonth) }}</div>
            <div class="sc-sub">dari subscription</div>
          </div>
          <div class="stat-card">
            <div class="sc-label">Total Transaksi</div>
            <div class="sc-val">{{ totalTx.toLocaleString('id') }}</div>
            <div class="sc-sub">semua user, all time</div>
          </div>
        </div>

        <div class="admin-grid-2">
          <!-- Revenue Chart -->
          <div class="admin-card">
            <div class="admin-card-title">Revenue 6 Bulan</div>
            <div class="bar-wrap">
              <div v-for="item in revenueChart" :key="item.month" class="bar-col">
                <div class="bar-num">{{ formatShort(item.revenue) }}</div>
                <div class="bar-fill" :style="`height:${barH(item.revenue)}px`"></div>
                <div class="bar-lbl">{{ item.month }}</div>
              </div>
            </div>
          </div>

          <!-- Recent Users -->
          <div class="admin-card">
            <div class="admin-card-title">User Terbaru</div>
            <div v-for="u in recentUsers" :key="u.id" class="user-row">
              <div class="user-avatar">{{ u.name.charAt(0) }}</div>
              <div class="user-info">
                <div class="user-name">{{ u.name }}</div>
                <div class="user-email">{{ u.email }}</div>
              </div>
              <div class="user-date">{{ u.created_at }}</div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
  totalUsers: Number,
  activeUsers: Number,
  trialUsers: Number,
  paidUsers: Number,
  totalTx: Number,
  newUsersToday: Number,
  revenueThisMonth: Number,
  revenueChart: Array,
  recentUsers: Array,
})

const sidebarCollapsed = ref(false)
const page = usePage()
const isActive = (c) => page.component === c

const maxRev = computed(() => Math.max(...props.revenueChart.map(r => r.revenue), 1))
const barH = (v) => Math.max(4, (v / maxRev.value) * 80)

const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
const formatShort = (n) => {
  n = Number(n || 0)
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000)     return (n / 1_000).toFixed(0) + 'rb'
  return String(n)
}
</script>

<style scoped>
.admin-shell { display:flex;min-height:100vh;background:var(--off); }

/* Sidebar */
.admin-sidebar { width:220px;min-height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;overflow-y:auto;overflow-x:hidden;transition:width .25s ease;z-index:100; }
.admin-sidebar.collapsed { width:64px; }
.sidebar-logo { display:flex;align-items:center;gap:10px;padding:18px 14px;border-bottom:1px solid rgba(255,255,255,.08);position:relative; }
.logo-icon { width:30px;height:30px;border-radius:7px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-family:"Syne",sans-serif;font-size:11px;font-weight:800;color:white;flex-shrink:0; }
.logo-name { font-family:"Syne",sans-serif;font-size:14px;font-weight:800;color:white;white-space:nowrap; }
.logo-sub  { font-size:10px;color:rgba(255,255,255,.35);white-space:nowrap; }
.hamburger { position:absolute;top:16px;right:10px;background:none;border:none;cursor:pointer;display:flex;flex-direction:column;gap:4px;padding:2px; }
.hamburger span { display:block;width:16px;height:2px;background:rgba(255,255,255,.5);border-radius:99px;transition:all .25s; }
.hamburger:hover span { background:white; }
.nav-section { padding:12px 14px 4px;font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.3);white-space:nowrap;overflow:hidden; }
.nav-item { display:flex;align-items:center;gap:10px;padding:9px 14px;margin:1px 8px;border-radius:8px;cursor:pointer;color:rgba(255,255,255,.5);font-size:13px;font-weight:500;text-decoration:none;transition:all .15s;position:relative;white-space:nowrap; }
.nav-item:hover { background:rgba(255,255,255,.07);color:rgba(255,255,255,.85); }
.nav-item.active { background:rgba(255,255,255,.13);color:white;font-weight:600; }
.nav-item.active::before { content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:18px;background:var(--green);border-radius:0 3px 3px 0; }
.ni-icon { font-size:16px;flex-shrink:0;width:20px;text-align:center; }
.admin-sidebar.collapsed .nav-item:hover::after { content:attr(data-label);position:absolute;left:68px;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.85);color:white;font-size:12px;padding:6px 12px;border-radius:8px;white-space:nowrap;z-index:200;pointer-events:none; }
.sidebar-footer { margin-top:auto;padding:10px;border-top:1px solid rgba(255,255,255,.08); }
.logout-nav-btn { width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:rgba(231,76,60,.15);color:var(--red);border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none; }

/* Main */
.admin-main { margin-left:220px;flex:1;min-height:100vh;transition:margin-left .25s ease; }
.admin-main.expanded { margin-left:64px; }

/* Topbar */
.admin-topbar { background:var(--white);border-bottom:1px solid var(--stone);padding:0 24px;height:54px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:var(--shadow); }
.topbar-left { display:flex;align-items:center;gap:12px; }
.hamburger-top { background:var(--stone);border:none;border-radius:8px;padding:6px 8px;cursor:pointer;font-size:16px; }
.topbar-title { font-family:"Syne",sans-serif;font-size:16px;font-weight:800; }
.topbar-breadcrumb { font-size:11px;color:var(--ink-muted); }
.admin-role-badge { font-size:11px;font-weight:700;padding:4px 12px;background:var(--amber-light);color:#7a5a00;border-radius:99px;text-transform:capitalize; }

/* Content */
.admin-content { padding:24px; }
.stat-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px; }
.stat-card { background:var(--white);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow); }
.sc-label { font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:6px; }
.sc-val { font-family:"Syne",sans-serif;font-size:22px;font-weight:800; }
.sc-sub { font-size:11px;color:var(--ink-muted);margin-top:3px; }

.admin-grid-2 { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
.admin-card { background:var(--white);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow); }
.admin-card-title { font-size:13px;font-weight:700;margin-bottom:14px; }

/* Bar chart */
.bar-wrap { display:flex;align-items:flex-end;gap:8px;height:100px; }
.bar-col { flex:1;display:flex;flex-direction:column;align-items:center;gap:0; }
.bar-num { font-size:9px;color:var(--ink-muted);margin-bottom:2px; }
.bar-fill { width:100%;background:var(--ink);border-radius:4px 4px 0 0;min-height:4px;transition:height .4s; }
.bar-lbl { font-size:9px;color:var(--ink-muted);margin-top:4px; }

/* Users */
.user-row { display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--stone); }
.user-row:last-child { border-bottom:none; }
.user-avatar { width:32px;height:32px;border-radius:50%;background:var(--ink);color:white;display:flex;align-items:center;justify-content:center;font-family:"Syne",sans-serif;font-size:12px;font-weight:800;flex-shrink:0; }
.user-info { flex:1;min-width:0; }
.user-name { font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.user-email { font-size:11px;color:var(--ink-muted); }
.user-date { font-size:11px;color:var(--ink-muted);flex-shrink:0; }
</style>
