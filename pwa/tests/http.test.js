import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

function jsonResponse(status, body) {
  return new Response(body === null ? null : JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

describe('http client', () => {
  let http, ApiError, onUnauthorized

  beforeEach(async () => {
    vi.resetModules()
    document.cookie = 'XSRF-TOKEN=abc%3D%3D; path=/'
    ;({ http, ApiError, onUnauthorized } = await import('@/api/http'))
  })

  afterEach(() => vi.restoreAllMocks())

  it('fetches the CSRF cookie once and sends the token on writes', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(jsonResponse(200, { data: 1 }))
      .mockResolvedValueOnce(jsonResponse(200, { data: 2 }))

    await http.post('/api/v1/x', { a: 1 })
    await http.post('/api/v1/y', { b: 2 })

    expect(fetchMock.mock.calls.filter(([url]) => url === '/sanctum/csrf-cookie')).toHaveLength(1)
    const [, options] = fetchMock.mock.calls[1]
    expect(options.headers['X-XSRF-TOKEN']).toBe('abc==')
    expect(options.credentials).toBe('same-origin')
  })

  it('refreshes an expired CSRF token once and retries', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(jsonResponse(419, { message: 'CSRF token mismatch.' }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(jsonResponse(200, { data: 'ok' }))

    await expect(http.post('/api/v1/x', {})).resolves.toEqual({ data: 'ok' })
    expect(fetchMock).toHaveBeenCalledTimes(4)
  })

  it('turns validation errors into ApiError with field messages', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
      jsonResponse(422, { message: 'Invalid', errors: { room_id: ['اختر مكان المشكلة.'] } }),
    )

    const error = await http.get('/api/v1/x').catch((e) => e)
    expect(error).toBeInstanceOf(ApiError)
    expect(error.status).toBe(422)
    expect(error.field('room_id')).toBe('اختر مكان المشكلة.')
    expect(error.field('other')).toBeNull()
  })

  it('reports 401 to the unauthorized handler', async () => {
    const handler = vi.fn()
    onUnauthorized(handler)
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse(401, { message: 'Unauthenticated.' }))

    await expect(http.get('/api/v1/auth/me')).rejects.toMatchObject({ status: 401 })
    expect(handler).toHaveBeenCalledOnce()
  })

  it('reports network failures as status 0', async () => {
    vi.spyOn(globalThis, 'fetch').mockRejectedValueOnce(new TypeError('Failed to fetch'))

    await expect(http.get('/api/v1/x')).rejects.toMatchObject({ status: 0 })
  })
})
