import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CardDompet from './CardDompet.vue'

const wallet = {
  id: 1,
  display_name: 'BCA Utama',
  type: 'both',
  balance: 2500000,
  bank_color: '#0057B8',
  bank_initial: 'BCA',
  logo_url: null,
  is_saham: false,
}

describe('CardDompet', () => {
  it('renders the wallet name and formatted balance', () => {
    const wrapper = mount(CardDompet, { props: { wallet } })
    expect(wrapper.text()).toContain('BCA Utama')
    expect(wrapper.text()).toContain('Rp')
    expect(wrapper.html()).toMatchSnapshot()
  })

  it('masks the balance when balanceHidden is true', () => {
    const wrapper = mount(CardDompet, { props: { wallet, balanceHidden: true } })
    expect(wrapper.find('.hidden-text').exists()).toBe(true)
  })

  it('emits click with the wallet payload', async () => {
    const wrapper = mount(CardDompet, { props: { wallet } })
    await wrapper.find('.wallet-card').trigger('click')
    expect(wrapper.emitted('click')[0]).toEqual([wallet])
  })
})
