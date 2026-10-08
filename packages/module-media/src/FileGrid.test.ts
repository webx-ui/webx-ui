import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { vWxSelect } from '@webx-ui/core'
import FileGrid from './FileGrid.vue'
import type { MediaApi } from './api'
import type { MediaFile } from './types'

/*
 * Names in the library are titles without an extension, so the grid is the one place the
 * format can be read off a file — and a JPEG and a PNG of the same photo look alike.
 */
function file(id: number, extension: string, mime: string): MediaFile {
  return {
    id,
    directory_id: 1,
    name: `File ${id}`,
    file_name: `file-${id}.${extension}`,
    extension,
    mime,
    type: mime.startsWith('image/') ? 'image' : 'document',
    size: 2048,
    width: mime.startsWith('image/') ? 800 : null,
    height: mime.startsWith('image/') ? 600 : null,
    path: `media/aa/bb/uuid-${id}.${extension}`,
    url: `/storage/media/aa/bb/uuid-${id}.${extension}`,
    thumb: null,
    source: null,
    editable: false,
    has_original: false,
    duplicate: false,
    created_at: null,
  }
}

const api = { thumb: (f: MediaFile) => `/thumb/${f.id}` } as unknown as MediaApi

function grid(files: MediaFile[]) {
  return mount(FileGrid, {
    props: { files, api },
    global: { directives: { 'wx-select': vWxSelect } },
  })
}

describe('FileGrid', () => {
  it('puts the extension over every picture, captions untouched', () => {
    const wrapper = grid([file(1, 'jpg', 'image/jpeg'), file(2, 'webp', 'image/webp')])

    const badges = wrapper.findAll('.wx-file-card__badge').map((badge) => badge.text())

    expect(badges).toEqual(['jpg', 'webp'])
    expect(wrapper.findAll('.wx-file-card__name').map((name) => name.text())).toEqual([
      'File 1',
      'File 2',
    ])
  })

  it('writes the extension into the placeholder of a file with no preview', () => {
    const wrapper = grid([file(3, 'pdf', 'application/pdf')])

    expect(wrapper.find('.wx-file-card__badge').exists()).toBe(false)
    expect(wrapper.get('.wx-file-card__extension').text()).toBe('pdf')
  })

  it('says what the file is in the tip over its card', () => {
    const wrapper = grid([file(1, 'png', 'image/png')])

    expect(wrapper.get('.wx-file-card').attributes('title')).toBe(
      'PNG · image/png · 800×600 · 2.0 KB',
    )
  })
})
