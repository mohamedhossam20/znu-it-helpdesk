import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { i18n, applyLocale } from '@/i18n'
import CategoryPicker from '@/components/CategoryPicker.vue'
import PhotoPicker from '@/components/PhotoPicker.vue'
import { iconFor, locationLabel } from '@/utils/format'

const categories = [
  {
    id: 1, name_ar: 'صوت', name_en: 'Audio', icon: 'heroicon-o-speaker-wave',
    children: [{ id: 11, name_ar: 'الميكروفون', name_en: 'Microphone', children: [] }],
  },
  { id: 2, name_ar: 'أخرى', name_en: 'Other', icon: 'heroicon-o-question-mark-circle', children: [] },
]

const options = { global: { plugins: [i18n] } }

describe('CategoryPicker', () => {
  it('asks for the exact problem before selecting a main type with sub-types', async () => {
    const wrapper = mount(CategoryPicker, { props: { categories, modelValue: null }, ...options })

    await wrapper.findAll('button').find((b) => b.text().includes('صوت')).trigger('click')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([null])
    expect(wrapper.text()).toContain('الميكروفون')

    await wrapper.findAll('button').find((b) => b.text().includes('الميكروفون')).trigger('click')
    const picked = wrapper.emitted('update:modelValue').at(-1)[0]
    expect(picked.id).toBe(11)
    expect(picked.parent.id).toBe(1)
  })

  it('selects a main type without sub-types directly', async () => {
    const wrapper = mount(CategoryPicker, { props: { categories, modelValue: null }, ...options })

    await wrapper.findAll('button').find((b) => b.text().includes('أخرى')).trigger('click')
    expect(wrapper.emitted('update:modelValue').at(-1)[0]).toMatchObject({ id: 2, parent: null })
  })
})

describe('PhotoPicker', () => {
  function pick(wrapper, files) {
    const input = wrapper.find('input[type=file]')
    Object.defineProperty(input.element, 'files', { value: files, configurable: true })
    return input.trigger('change')
  }

  it('drops files over the size limit and beyond the count limit', async () => {
    globalThis.URL.createObjectURL ??= () => 'blob:x'
    globalThis.URL.revokeObjectURL ??= () => {}

    const small = new File(['x'], 'a.jpg', { type: 'image/jpeg' })
    const big = new File([new Uint8Array(2048)], 'b.jpg', { type: 'image/jpeg' })
    const wrapper = mount(PhotoPicker, { props: { modelValue: [], max: 1, maxBytes: 1024 }, ...options })

    await pick(wrapper, [big, small, small])

    expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual([small])
    expect(wrapper.text()).toMatch(/1/)
  })
})

describe('format helpers', () => {
  const location = {
    building: { name_ar: 'المبنى الرئيسي', name_en: 'Main Building' },
    floor: { name_ar: 'الدور الثاني', name_en: 'Second floor' },
    room: { name_ar: 'مكتب 204', name_en: null },
  }

  it('builds the location label in the current language', () => {
    applyLocale('ar')
    expect(locationLabel(location)).toBe('المبنى الرئيسي › الدور الثاني › مكتب 204')

    applyLocale('en')
    // Falls back to Arabic when there is no English name.
    expect(locationLabel(location)).toBe('Main Building › Second floor › مكتب 204')
    applyLocale('ar')
  })

  it('has a fallback icon', () => {
    expect(iconFor('heroicon-o-wifi')).toBe('📶')
    expect(iconFor('unknown')).toBe('🛠️')
  })
})
