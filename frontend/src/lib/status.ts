import { Anchor, CircleCheck, Clock, Ship, Warehouse, type LucideIcon } from 'lucide-react'
import type { CargoStatus, ChangeSource, StatusCounts } from '@/lib/types'

export const STATUS_ORDER: CargoStatus[] = ['planned', 'in_transit', 'arrived', 'in_storage', 'delivered']

interface StatusStyle {
  label: string
  short: string
  icon: LucideIcon
  badge: string
  dot: string
  bar: string
  chart: string
  soft: string
}

export const STATUS_STYLES: Record<CargoStatus, StatusStyle> = {
  planned: {
    label: 'Planned',
    short: 'Planned',
    icon: Clock,
    badge: 'bg-slate-100 text-slate-700 ring-slate-500/15',
    dot: 'bg-slate-400',
    bar: 'bg-slate-300',
    chart: '#64748b',
    soft: 'bg-slate-50 text-slate-600',
  },
  in_transit: {
    label: 'In transit',
    short: 'Transit',
    icon: Ship,
    badge: 'bg-sky-50 text-sky-700 ring-sky-600/20',
    dot: 'bg-sky-500',
    bar: 'bg-sky-500',
    chart: '#0ea5e9',
    soft: 'bg-sky-50 text-sky-700',
  },
  arrived: {
    label: 'Arrived',
    short: 'Arrived',
    icon: Anchor,
    badge: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    dot: 'bg-indigo-500',
    bar: 'bg-indigo-500',
    chart: '#6366f1',
    soft: 'bg-indigo-50 text-indigo-700',
  },
  in_storage: {
    label: 'In storage',
    short: 'Storage',
    icon: Warehouse,
    badge: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    dot: 'bg-amber-500',
    bar: 'bg-amber-400',
    chart: '#f59e0b',
    soft: 'bg-amber-50 text-amber-800',
  },
  delivered: {
    label: 'Delivered',
    short: 'Delivered',
    icon: CircleCheck,
    badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    dot: 'bg-emerald-500',
    bar: 'bg-emerald-500',
    chart: '#10b981',
    soft: 'bg-emerald-50 text-emerald-700',
  },
}

export const SOURCE_LABELS: Record<ChangeSource, string> = {
  manual: 'Manual',
  api: 'API',
  import: 'Import',
  provider: 'Tracking',
  system: 'Automatic',
}

export function rankOf(status: CargoStatus): number {
  return STATUS_ORDER.indexOf(status)
}

export function progressText(counts: StatusCounts | undefined): string | null {
  if (!counts) return null
  const total = STATUS_ORDER.reduce((sum, status) => sum + (counts[status] ?? 0), 0)
  const delivered = counts.delivered ?? 0

  return delivered > 0 && delivered < total ? `${delivered}/${total} delivered` : null
}
