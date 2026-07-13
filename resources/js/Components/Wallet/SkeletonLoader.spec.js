import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SkeletonLoader from './SkeletonLoader.vue'

describe('SkeletonLoader', () => {
  it('renders the list-item variant by default', () => {
    const wrapper = mount(SkeletonLoader)
    expect(wrapper.classes()).toContain('skeleton-list-item')
    expect(wrapper.find('.sk-avatar').exists()).toBe(true)
    expect(wrapper.html()).toMatchSnapshot()
  })

  it.each(['card', 'hero'])('renders the %s variant', (variant) => {
    const wrapper = mount(SkeletonLoader, { props: { variant } })
    expect(wrapper.classes()).toContain(`skeleton-${variant}`)
  })
})
