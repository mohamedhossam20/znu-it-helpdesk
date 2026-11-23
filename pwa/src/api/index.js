import { http } from './http'

const v1 = '/api/v1'

function ticketForm(fields, photos = []) {
  const form = new FormData()
  for (const [key, value] of Object.entries(fields)) {
    if (value === null || value === undefined || value === '') continue
    form.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value)
  }
  photos.forEach((file) => form.append('photos[]', file))
  return form
}

export const api = {
  login: (email, password, remember) => http.post(`${v1}/auth/login`, { email, password, remember }),
  logout: () => http.post(`${v1}/auth/logout`),
  me: () => http.get(`${v1}/auth/me`),

  context: () => http.get(`${v1}/me/context`),
  buildings: () => http.get(`${v1}/buildings`),
  rooms: (floorId) => http.get(`${v1}/floors/${floorId}/rooms`),
  roomAssets: (roomId) => http.get(`${v1}/rooms/${roomId}/assets`),
  roomByQr: (token) => http.get(`${v1}/rooms/by-qr/${encodeURIComponent(token)}`),
  categories: () => http.get(`${v1}/categories`),

  notifications: (page = 1) => http.get(`${v1}/notifications?page=${page}`),
  markNotificationsRead: (ids) => http.post(`${v1}/notifications/read`, ids === 'all' ? { all: true } : { ids }),

  tickets: (status, page = 1) => http.get(`${v1}/tickets?status=${status}&page=${page}`),
  ticket: (number) => http.get(`${v1}/tickets/${encodeURIComponent(number)}`),
  createTicket: (fields, photos) => http.post(`${v1}/tickets`, ticketForm(fields, photos)),
  comment: (number, body, photos) => http.post(`${v1}/tickets/${encodeURIComponent(number)}/comments`, ticketForm({ body }, photos)),
  cancel: (number, note) => http.post(`${v1}/tickets/${encodeURIComponent(number)}/cancel`, { note }),
  confirm: (number) => http.post(`${v1}/tickets/${encodeURIComponent(number)}/confirm`, {}),
  reopen: (number, note) => http.post(`${v1}/tickets/${encodeURIComponent(number)}/reopen`, { note }),
  rate: (number, rating, comment) => http.post(`${v1}/tickets/${encodeURIComponent(number)}/rating`, { rating, comment }),
}
