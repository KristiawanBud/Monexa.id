<template>
  <!-- Floating Button -->
  <button v-if="!isOpen" class="cuanai-fab" @click="open" aria-label="CuanAI">
    <span class="fab-emoji">🤖</span>
    <span class="fab-label">CuanAI</span>
    <span v-if="hasUnread" class="fab-dot"></span>
  </button>

  <!-- Chat Panel -->
  <Teleport to="body">
    <Transition name="chat-panel">
      <div v-if="isOpen" class="cuanai-panel">

        <!-- Header -->
        <div class="cp-header">
          <div class="cp-avatar">🤖</div>
          <div class="cp-info">
            <div class="cp-name">CuanAI</div>
            <div class="cp-status">Asisten Keuangan Pribadi</div>
          </div>
          <div class="cp-actions">
            <button class="cp-action-btn" @click="resetChat" title="Reset percakapan">🔄</button>
            <button class="cp-action-btn" @click="close">✕</button>
          </div>
        </div>

        <!-- Messages -->
        <div class="cp-messages" ref="messagesEl">

          <!-- Welcome -->
          <div v-if="messages.length === 0" class="cp-welcome">
            <div class="cw-emoji">👋</div>
            <div class="cw-title">Halo! Saya CuanAI</div>
            <div class="cw-sub">Tanya apa saja soal keuangan kamu. Saya punya akses ke data transaksi kamu!</div>

            <!-- Quick prompts -->
            <div class="cw-prompts">
              <button v-for="p in quickPrompts" :key="p" class="cw-prompt" @click="sendQuick(p)">
                {{ p }}
              </button>
            </div>
          </div>

          <!-- Message list -->
          <div v-for="(msg, i) in messages" :key="i"
            :class="['cp-msg', msg.role === 'user' ? 'user' : 'ai']">
            <div v-if="msg.role === 'assistant'" class="msg-avatar">🤖</div>
            <div class="msg-bubble">
              <div class="msg-text" v-html="formatMessage(msg.content)"></div>
              <div class="msg-time">{{ msg.time }}</div>
            </div>
          </div>

          <!-- Typing indicator -->
          <div v-if="isTyping" class="cp-msg ai">
            <div class="msg-avatar">🤖</div>
            <div class="msg-bubble typing">
              <span></span><span></span><span></span>
            </div>
          </div>

        </div>

        <!-- Input -->
        <div class="cp-input-wrap">
          <input
            v-model="inputText"
            class="cp-input"
            placeholder="Tanya soal keuangan kamu..."
            @keydown.enter.prevent="send"
            :disabled="isTyping"
            ref="inputEl"
            maxlength="500"
          />
          <button class="cp-send" @click="send" :disabled="!inputText.trim() || isTyping">
            {{ isTyping ? '⏳' : '➤' }}
          </button>
        </div>

        <!-- Disclaimer -->
        <div class="cp-disclaimer">
          CuanAI hanya membahas topik keuangan. Data dijaga kerahasiaannya.
        </div>

      </div>
    </Transition>

    <!-- Overlay mobile -->
    <Transition name="fade">
      <div v-if="isOpen" class="cuanai-overlay" @click="close"></div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const isOpen    = ref(false)
const isTyping  = ref(false)
const hasUnread = ref(false)
const inputText = ref('')
const messages  = ref([])
const messagesEl = ref(null)
const inputEl   = ref(null)

const quickPrompts = [
  '💰 Berapa saldo totalku sekarang?',
  '📊 Analisa pengeluaranku bulan ini',
  '🏦 Tips meningkatkan tabungan',
  '⚠️ Tagihan apa yang jatuh tempo minggu ini?',
]

const open = async () => {
  isOpen.value    = true
  hasUnread.value = false
  await nextTick()
  inputEl.value?.focus()

  if (messages.value.length === 0) {
    await loadHistory()
  }

  scrollToBottom()
}

const close = () => { isOpen.value = false }

const loadHistory = async () => {
  try {
    const { data } = await axios.get(route('ai.history'))
    if (data.messages?.length) {
      messages.value = data.messages.map(m => ({
        role:    m.role,
        content: m.content,
        time:    formatTime(m.timestamp),
      }))
    }
  } catch {}
}

const send = async () => {
  const text = inputText.value.trim()
  if (!text || isTyping.value) return

  messages.value.push({ role: 'user', content: text, time: now() })
  inputText.value = ''
  isTyping.value  = true
  await nextTick()
  scrollToBottom()

  try {
    const { data } = await axios.post(route('ai.chat'), { message: text })

    messages.value.push({
      role:    'assistant',
      content: data.reply,
      time:    data.timestamp,
    })

    // ── Kalau CuanAI barusan nyimpen transaksi/aksi ──
    // Refresh data halaman yang lagi kebuka (Dashboard, dll)
    // TANPA reload penuh, biar saldo & transaksi langsung update.
    if (data.action_taken) {
      router.reload({ preserveScroll: true, preserveState: true })
    }
  } catch {
    messages.value.push({
      role:    'assistant',
      content: 'Maaf, ada gangguan koneksi. Coba lagi ya! 🙏',
      time:    now(),
    })
  } finally {
    isTyping.value = false
    await nextTick()
    scrollToBottom()
  }
}

const sendQuick = (prompt) => {
  inputText.value = prompt
  send()
}

const resetChat = async () => {
  if (!confirm('Reset percakapan? Riwayat chat akan dihapus.')) return
  try {
    await axios.post(route('ai.reset'))
    messages.value = []
  } catch {}
}

const scrollToBottom = () => {
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
}

const formatMessage = (text) => {
  return text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/\n/g, '<br>')
}

const now = () => new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
const formatTime = (iso) => {
  if (!iso) return ''
  try { return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }
  catch { return '' }
}
</script>

<style scoped>
/* ── FAB ──────────────────────────────────── */
.cuanai-fab {
  position: fixed;
  bottom: 88px; /* di atas bottom nav */
  right: 18px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px 10px 12px;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  color: white;
  border: none;
  border-radius: 99px;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0,0,0,.25);
  font-size: 13px;
  font-weight: 700;
  font-family: inherit;
  z-index: 90;
  transition: all .2s;
  position: fixed;
}
.cuanai-fab:hover { transform: scale(1.05); }
.fab-emoji { font-size: 18px; }
.fab-label { font-size: 12px; }
.fab-dot {
  position: absolute;
  top: 0; right: 0;
  width: 10px; height: 10px;
  background: var(--danger);
  border-radius: 50%;
  border: 2px solid white;
}

/* ── Panel ────────────────────────────────── */
.cuanai-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.4);
  z-index: 300;
  backdrop-filter: blur(3px);
}

.cuanai-panel {
  position: fixed;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 480px;
  height: 85vh;
  background: var(--surface);
  border-radius: 20px 20px 0 0;
  z-index: 301;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Header */
.cp-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 18px;
  border-bottom: 1px solid var(--background);
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  flex-shrink: 0;
}
.cp-avatar {
  width: 38px; height: 38px;
  border-radius: 50%;
  background: rgba(255,255,255,.15);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}
.cp-info { flex: 1; }
.cp-name { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 800; color: white; }
.cp-status { font-size: 11px; color: rgba(255,255,255,.45); margin-top: 1px; }
.cp-actions { display: flex; gap: 6px; }
.cp-action-btn {
  width: 30px; height: 30px; border-radius: 50%;
  background: rgba(255,255,255,.1); border: none;
  color: white; font-size: 14px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all .15s;
}
.cp-action-btn:hover { background: rgba(255,255,255,.2); }

/* Messages */
.cp-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  scroll-behavior: smooth;
}

/* Welcome */
.cp-welcome { text-align: center; padding: 20px 0; }
.cw-emoji { font-size: 40px; margin-bottom: 8px; }
.cw-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16px; font-weight: 800; margin-bottom: 6px; }
.cw-sub { font-size: 12px; color: var(--text-secondary); line-height: 1.7; margin-bottom: 16px; }
.cw-prompts { display: flex; flex-direction: column; gap: 8px; }
.cw-prompt {
  text-align: left;
  padding: 10px 14px;
  background: var(--background);
  border: 1.5px solid var(--border);
  border-radius: var(--radius-md);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  font-family: inherit;
  color: var(--text-primary);
  transition: all .15s;
}
.cw-prompt:hover { border-color: var(--text-primary); background: var(--surface); }

/* Messages */
.cp-msg { display: flex; gap: 8px; align-items: flex-end; }
.cp-msg.user { flex-direction: row-reverse; }
.msg-avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--background); font-size: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.msg-bubble { max-width: 80%; }
.cp-msg.user .msg-bubble { align-items: flex-end; }
.msg-text {
  padding: 10px 14px;
  border-radius: 16px;
  font-size: 13px;
  line-height: 1.6;
}
.cp-msg.ai   .msg-text { background: var(--background); border-radius: 4px 16px 16px 16px; color: var(--text-primary); }
.cp-msg.user .msg-text { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; border-radius: 16px 4px 16px 16px; }
.msg-time { font-size: 10px; color: var(--text-secondary); margin-top: 3px; padding: 0 4px; }
.cp-msg.user .msg-time { text-align: right; }

/* Typing indicator */
.msg-bubble.typing {
  padding: 12px 16px;
  background: var(--background);
  border-radius: 4px 16px 16px 16px;
  display: flex; gap: 4px; align-items: center;
}
.msg-bubble.typing span {
  width: 7px; height: 7px;
  background: var(--text-secondary);
  border-radius: 50%;
  animation: bounce .8s infinite;
}
.msg-bubble.typing span:nth-child(2) { animation-delay: .15s; }
.msg-bubble.typing span:nth-child(3) { animation-delay: .3s; }
@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); }
  30%            { transform: translateY(-6px); }
}

/* Input */
.cp-input-wrap {
  display: flex;
  gap: 8px;
  padding: 12px 14px;
  border-top: 1px solid var(--background);
  background: var(--surface);
  flex-shrink: 0;
}
.cp-input {
  flex: 1;
  padding: 10px 14px;
  border: 1.5px solid var(--border);
  border-radius: 99px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  background: var(--background);
  transition: border-color .2s;
}
.cp-input:focus { border-color: var(--text-primary); background: var(--surface); }
.cp-input:disabled { opacity: .6; }
.cp-send {
  width: 40px; height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
  color: white;
  border: none;
  font-size: 16px;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: all .15s;
}
.cp-send:disabled { opacity: .4; cursor: not-allowed; }
.cp-send:not(:disabled):hover { background: var(--text-secondary); }

.cp-disclaimer {
  padding: 6px 14px 10px;
  font-size: 10px;
  color: var(--border);
  text-align: center;
  background: var(--surface);
  flex-shrink: 0;
}

/* Animations */
.chat-panel-enter-active, .chat-panel-leave-active { transition: transform .3s cubic-bezier(.4,0,.2,1); }
.chat-panel-enter-from, .chat-panel-leave-to { transform: translateX(-50%) translateY(100%); }
.fade-enter-active, .fade-leave-active { transition: opacity .3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
