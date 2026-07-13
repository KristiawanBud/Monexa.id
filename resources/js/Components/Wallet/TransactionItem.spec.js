import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import TransactionItem from './TransactionItem.vue'

const baseTransaction = {
  id: 1,
  type: 'expense',
  amount: 25000,
  note: 'Makan siang',
  category: 'Makanan',
  category_emoji: '🍔',
  wallet: 'Cash',
  transacted_at_time: '12:30',
}

describe('TransactionItem', () => {
  it('renders note, category and formatted amount', () => {
    const wrapper = mount(TransactionItem, { props: { transaction: baseTransaction } })
    expect(wrapper.text()).toContain('Makan siang')
    expect(wrapper.text()).toContain('Makanan')
    expect(wrapper.find('.tx-amt').classes()).toContain('down')
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('emits click with the transaction payload', async () => {
    const wrapper = mount(TransactionItem, { props: { transaction: baseTransaction } })
    await wrapper.find('.tx-item').trigger('click')
    expect(wrapper.emitted('click')[0]).toEqual([baseTransaction])
  })

  it('marks income transactions with the up style', () => {
    const wrapper = mount(TransactionItem, {
      props: { transaction: { ...baseTransaction, type: 'income' } },
    })
    expect(wrapper.find('.tx-amt').classes()).toContain('up')
  })
})
