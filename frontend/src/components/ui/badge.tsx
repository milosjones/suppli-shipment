import { AlertTriangle, Clock } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'
import { formatDays, formatShift } from '@/lib/format'
import { STATUS_STYLES } from '@/lib/status'
import type { CargoStatus } from '@/lib/types'

export function Badge({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
        className,
      )}
    >
      {children}
    </span>
  )
}

export function StatusBadge({ status, className }: { status: CargoStatus; className?: string }) {
  const style = STATUS_STYLES[status]
  const Icon = style.icon

  return (
    <Badge className={cn(style.badge, className)}>
      <Icon className="size-3.5" aria-hidden />
      {style.label}
    </Badge>
  )
}

export function DelayBadge({ days, className }: { days: number; className?: string }) {
  if (!days) return null

  return (
    <Badge
      className={cn(
        days > 0 ? 'bg-orange-50 text-orange-700 ring-orange-600/20' : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        className,
      )}
    >
      <Clock className="size-3" aria-hidden />
      {formatShift(days)}
    </Badge>
  )
}

export function OverdueBadge({ days, label = 'overdue' }: { days: number; label?: string }) {
  if (days <= 0) return null

  return (
    <Badge className="bg-rose-50 text-rose-700 ring-rose-600/20">
      <AlertTriangle className="size-3" aria-hidden />
      {formatDays(days)} {label}
    </Badge>
  )
}

export function CategoryBadge({ code }: { code: 'NP' | 'DF' | null }) {
  if (!code) return null

  return (
    <Badge
      className={
        code === 'NP' ? 'bg-brand-50 text-brand-700 ring-brand-600/20' : 'bg-violet-50 text-violet-700 ring-violet-600/20'
      }
    >
      {code}
    </Badge>
  )
}
