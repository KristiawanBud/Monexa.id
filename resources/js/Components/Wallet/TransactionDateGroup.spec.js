import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import TransactionDateGroup from './TransactionDateGroup.vue'

const transactions = [
  { id: 1, type: 'expense', amount: 25000, note: 'Makan siang' },
  { id: 2, type: 'income', amount: 500000, note: 'Gaji' },
]

describe('TransactionDateGroup', () => {
  it('renders the date label and one row per transaction', () => {
    const wrapper = mount(TransactionDateGroup, {
      props: { label: 'Hari Ini', transactions },
    })
    expect(wrapper.text()).toContain('Hari Ini')
    expect(wrapper.findAll('.tx-item')).toHaveLength(2)
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('bubbles up item-click with the clicked transaction', async () => {
    const wrapper = mount(TransactionDateGroup, {
      props: { label: 'Hari Ini', transactions },
    })
    await wrapper.findAll('.tx-item')[1].trigger('click')
    expect(wrapper.emitted('item-click')[0]).toEqual([transactions[1]])
  })
})
