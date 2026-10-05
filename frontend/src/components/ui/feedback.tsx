import { CircleAlert, LoaderCircle, RefreshCw, type LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { ApiError } from '@/lib/api'
import { cn } from '@/lib/cn'
import { Button } from '@/components/ui/button'

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn('skeleton h-4', className)} aria-hidden />
}

export function Spinner({ className }: { className?: string }) {
  return <LoaderCircle className={cn('size-5 animate-spin text-brand-600', className)} aria-label="Loading" />
}

export function TableSkeleton({ rows = 6, columns = 6 }: { rows?: number; columns?: number }) {
  return (
    <div className="divide-y divide-slate-100" aria-busy>
      {Array.from({ length: rows }, (_, row) => (
        <div key={row} className="flex items-center gap-6 px-5 py-4">
          {Array.from({ length: columns }, (_, column) => (
            <Skeleton key={column} className={cn('h-4', column === 0 ? 'w-28' : column % 3 === 0 ? 'w-16' : 'flex-1')} />
          ))}
        </div>
      ))}
    </div>
  )
}

export function EmptyState({
  icon: Icon,
  title,
  description,
  action,
  className,
}: {
  icon: LucideIcon
  title: string
  description?: ReactNode
  action?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('flex flex-col items-center justify-center px-6 py-14 text-center', className)}>
      <span className="flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
        <Icon className="size-6" aria-hidden />
      </span>
      <h3 className="mt-4 text-sm font-semibold text-slate-900">{title}</h3>
      {description && <p className="mt-1 max-w-sm text-sm text-slate-500">{description}</p>}
      {action && <div className="mt-5">{action}</div>}
    </div>
  )
}

export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  const message = error instanceof ApiError ? error.message : 'Something went wrong while loading the data.'

  return (
    <EmptyState
      icon={CircleAlert}
      title="Could not load the data"
      description={message}
      action={
        onRetry && (
          <Button variant="outline" icon={RefreshCw} onClick={onRetry}>
            Try again
          </Button>
        )
      }
    />
  )
}
