import { currentLocale, localName } from '@/i18n'

const ICONS = {
  'heroicon-o-computer-desktop': '💻',
  'heroicon-o-device-tablet': '💻',
  'heroicon-o-printer': '🖨️',
  'heroicon-o-document-magnifying-glass': '🖨️',
  'heroicon-o-tv': '🖥️',
  'heroicon-o-film': '📽️',
  'heroicon-o-speaker-wave': '🔊',
  'heroicon-o-microphone': '🎤',
  'heroicon-o-adjustments-horizontal': '🎛️',
  'heroicon-o-wifi': '📶',
  'heroicon-o-server-stack': '🔌',
  'heroicon-o-server': '🗄️',
  'heroicon-o-key': '🔑',
  'heroicon-o-phone': '☎️',
  'heroicon-o-question-mark-circle': '❓',
}

/** Emoji for a heroicon name sent by the API (problem types, device types). */
export function iconFor(name) {
  return ICONS[name] ?? '🛠️'
}

const UNITS = [
  ['year', 365 * 24 * 3600],
  ['month', 30 * 24 * 3600],
  ['week', 7 * 24 * 3600],
  ['day', 24 * 3600],
  ['hour', 3600],
  ['minute', 60],
]

/** "منذ ٥ دقائق" / "5 minutes ago" */
export function timeAgo(value, now = Date.now()) {
  if (!value) return ''
  const seconds = Math.round((new Date(value).getTime() - now) / 1000)
  const format = new Intl.RelativeTimeFormat(currentLocale(), { numeric: 'auto' })

  for (const [unit, size] of UNITS) {
    if (Math.abs(seconds) >= size) return format.format(Math.round(seconds / size), unit)
  }
  return format.format(0, 'minute')
}

export function dateTime(value) {
  if (!value) return ''
  return new Intl.DateTimeFormat(currentLocale() === 'ar' ? 'ar-EG' : 'en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

/** "المبنى الرئيسي › الدور الثاني › مكتب 204" from a location snapshot. */
export function locationLabel(location) {
  if (!location) return ''
  return [location.building, location.floor, location.room].filter(Boolean).map(localName).join(' › ')
}

export function assetLabel(asset) {
  if (!asset) return ''
  return [asset.brand, asset.model].filter(Boolean).join(' ')
}
