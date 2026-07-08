<template>
  <img v-if="iconUrl" :src="iconUrl" :alt="slug" :class="$attrs.class" />
  <span v-else :class="$attrs.class"><slot /></span>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({ slug: { type: String, required: true } })

const page = usePage()
const iconUrl = computed(() => page.props.icons?.[props.slug] ?? null)
</script>

<style scoped>
/* Gambar upload otomatis nyesuain ukuran teks/emoji di sekitarnya */
img { width: 1em; height: 1em; object-fit: contain; vertical-align: -0.15em; display: inline-block; }
</style>
