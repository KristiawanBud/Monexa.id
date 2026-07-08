<template>
  <div class="emoji-picker-wrap">
    <button type="button" class="emoji-trigger" @click="open = true">
      <span class="emoji-current">{{ modelValue || '😀' }}</span>
      <span class="emoji-label">Pilih Emoji</span>
    </button>

    <Teleport to="body">
      <div v-if="open" class="ep-overlay" @click.self="open = false">
        <div class="ep-sheet">
          <div class="ep-handle"></div>
          <div class="ep-title">Pilih Emoji</div>

          <div class="ep-categories">
            <button v-for="cat in categories" :key="cat.name"
              :class="['ep-cat-btn', { active: activeCat === cat.name }]"
              @click="activeCat = cat.name">
              {{ cat.icon }}
            </button>
          </div>

          <div class="ep-grid">
            <button v-for="emoji in currentEmojis" :key="emoji" type="button"
              class="ep-emoji-btn" @click="select(emoji)">
              {{ emoji }}
            </button>
          </div>

          <button class="ep-close" @click="open = false">Tutup</button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const activeCat = ref('keuangan')

const categories = [
  { name: 'keuangan', icon: '💰', emojis: ['💰','💵','💴','💶','💷','🪙','💳','🏦','📊','📈','📉','💹','🧾','💸','🤑'] },
  { name: 'rumah', icon: '🏠', emojis: ['🏠','🏡','🏢','🏬','🏥','🏫','⚡','💡','🔥','💧','🚿','🛋️','🪑','🛏️','🧹'] },
  { name: 'makanan', icon: '🍔', emojis: ['🍔','🍕','🍜','🍱','🍳','☕','🍵','🧋','🍰','🍦','🥗','🍞','🥤','🍻','🍷'] },
  { name: 'transport', icon: '🚗', emojis: ['🚗','🏍️','🚌','🚕','🚲','⛽','🚆','✈️','🛺','🚢','🅿️','🛣️','🚦','🚏','🛞'] },
  { name: 'belanja', icon: '🛍️', emojis: ['🛍️','🛒','👕','👗','👟','💄','📱','💻','🎮','📚','🎁','🧴','👜','⌚','💍'] },
  { name: 'kesehatan', icon: '🏥', emojis: ['🏥','💊','🩺','🦷','💉','🧬','🩹','🧘','🏋️','⚕️','😷','🌡️','🦴','👓','🩻'] },
  { name: 'hiburan', icon: '🎮', emojis: ['🎮','🎬','🎵','🎤','🎨','🎭','🎪','⚽','🏀','🎳','🎯','🎲','📺','🎸','🎫'] },
  { name: 'lain', icon: '✨', emojis: ['✨','⭐','🎯','🎓','💼','📌','🔖','📋','📝','🔔','⏰','📅','🗂️','📦','🎉'] },
]

const currentEmojis = computed(() =>
  categories.find(c => c.name === activeCat.value)?.emojis ?? []
)

const select = (emoji) => {
  emit('update:modelValue', emoji)
  open.value = false
}
</script>

<style scoped>
.emoji-picker-wrap { width: 100%; }
.emoji-trigger {
  display: flex; align-items: center; gap: 10px;
  width: 100%; padding: 12px 16px;
  border: 1.5px solid var(--border); border-radius: var(--radius-md);
  background: var(--surface); cursor: pointer; transition: border-color .2s;
}
.emoji-trigger:hover { border-color: var(--primary); }
.emoji-current { font-size: 22px; }
.emoji-label { font-size: 13px; color: var(--text-secondary); font-weight: 500; }

.ep-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 600; display: flex; align-items: flex-end; justify-content: center; backdrop-filter: blur(4px); }
.ep-sheet { background: var(--surface); border-radius: 28px 28px 0 0; width: 100%; max-width: 480px; padding: 20px 16px 30px; max-height: 70vh; display: flex; flex-direction: column; }
.ep-handle { width: 40px; height: 4px; background: var(--border); border-radius: 99px; margin: 0 auto 16px; }
.ep-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16px; font-weight: 800; margin-bottom: 14px; text-align: center; }

.ep-categories { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; margin-bottom: 14px; padding-bottom: 4px; }
.ep-cat-btn { flex-shrink: 0; width: 40px; height: 40px; border-radius: 12px; border: 1.5px solid var(--border); background: var(--background); font-size: 18px; cursor: pointer; }
.ep-cat-btn.active { border-color: var(--primary); background: var(--primary-bg); }

.ep-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; overflow-y: auto; flex: 1; padding-bottom: 10px; }
.ep-emoji-btn { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 24px; background: var(--background); border: none; border-radius: 12px; cursor: pointer; transition: transform .1s; }
.ep-emoji-btn:active { transform: scale(.9); background: var(--primary-bg); }

.ep-close { width: 100%; padding: 12px; background: var(--background); border: none; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; color: var(--text-secondary); cursor: pointer; margin-top: 12px; }
</style>
