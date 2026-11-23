/**
 * Thin fetch wrapper for the Laravel API using Sanctum SPA (cookie) auth:
 * the session lives in an HttpOnly cookie, and every write sends the
 * XSRF-TOKEN cookie back as a header.
 */

export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }

  /** First validation message for a field, if any. */
  field(name) {
    return this.errors?.[name]?.[0] ?? null
  }
}

let csrfReady = null
let unauthorizedHandler = () => {}
let localeProvider = () => 'ar'

export function onUnauthorized(handler) {
  unauthorizedHandler = handler
}

export function useLocaleHeader(provider) {
  localeProvider = provider
}

function readCookie(name) {
  const match = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`))
  return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : null
}

export function ensureCsrfCookie(force = false) {
  if (force || !csrfReady) {
    csrfReady = fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).catch((error) => {
      csrfReady = null
      throw error
    })
  }
  return csrfReady
}

async function send(method, url, body, retried = false) {
  const isWrite = method !== 'GET'
  if (isWrite) await ensureCsrfCookie()

  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'Accept-Language': localeProvider(),
  }
  const xsrf = readCookie('XSRF-TOKEN')
  if (xsrf) headers['X-XSRF-TOKEN'] = xsrf

  let payload
  if (body instanceof FormData) {
    payload = body
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }

  let response
  try {
    response = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' })
  } catch {
    throw new ApiError(0, 'network')
  }

  // Expired CSRF token (tab left open for hours): refresh it once and retry.
  if (response.status === 419 && !retried) {
    await ensureCsrfCookie(true)
    return send(method, url, body, true)
  }

  if (response.status === 204) return null

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    if (response.status === 401) unauthorizedHandler()
    throw new ApiError(response.status, data.message || response.statusText, data.errors || {})
  }

  return data
}

export const http = {
  get: (url) => send('GET', url),
  post: (url, body) => send('POST', url, body),
}
