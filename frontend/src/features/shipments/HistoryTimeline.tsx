import { ArrowRight, CalendarClock, History, RotateCcw } from 'lucide-react'
import { DelayBadge } from '@/components/ui/badge'
import { EmptyState, Skeleton } from '@/components/ui/feedback'
import { cn } from '@/lib/cn'
import { formatDate, formatDateTime } from '@/lib/format'
import { SOURCE_LABELS, STATUS_STYLES } from '@/lib/status'
import type { HistoryGroup } from '@/lib/types'

export function HistoryTimeline({ groups, loading }: { groups: HistoryGroup[] | undefined; loading: boolean }) {
  if (loading) {
    return (
      <div className="space-y-5 p-5">
        {Array.from({ length: 4 }, (_, i) => (
          <div key={i} className="flex gap-3">
            <Skeleton className="size-8 rounded-full" />
            <div className="flex-1 space-y-2">
              <Skeleton className="h-4 w-2/3" />
              <Skeleton className="h-3 w-1/3" />
            </div>
          </div>
        ))}
      </div>
    )
  }

  if (!groups || groups.length === 0) {
    return <EmptyState icon={History} title="No history yet" />
  }

  return (
    <ol className="relative px-5 py-4">
      {groups.map((group, index) => {
        const status = group.status_changes[0]
        const Icon = status ? STATUS_STYLES[status.to].icon : CalendarClock
        const tone = status ? STATUS_STYLES[status.to].soft : 'bg-orange-50 text-orange-600'

        return (
          <li key={group.change_group} className="relative flex gap-3 pb-6 last:pb-1">
            {index < groups.length - 1 && <span className="absolute top-9 bottom-0 left-4 w-px bg-slate-200" aria-hidden />}
            <span className={cn('relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white', tone)}>
              <Icon className="size-4" />
            </span>
            <div className="min-w-0 flex-1 pt-0.5">
              <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                <span className="tabular font-medium text-slate-700">{formatDateTime(group.at)}</span>
                <span>·</span>
                <span>{group.user?.name ?? (group.source === 'provider' ? 'Tracking provider' : 'System')}</span>
                <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600">{SOURCE_LABELS[group.source]}</span>
              </div>

              <div className="mt-1.5 space-y-2">
                {group.status_changes.map((change) => (
                  <div key={`${change.from}-${change.to}-${change.is_correction}`} className="text-sm">
                    <p className="flex flex-wrap items-center gap-1.5 text-slate-900">
                      {change.from ? (
                        <>
                          <span className="text-slate-500">{STATUS_STYLES[change.from].label}</span>
                          <ArrowRight className="size-3.5 text-slate-400" />
                        </>
                      ) : (
                        <span className="text-slate-500">Registered as</span>
                      )}
                      <span className="font-medium">{STATUS_STYLES[change.to].label}</span>
                      {change.is_correction && (
                        <span className="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-medium text-rose-700">
                          <RotateCcw className="size-3" /> correction
                        </span>
                      )}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500">
                      {change.items_count} package{change.items_count === 1 ? '' : 's'}
                      {change.containers.length > 0 && ` in ${change.containers.join(', ')}`}
                    </p>
                    {change.reason && <p className="mt-1 text-sm text-slate-600 italic">“{change.reason}”</p>}
                  </div>
                ))}

                {group.date_changes.map((change) => (
                  <div key={`${change.field}-${change.old_date}-${change.new_date}-${change.source}`} className="text-sm">
                    <p className="flex flex-wrap items-center gap-1.5 text-slate-900">
                      <span className="font-medium">{change.field_label}</span>
                      <span className="tabular text-slate-500">{formatDate(change.old_date)}</span>
                      <ArrowRight className="size-3.5 text-slate-400" />
                      <span className="tabular font-medium">{formatDate(change.new_date)}</span>
                      {change.shift_days !== null && <DelayBadge days={change.shift_days} />}
                      {change.source === 'system' && <span className="text-xs text-slate-400">(automatic)</span>}
                    </p>
                    {change.items_count > 0 && (
                      <p className="mt-0.5 text-xs text-slate-500">
                        {change.items_count} package{change.items_count === 1 ? '' : 's'}
                        {change.containers.length > 0 && ` in ${change.containers.join(', ')}`}
                      </p>
                    )}
                    <p className="mt-1 text-sm text-slate-600 italic">“{change.reason}”</p>
                  </div>
                ))}
              </div>
            </div>
          </li>
        )
      })}
    </ol>
  )
}
