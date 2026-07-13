import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import EmptyState from './EmptyState.vue'

describe('EmptyState', () => {
  it('renders icon, title and optional action', async () => {
    const wrapper = mount(EmptyState, {
      props: { icon: '📝', title: 'Belum ada transaksi', actionLabel: '+ Catat Transaksi' },
    })
    expect(wrapper.text()).toContain('Belum ada transaksi')
    expect(wrapper.find('.empty-action').exists()).toBe(true)
    await wrapper.find('.empty-action').trigger('click')
    expect(wrapper.emitted('action')).toHaveLength(1)
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('hides the action button when no actionLabel is given', () => {
    const wrapper = mount(EmptyState, { props: { title: 'Kosong' } })
    expect(wrapper.find('.empty-action').exists()).toBe(false)
  })
})
