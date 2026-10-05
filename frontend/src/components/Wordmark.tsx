import { cn } from '@/lib/cn'

export function Wordmark({ onDark = true, className }: { onDark?: boolean; className?: string }) {
  return (
    <span className={cn('font-bold tracking-tight', className)}>
      <span className={onDark ? 'text-white' : 'text-slate-900'}>Suppli</span>{' '}
      <span className={cn('font-medium', onDark ? 'text-brand-300' : 'text-brand-600')}>Shipments</span>
    </span>
  )
}
