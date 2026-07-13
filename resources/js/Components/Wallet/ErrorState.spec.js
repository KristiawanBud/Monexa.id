import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ErrorState from './ErrorState.vue'

describe('ErrorState', () => {
  it('renders the default message and retry button', async () => {
    const wrapper = mount(ErrorState)
    expect(wrapper.text()).toContain('Gagal memuat data')
    await wrapper.find('.error-retry').trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('renders a custom message and retry label', () => {
    const wrapper = mount(ErrorState, {
      props: { message: 'Koneksi terputus', retryLabel: 'Ulangi' },
    })
    expect(wrapper.text()).toContain('Koneksi terputus')
    expect(wrapper.text()).toContain('Ulangi')
  })
})
