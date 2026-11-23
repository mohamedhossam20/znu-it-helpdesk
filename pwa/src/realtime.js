import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { http } from '@/api/http'

/**
 * Laravel Reverb connection. Private channels are authorised through
 * /broadcasting/auth with the same session cookie as the API. When Reverb
 * is not configured or unreachable the app keeps working: pages still
 * refresh on focus and the notification count is polled.
 */
let echo = null

export function connectRealtime() {
  const key = import.meta.env.VITE_REVERB_APP_KEY
  if (echo || !key) return echo

  window.Pusher = Pusher
  const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https'

  echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
      authorize: (socketId, callback) => {
        http
          .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then((data) => callback(null, data))
          .catch((error) => callback(error, null))
      },
    }),
  })

  return echo
}

export function disconnectRealtime() {
  echo?.disconnect()
  echo = null
}

export function realtime() {
  return echo
}
