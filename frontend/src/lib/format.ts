const dateFormat = new Intl.DateTimeFormat('nb-NO', { day: '2-digit', month: '2-digit', year: 'numeric' })
const dateTimeFormat = new Intl.DateTimeFormat('nb-NO', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})
const numberFormat = new Intl.NumberFormat('nb-NO', { maximumFractionDigits: 1 })

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  return dateFormat.format(fromIsoDate(value))
}

export function formatDateTime(value: string | null | undefined): string {
  return value ? dateTimeFormat.format(new Date(value)) : '—'
}

export function formatNumber(value: number | null | undefined): string {
  return value === null || value === undefined ? '—' : numberFormat.format(value)
}

export function formatWeight(kg: number | null | undefined): string {
  if (kg === null || kg === undefined) return '—'
  return kg >= 1000 ? `${numberFormat.format(kg / 1000)} t` : `${numberFormat.format(kg)} kg`
}

export function formatDays(days: number): string {
  const abs = Math.abs(days)
  return `${abs} day${abs === 1 ? '' : 's'}`
}

export function formatShift(days: number | null | undefined): string {
  if (!days) return 'on time'
  return `${days > 0 ? '+' : '−'}${formatDays(days)}`
}

export function relativeTime(value: string): string {
  const diff = Date.now() - new Date(value).getTime()
  const future = diff < 0
  const label = (text: string) => (future ? `in ${text}` : `${text} ago`)
  const minutes = Math.round(Math.abs(diff) / 60000)
  if (minutes < 1) return future ? 'in a moment' : 'just now'
  if (minutes < 60) return label(`${minutes} min`)
  const hours = Math.round(minutes / 60)
  if (hours < 24) return label(`${hours} h`)
  const days = Math.round(hours / 24)
  if (days < 31) return label(`${days} day${days === 1 ? '' : 's'}`)
  return formatDateTime(value)
}

export function daysSince(value: string | null | undefined): number {
  if (!value) return 0
  return Math.round((Date.parse(todayIso()) - Date.parse(value.slice(0, 10))) / 86_400_000)
}

export function todayIso(): string {
  return toIsoDate(new Date())
}

export function addDaysIso(days: number, from: string = todayIso()): string {
  const date = fromIsoDate(from)
  date.setDate(date.getDate() + days)
  return toIsoDate(date)
}

export function formatDateRange(from: string | null, to: string | null): string {
  if (!from && !to) return ''
  if (!from) return `– ${formatDate(to)}`
  if (!to || from === to) return formatDate(from)
  const start = from.slice(0, 4) === to.slice(0, 4) ? formatDate(from).slice(0, 5) : formatDate(from)
  return `${start} – ${formatDate(to)}`
}

export function monthRange(offset: number): { from: string; to: string } {
  const today = fromIsoDate(todayIso())
  return {
    from: toIsoDate(new Date(today.getFullYear(), today.getMonth() + offset, 1)),
    to: toIsoDate(new Date(today.getFullYear(), today.getMonth() + offset + 1, 0)),
  }
}

export function fromIsoDate(value: string): Date {
  const [year, month, day] = value.slice(0, 10).split('-').map(Number)
  return new Date(year, month - 1, day)
}

export function toIsoDate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}
