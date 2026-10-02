import type { LightboxSource, LightboxVideo } from './types'

/** What the lightbox plays: a file in a `<video>`, or an address in an `<iframe>`. */
export type ResolvedVideo = { kind: 'file'; src: string } | { kind: 'embed'; src: string }

/** An item with every default filled in, so the template asks no questions. */
export interface ResolvedItem {
  src: string
  alt: string
  caption: string
  thumb: string
  original: string | undefined
  video: ResolvedVideo | undefined
}

const YOUTUBE_HOSTS = new Set([
  'youtube.com',
  'www.youtube.com',
  'm.youtube.com',
  'music.youtube.com',
  'youtu.be',
  'www.youtube-nocookie.com',
  'youtube-nocookie.com',
])

const YOUTUBE_ID = /^[A-Za-z0-9_-]{11}$/

/** The id of a YouTube video in any form of its address — watch, short link, shorts, embed — or `null`. */
export function youTubeId(address: string): string | null {
  let url: URL

  try {
    url = new URL(address.trim())
  } catch {
    return null
  }

  const host = url.hostname.toLowerCase()
  if (!/^https?:$/.test(url.protocol) || !YOUTUBE_HOSTS.has(host)) return null

  if (host === 'youtu.be') {
    const id = url.pathname.split('/')[1] ?? ''
    return YOUTUBE_ID.test(id) ? id : null
  }

  const watched = url.pathname === '/watch' ? url.searchParams.get('v') : null
  if (watched !== null) return YOUTUBE_ID.test(watched) ? watched : null

  const [, kind, id] = url.pathname.split('/')

  return ['shorts', 'embed', 'live'].includes(kind ?? '') && YOUTUBE_ID.test(id ?? '')
    ? (id ?? null)
    : null
}

/*
 * The no-cookie host: a gallery should not hand its visitors to an advertiser for having
 * looked at a product. The player is only created on play, so `autoplay` is what the press
 * of the button asked for rather than a video that starts by itself.
 */
function youTubeEmbed(id: string) {
  return `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0`
}

/** An embed address that starts playing — the press of play has already happened. */
function playing(address: string) {
  try {
    const url = new URL(address, 'https://example.invalid')
    if (!url.searchParams.has('autoplay')) url.searchParams.set('autoplay', '1')
    return url.origin === 'https://example.invalid' ? address : url.toString()
  } catch {
    return address
  }
}

function resolveVideo(video: string | LightboxVideo | undefined): ResolvedVideo | undefined {
  if (!video) return undefined

  if (typeof video === 'string') {
    const id = youTubeId(video)
    return id ? { kind: 'embed', src: youTubeEmbed(id) } : { kind: 'file', src: video }
  }

  const { embed, src } = video
  if (embed) {
    const id = youTubeId(embed)
    return { kind: 'embed', src: id ? youTubeEmbed(id) : playing(embed) }
  }
  if (src) return { kind: 'file', src }

  return undefined
}

export function resolveItem(source: LightboxSource): ResolvedItem {
  const item = typeof source === 'string' ? { src: source } : source
  const src = item.src ?? ''
  const video = resolveVideo(item.video)

  return {
    src,
    alt: item.alt ?? '',
    caption: item.caption ?? item.alt ?? '',
    thumb: item.thumb ?? src,
    /* A video's poster is not what "the original" means; only an address given for it is. */
    original: item.original ?? (video ? undefined : src || undefined),
    video,
  }
}

export function counter(
  text: string | ((index: number, total: number) => string),
  index: number,
  total: number,
) {
  if (typeof text === 'function') return text(index, total)

  return text.replace('{index}', String(index)).replace('{total}', String(total))
}
