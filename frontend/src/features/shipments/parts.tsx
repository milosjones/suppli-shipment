import { ArrowRight, Plane, Ship, Truck } from 'lucide-react'
import { DelayBadge, OverdueBadge } from '@/components/ui/badge'
import { cn } from '@/lib/cn'
import { formatDate } from '@/lib/format'
import type { ShippingMethod } from '@/lib/types'

const METHOD_ICONS = { sea: Ship, air: Plane, road: Truck }

export function MethodIcon({ method, className }: { method: ShippingMethod; className?: string }) {
  const Icon = METHOD_ICONS[method]
  return <Icon className={cn('size-4 text-slate-400', className)} aria-label={method} />
}

export function Route({ origin, destination, method }: { origin: string | null; destination: string; method: ShippingMethod }) {
  return (
    <span className="inline-flex items-center gap-1.5 text-sm whitespace-nowrap text-slate-700">
      <MethodIcon method={method} />
      <span className="text-slate-500">{origin ?? '—'}</span>
      <ArrowRight className="size-3.5 text-slate-300" aria-hidden />
      <span className="font-medium text-slate-900">{destination}</span>
    </span>
  )
}

export function DateWithDelay({
  date,
  original,
  delayDays,
  overdueDays = 0,
  compact = false,
}: {
  date: string | null
  original?: string | null
  delayDays: number
  overdueDays?: number
  compact?: boolean
}) {
  return (
    <div className={cn('flex flex-col gap-1', compact ? 'items-start' : '')}>
      <span className="tabular text-sm text-slate-900">{formatDate(date)}</span>
      {(delayDays !== 0 || overdueDays > 0) && (
        <span className="flex flex-wrap gap-1">
          <DelayBadge days={delayDays} />
          <OverdueBadge days={overdueDays} />
        </span>
      )}
      {!compact && original && original !== date && (
        <span className="tabular text-xs text-slate-400 line-through">{formatDate(original)}</span>
      )}
    </div>
  )
}
