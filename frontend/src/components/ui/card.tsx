import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

export function Card({ className, children }: { className?: string; children: ReactNode }) {
  return <section className={cn('card', className)}>{children}</section>
}

export function CardHeader({
  title,
  description,
  icon: Icon,
  actions,
  className,
}: {
  title: ReactNode
  description?: ReactNode
  icon?: LucideIcon
  actions?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4', className)}>
      <div className="flex min-w-0 items-start gap-3">
        {Icon && (
          <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
            <Icon className="size-4" aria-hidden />
          </span>
        )}
        <div className="min-w-0">
          <h2 className="text-sm font-semibold text-slate-900">{title}</h2>
          {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
        </div>
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}

export function StatTile({
  label,
  value,
  hint,
  icon: Icon,
  tone = 'brand',
  loading = false,
}: {
  label: string
  value: ReactNode
  hint?: ReactNode
  icon: LucideIcon
  tone?: 'brand' | 'sky' | 'indigo' | 'amber' | 'emerald' | 'rose' | 'orange' | 'slate'
  loading?: boolean
}) {
  const tones = {
    brand: 'bg-brand-50 text-brand-600',
    sky: 'bg-sky-50 text-sky-600',
    indigo: 'bg-indigo-50 text-indigo-600',
    amber: 'bg-amber-50 text-amber-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    rose: 'bg-rose-50 text-rose-600',
    orange: 'bg-orange-50 text-orange-600',
    slate: 'bg-slate-100 text-slate-600',
  }

  return (
    <div className="card flex items-start gap-4 p-5">
      <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl', tones[tone])}>
        <Icon className="size-5" aria-hidden />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-sm text-slate-500">{label}</p>
        {loading ? (
          <div className="skeleton mt-2 h-7 w-16" />
        ) : (
          <p className="tabular mt-1 text-2xl font-semibold tracking-tight text-slate-900">{value}</p>
        )}
        {hint && !loading && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
      </div>
    </div>
  )
}
