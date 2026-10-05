import { cn } from '@/lib/cn'
import { STATUS_ORDER, STATUS_STYLES } from '@/lib/status'
import type { StatusCounts } from '@/lib/types'

export function StatusBar({ counts, className, showLegend = false }: { counts: StatusCounts; className?: string; showLegend?: boolean }) {
  const total = STATUS_ORDER.reduce((sum, status) => sum + (counts[status] ?? 0), 0)

  return (
    <div className={className}>
      <div className="flex h-1.5 w-full overflow-hidden rounded-full bg-slate-100" title={legendText(counts)}>
        {total > 0 &&
          STATUS_ORDER.map((status) => {
            const count = counts[status] ?? 0
            return count > 0 ? (
              <div key={status} className={cn('h-full', STATUS_STYLES[status].bar)} style={{ width: `${(count / total) * 100}%` }} />
            ) : null
          })}
      </div>
      {showLegend && (
        <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1">
          {STATUS_ORDER.filter((status) => (counts[status] ?? 0) > 0).map((status) => (
            <span key={status} className="inline-flex items-center gap-1.5 text-xs text-slate-600">
              <span className={cn('size-2 rounded-full', STATUS_STYLES[status].dot)} />
              {STATUS_STYLES[status].label}
              <span className="tabular font-medium text-slate-900">{counts[status]}</span>
            </span>
          ))}
        </div>
      )}
    </div>
  )
}

function legendText(counts: StatusCounts): string {
  return STATUS_ORDER.filter((status) => counts[status])
    .map((status) => `${STATUS_STYLES[status].label}: ${counts[status]}`)
    .join(' · ')
}
