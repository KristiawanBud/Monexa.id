import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { trackEvent } from '@/lib/analytics'

const MOBILE_BREAKPOINT = 480
const TRIGGER_DISTANCE = 60
const MAX_PULL_DISTANCE = 100

export function usePullToRefresh(only = []) {
  const pullDistance = ref(0)
  const refreshing = ref(false)
  let pullStartY = null

  function onTouchStart(e) {
    if (window.innerWidth > MOBILE_BREAKPOINT || window.scrollY > 0) {
      pullStartY = null
      return
    }
    pullStartY = e.touches[0].clientY
  }

  function onTouchMove(e) {
    if (pullStartY === null) return
    const delta = e.touches[0].clientY - pullStartY
    if (delta > 0) pullDistance.value = Math.min(delta, MAX_PULL_DISTANCE)
  }

  function onTouchEnd() {
    if (pullStartY === null) return
    if (pullDistance.value > TRIGGER_DISTANCE) {
      refreshing.value = true
      pullDistance.value = 0
      trackEvent('dompet_pull_to_refresh')
      router.reload({
        only,
        onFinish: () => { refreshing.value = false },
      })
    } else {
      pullDistance.value = 0
    }
    pullStartY = null
  }

  onMounted(() => {
    document.addEventListener('touchstart', onTouchStart, { passive: true })
    document.addEventListener('touchmove', onTouchMove, { passive: true })
    document.addEventListener('touchend', onTouchEnd, { passive: true })
  })

  onUnmounted(() => {
    document.removeEventListener('touchstart', onTouchStart)
    document.removeEventListener('touchmove', onTouchMove)
    document.removeEventListener('touchend', onTouchEnd)
  })

  return { pullDistance, refreshing }
}
