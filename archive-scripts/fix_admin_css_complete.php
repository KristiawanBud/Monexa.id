<?php

// ── FIX 1: Tambah CSS variabel Admin yang belum ada ──
$appCss = '/var/www/monexa/resources/css/app.css';
$adminTokens = <<<'CSS'

/* ── Admin Panel Design Tokens (tema monokromatik terpisah dari App) ── */
:root {
  --off:         #F5F5F4;
  --ink:         #18181B;
  --ink-muted:   #71717A;
  --stone:       #E7E5E4;
  --white:       #FFFFFF;
  --shadow:      0 1px 3px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.06);
  --radius:      14px;
  --green:       #22C55E;
  --green-dark:  #15803D;
  --red:         #EF4444;
  --red-dark:    #991B1B;
  --amber-light: #FEF6E7;
}
CSS;

if (file_exists($appCss)) {
    $content = file_get_contents($appCss);
    if (strpos($content, '--ink:') !== false) {
        echo "SKIP: app.css sudah punya token admin (--ink), dilewati\n";
    } else {
        copy($appCss, $appCss.'.bak_'.date('Ymd_His'));
        file_put_contents($appCss, $content.$adminTokens);
        echo "OK: token warna admin ditambahkan ke app.css\n";
    }
} else {
    echo "SKIP: app.css tidak ditemukan\n";
}

// ── FIX 2: Tambah CSS sidebar/topbar yang hilang di Icons.vue & CuanAiRules.vue ──
$sharedShellCss = <<<'CSS'

/* ── Admin Shell / Sidebar / Topbar (disamakan dengan Dashboard.vue) ── */
.admin-shell { display:flex;min-height:100vh;background:var(--off); }
.admin-sidebar { width:220px;min-height:100vh;background:var(--ink);display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;overflow-y:auto;overflow-x:hidden;transition:width .25s ease;z-index:100; }
.admin-sidebar.collapsed { width:64px; }
.sidebar-logo { display:flex;align-items:center;gap:10px;padding:18px 14px;border-bottom:1px solid rgba(255,255,255,.08);position:relative; }
.logo-icon { width:30px;height:30px;border-radius:7px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-family:"Syne",sans-serif;font-size:11px;font-weight:800;color:white;flex-shrink:0; }
.logo-name { font-family:"Syne",sans-serif;font-size:14px;font-weight:800;color:white;white-space:nowrap; }
.hamburger { position:absolute;top:16px;right:10px;background:none;border:none;cursor:pointer;display:flex;flex-direction:column;gap:4px;padding:2px; }
.hamburger span { display:block;width:16px;height:2px;background:rgba(255,255,255,.5);border-radius:99px;transition:all .25s; }
.hamburger:hover span { background:white; }
.sidebar-nav { padding:8px 0; }
.nav-item { display:flex;align-items:center;gap:10px;padding:9px 14px;margin:1px 8px;border-radius:8px;cursor:pointer;color:rgba(255,255,255,.5);font-size:13px;font-weight:500;text-decoration:none;transition:all .15s;position:relative;white-space:nowrap; }
.nav-item:hover { background:rgba(255,255,255,.07);color:rgba(255,255,255,.85); }
.nav-item.active { background:rgba(255,255,255,.13);color:white;font-weight:600; }
.nav-item.active::before { content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:18px;background:var(--green);border-radius:0 3px 3px 0; }
.ni-icon { font-size:16px;flex-shrink:0;width:20px;text-align:center; }
.admin-main { margin-left:220px;flex:1;min-height:100vh;transition:margin-left .25s ease; }
.admin-main.expanded { margin-left:64px; }
.admin-topbar { background:var(--white);border-bottom:1px solid var(--stone);padding:0 24px;height:54px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:var(--shadow); }
.topbar-left { display:flex;align-items:center;gap:12px; }
.hamburger-top { background:var(--stone);border:none;border-radius:8px;padding:6px 8px;cursor:pointer;font-size:16px; }
.topbar-title { font-family:"Syne",sans-serif;font-size:16px;font-weight:800; }
.topbar-breadcrumb { font-size:11px;color:var(--ink-muted); }
.topbar-actions { display:flex;align-items:center;gap:8px; }
.admin-content { padding:24px; }
CSS;

$targets = [
    '/var/www/monexa/resources/js/Pages/Admin/Icons.vue',
    '/var/www/monexa/resources/js/Pages/Admin/CuanAiRules.vue',
];

foreach ($targets as $file) {
    if (! file_exists($file)) {
        echo "SKIP (tidak ditemukan): $file\n";

        continue;
    }

    $content = file_get_contents($file);

    if (strpos($content, '.admin-shell {') !== false) {
        echo "SKIP (CSS shell sudah ada): $file\n";

        continue;
    }

    if (strpos($content, '<style scoped>') === false) {
        echo "⚠️  Tidak ketemu <style scoped> di: $file — cek manual\n";

        continue;
    }

    copy($file, $file.'.bak_'.date('Ymd_His'));
    $newContent = str_replace('<style scoped>', '<style scoped>'.$sharedShellCss, $content);
    file_put_contents($file, $newContent);

    echo "OK (CSS shell ditambahkan): $file\n";
}

echo "\nSELESAI.\n";
