import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import 'bootstrap/dist/css/bootstrap.min.css'
import '../css/app.css'
import { initTheme } from './Composables/useTheme'

initTheme()

createInertiaApp({
  title: (title) => `${title} — CatatCuan`,

  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.vue`,
      import.meta.glob('./Pages/**/*.vue')
    ),

  setup({ el, App, props, plugin }) {
    return createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue)
      .mount(el)
  },

  progress: {
    color: '#0F0F0F',
  },
})
