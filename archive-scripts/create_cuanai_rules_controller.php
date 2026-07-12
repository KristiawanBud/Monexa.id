<?php

$controllerPath = '/var/www/monexa/app/Http/Controllers/Admin/CuanAiRulesController.php';
$vuePath = '/var/www/monexa/resources/js/Pages/Admin/CuanAiRules.vue';

// ── Controller ──
if (file_exists($controllerPath)) {
    echo "SKIP (sudah ada): $controllerPath\n";
} else {
    $dir = dirname($controllerPath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($controllerPath, <<<'EOT'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\CuanAiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CuanAiRulesController extends Controller
{
    public function index(): Response
    {
        $current = SystemSetting::get('cuan_ai_system_prompt');

        return Inertia::render('Admin/CuanAiRules', [
            'prompt'         => $current ?? CuanAiService::defaultPromptTemplate(),
            'is_custom'      => $current !== null,
            'default_prompt' => CuanAiService::defaultPromptTemplate(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'prompt' => ['required', 'string', 'max:8000'],
        ]);

        SystemSetting::set(
            'cuan_ai_system_prompt',
            $request->prompt,
            'System prompt / aturan CuanAI — diedit via Admin Panel'
        );

        return back()->with('success', 'Aturan CuanAI berhasil disimpan!');
    }

    public function reset(): RedirectResponse
    {
        SystemSetting::forget('cuan_ai_system_prompt');

        return back()->with('success', 'Aturan CuanAI dikembalikan ke default.');
    }
}

EOT
    );
    echo "Ditulis: $controllerPath\n";
}

// ── Vue page ──
if (file_exists($vuePath)) {
    echo "SKIP (sudah ada): $vuePath\n";
} else {
    $dir = dirname($vuePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($vuePath, <<<'EOT'
<template>
  <div class="admin-shell">
    <aside :class="['admin-sidebar', { collapsed: sc }]">
      <div class="sidebar-logo">
        <div class="logo-icon">CC</div>
        <span v-if="!sc" class="logo-name">CatatCuan Admin</span>
      </div>
      <button class="hamburger" @click="sc = !sc"><span></span><span></span><span></span></button>
      <nav class="sidebar-nav">
        <Link :href="route('admin.dashboard')" class="nav-item" data-label="Dashboard">
          <span class="ni-icon">📊</span><span v-if="!sc">Dashboard</span>
        </Link>
        <Link :href="route('admin.users')" class="nav-item" data-label="Users">
          <span class="ni-icon">👥</span><span v-if="!sc">Manajemen User</span>
        </Link>
        <Link :href="route('admin.gateway.index')" class="nav-item" data-label="WA Gateway">
          <span class="ni-icon">📱</span><span v-if="!sc">WA Gateway</span>
        </Link>
        <Link :href="route('admin.cuan-ai-rules')" class="nav-item active" data-label="CuanAI Rules">
          <span class="ni-icon">🤖</span><span v-if="!sc">CuanAI Rules</span>
        </Link>
        <Link :href="route('admin.icons')" class="nav-item" data-label="Icons">
          <span class="ni-icon">🖼️</span><span v-if="!sc">Icon & Assets</span>
        </Link>
        <Link :href="route('dashboard')" class="nav-item" data-label="App">
          <span class="ni-icon">🏠</span><span v-if="!sc">Kembali ke App</span>
        </Link>
      </nav>
    </aside>

    <div :class="['admin-main', { expanded: sc }]">
      <div class="admin-topbar">
        <div class="topbar-left">
          <button class="hamburger-top" @click="sc = !sc">☰</button>
          <div>
            <div class="topbar-title">CuanAI Rules 🤖</div>
            <div class="topbar-breadcrumb">Admin → CuanAI Rules</div>
          </div>
        </div>
        <div class="topbar-actions">
          <span v-if="isCustom" class="badge-custom">✏️ Custom aktif</span>
          <span v-else class="badge-default">⚙️ Pakai Default</span>
        </div>
      </div>

      <div class="admin-content">
        <div v-if="$page.props.flash?.success" class="flash-success">
          {{ $page.props.flash.success }}
        </div>

        <div class="rules-card">
          <div class="rules-card-header">
            <h3>System Prompt CuanAI</h3>
            <p>Edit instruksi & format jawaban CuanAI di sini. Perubahan langsung berlaku ke Web Chat & WA Bot tanpa perlu edit kode.</p>
          </div>

          <textarea
            v-model="promptText"
            class="rules-textarea"
            rows="22"
            spellcheck="false"
          ></textarea>

          <div class="char-count">{{ promptText.length }} / 8000 karakter</div>

          <div class="placeholder-legend">
            <strong>⚠️ Placeholder wajib dipertahankan:</strong>
            <code>{userMessage}</code> <code>{history}</code> <code>{greetingInstruction}</code>
            <br><strong>Placeholder data lain yang tersedia:</strong>
            <code>{periodLabel}</code> <code>{totalBalance}</code> <code>{walletList}</code>
            <code>{totalIncome}</code> <code>{totalExpense}</code> <code>{topCategories}</code>
            <code>{goalList}</code> <code>{billList}</code> <code>{budgetList}</code>
          </div>

          <div class="rules-actions">
            <button class="btn-reset" @click="resetToDefault" :disabled="processing">
              🔄 Reset ke Default
            </button>
            <button class="btn-save" @click="save" :disabled="processing">
              {{ processing ? 'Menyimpan...' : '💾 Simpan Perubahan' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  prompt: String,
  is_custom: Boolean,
  default_prompt: String,
})

const sc = ref(false)
const promptText = ref(props.prompt)
const isCustom = ref(props.is_custom)
const processing = ref(false)

function save() {
  processing.value = true
  router.put(route('admin.cuan-ai-rules.update'), { prompt: promptText.value }, {
    preserveScroll: true,
    onFinish: () => { processing.value = false; isCustom.value = true },
  })
}

function resetToDefault() {
  if (!confirm('Yakin mau reset ke default? Perubahan custom kamu akan hilang.')) return
  processing.value = true
  router.delete(route('admin.cuan-ai-rules.reset'), {
    preserveScroll: true,
    onFinish: () => {
      processing.value = false
      promptText.value = props.default_prompt
      isCustom.value = false
    },
  })
}
</script>

<style scoped>
.rules-card { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.rules-card-header h3 { margin: 0 0 4px; font-size: 16px; }
.rules-card-header p { margin: 0 0 16px; font-size: 13px; color: #6b7280; }
.rules-textarea { width: 100%; font-family: 'JetBrains Mono', monospace; font-size: 13px; line-height: 1.6; padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px; resize: vertical; }
.char-count { text-align: right; font-size: 12px; color: #9ca3af; margin-top: 4px; }
.placeholder-legend { margin-top: 14px; padding: 12px; background: #f9fafb; border-radius: 10px; font-size: 12px; line-height: 1.8; color: #4b5563; }
.placeholder-legend code { background: #e5e7eb; padding: 1px 6px; border-radius: 4px; margin-right: 4px; }
.rules-actions { display: flex; gap: 10px; margin-top: 16px; justify-content: flex-end; }
.btn-save { background: #16a34a; color: #fff; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-reset { background: #f3f4f6; color: #374151; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }
.badge-custom { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-default { background: #f3f4f6; color: #6b7280; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.flash-success { background: #dcfce7; color: #166534; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
</style>

EOT
    );
    echo "Ditulis: $vuePath\n";
}

echo "\nSELESAI.\n";
