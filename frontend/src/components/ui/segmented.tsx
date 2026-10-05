import type { LucideIcon } from 'lucide-react'
import { cn } from '@/lib/cn'

export interface SegmentOption<T extends string> {
  value: T
  label: string
  icon?: LucideIcon
  count?: number
}

export function Segmented<T extends string>({
  options,
  value,
  onChange,
  className,
}: {
  options: SegmentOption<T>[]
  value: T
  onChange: (value: T) => void
  className?: string
}) {
  return (
    <div className={cn('inline-flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1', className)} role="tablist">
      {options.map((option) => {
        const active = option.value === value
        const Icon = option.icon

        return (
          <button
            key={option.value}
            type="button"
            role="tab"
            aria-selected={active}
            onClick={() => onChange(option.value)}
            className={cn(
              'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
              active ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-slate-800',
            )}
          >
            {Icon && <Icon className={cn('size-4', active ? 'text-brand-600' : 'text-slate-400')} aria-hidden />}
            {option.label}
            {option.count !== undefined && (
              <span
                className={cn(
                  'tabular rounded-full px-1.5 text-xs',
                  active ? 'bg-brand-50 text-brand-700' : 'bg-slate-200/70 text-slate-500',
                )}
              >
                {option.count}
              </span>
            )}
          </button>
        )
      })}
    </div>
  )
}
