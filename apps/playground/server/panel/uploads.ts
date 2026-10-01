import type { IncomingMessage, ServerResponse } from 'node:http'
import { randomUUID } from 'node:crypto'

/**
 * The four requests of the chunked uploads (`module-admin`, §4 of the video spec), in memory.
 *
 * Before the router, like the other uploads: the pieces are bytes rather than JSON, and the
 * answers carry `Upload-Offset` as a header. The advised piece is small on purpose — a clip of a
 * few megabytes then takes long enough to see the bar move, to pause it and to resume it.
 */

interface Purpose {
  /** Empty — any type: the exchange decides by the extension, and its reader refuses the rest. */
  types: string[]
  maxBytes: number
}

interface Session {
  id: string
  purpose: string
  name: string
  size: number
  type: string
  fingerprint: string
  pieces: Buffer[]
  offset: number
}

/* What the catalogue registers (V2): the same types and the default limit as its config. */
const PURPOSES: Record<string, Purpose> = {
  'catalog.video': { types: ['video/mp4', 'video/webm'], maxBytes: 2048 * 1048576 },
  'catalog.exchange': { types: [], maxBytes: 200 * 1048576 },
}

const CHUNK = 256 * 1024
/* Per piece, so that a small file still takes a few seconds — long enough to pause. */
const PIECE_DELAY = 400

const sessions = new Map<string, Session>()

export type Claimed = { name: string; type: string; bytes: Buffer }

/**
 * The finished file, taken away by the module it was for (`Uploads::claim`): gone from here
 * afterwards. A string is the refusal, as `errors.upload` says it.
 */
export function claimUpload(id: string, purpose: string): Claimed | 'missing' | 'refused' {
  const session = sessions.get(id)

  if (session === undefined) return 'missing'
  if (session.purpose !== purpose || session.offset < session.size) return 'refused'

  sessions.delete(id)

  return { name: session.name, type: session.type, bytes: Buffer.concat(session.pieces) }
}

/**
 * The finished file read where it lies, without taking it (`ExchangeFiles::peek`): the exchange
 * looks at a file before the import claims it.
 */
export function peekUpload(id: string, purpose: string): Claimed | 'missing' | 'refused' {
  const session = sessions.get(id)

  if (session === undefined) return 'missing'
  if (session.purpose !== purpose || session.offset < session.size) return 'refused'

  return { name: session.name, type: session.type, bytes: Buffer.concat(session.pieces) }
}

/** Answers the request when it is one of the four; `false` leaves it to whoever is next. */
export function handleUploads(
  request: IncomingMessage,
  response: ServerResponse,
  url: URL,
): boolean {
  const method = (request.method ?? 'GET').toUpperCase()

  if (url.pathname === '/api/cms/uploads' && method === 'POST') {
    void create(request, response)

    return true
  }

  const one = url.pathname.match(/^\/api\/cms\/uploads\/([0-9a-f-]+)$/)

  if (one === null) return false

  const session = sessions.get(one[1]!)

  if (session === undefined) {
    json(response, 404, { message: 'No such upload.' })

    return true
  }

  switch (method) {
    case 'GET':
    case 'HEAD':
      json(response, 200, { data: shape(session) }, session.offset)

      return true
    case 'PATCH':
      void piece(request, response, session)

      return true
    case 'DELETE':
      sessions.delete(session.id)
      response.statusCode = 204
      response.end()

      return true
    default:
      return false
  }
}

async function create(request: IncomingMessage, response: ServerResponse): Promise<void> {
  let body: Record<string, unknown> = {}

  try {
    body = JSON.parse((await raw(request)).toString('utf8') || '{}') as Record<string, unknown>
  } catch {
    // An empty body fails on the purpose below, as a refusal and not a crash.
  }

  const purposeKey = String(body.purpose ?? '')
  const purpose = PURPOSES[purposeKey]
  const size = Number(body.size)
  const type = String(body.type ?? '')

  if (purpose === undefined) {
    return refuse(response, 'purpose', 'Nothing takes uploads for this purpose.')
  }

  if (purpose.types.length > 0 && !purpose.types.includes(type)) {
    return refuse(response, 'type', 'This kind of file is not taken here.')
  }

  if (!Number.isInteger(size) || size <= 0 || size > purpose.maxBytes) {
    return refuse(response, 'size', 'The file is larger than allowed.')
  }

  const fingerprint = String(body.fingerprint ?? '')
  const found = [...sessions.values()].find(
    (one) => one.fingerprint === fingerprint && one.purpose === purposeKey,
  )

  if (found !== undefined) {
    json(response, 200, { data: shape(found) }, found.offset)

    return
  }

  const session: Session = {
    id: randomUUID(),
    purpose: purposeKey,
    name: String(body.name ?? 'file'),
    size,
    type,
    fingerprint,
    pieces: [],
    offset: 0,
  }

  sessions.set(session.id, session)
  json(response, 201, { data: shape(session) }, 0)
}

async function piece(
  request: IncomingMessage,
  response: ServerResponse,
  session: Session,
): Promise<void> {
  const bytes = await raw(request)
  const from = Number(request.headers['upload-offset'])

  await new Promise((resolve) => setTimeout(resolve, PIECE_DELAY))

  // Cancelled while the piece was on its way.
  if (!sessions.has(session.id)) {
    json(response, 404, { message: 'No such upload.' })

    return
  }

  if (from !== session.offset) {
    json(response, 409, { message: 'The piece is not where the file ends.' }, session.offset)

    return
  }

  const room = session.size - session.offset
  const taken = bytes.subarray(0, room)

  session.pieces.push(taken)
  session.offset += taken.length

  response.setHeader('Upload-Offset', String(session.offset))
  response.statusCode = 204
  response.end()
}

function shape(session: Session) {
  return { id: session.id, offset: session.offset, size: session.size, chunk_size: CHUNK }
}

function refuse(response: ServerResponse, field: string, message: string): void {
  json(response, 422, { message, errors: { [field]: [message] } })
}

function json(response: ServerResponse, status: number, body: unknown, offset?: number): void {
  response.statusCode = status
  response.setHeader('Content-Type', 'application/json; charset=utf-8')

  if (offset !== undefined) response.setHeader('Upload-Offset', String(offset))

  response.end(JSON.stringify(body))
}

function raw(request: IncomingMessage): Promise<Buffer> {
  return new Promise((resolve, reject) => {
    const chunks: Buffer[] = []

    request.on('data', (chunk: Buffer) => chunks.push(chunk))
    request.on('end', () => resolve(Buffer.concat(chunks)))
    request.on('error', reject)
  })
}
