import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BalanceSummaryCard from './BalanceSummaryCard.vue'

describe('BalanceSummaryCard', () => {
  it('renders total balance and active wallets badge', () => {
    const wrapper = mount(BalanceSummaryCard, {
      props: {
        totalBalance: 1500000,
        activeWalletsCount: 3,
        cashTotal: 500000,
        bankTotal: 700000,
        ewalletTotal: 300000,
      },
    })
    expect(wrapper.text()).toContain('Rp')
    expect(wrapper.text()).toContain('3 Dompet Aktif')
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('masks the balance when balanceHidden is true', () => {
    const wrapper = mount(BalanceSummaryCard, {
      props: { totalBalance: 1500000, balanceHidden: true },
    })
    expect(wrapper.text()).not.toContain('1.500.000')
    expect(wrapper.find('.hidden-text').exists()).toBe(true)
  })
})
