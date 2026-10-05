import { ChevronLeft, ChevronRight } from 'lucide-react'
import type { Paginated } from '@/lib/types'
import { Button } from '@/components/ui/button'

export function Pagination({ meta, onPage }: { meta: Paginated<unknown>['meta']; onPage: (page: number) => void }) {
  if (meta.total === 0) return null

  return (
    <div className="flex items-center justify-between gap-4 border-t border-slate-100 px-5 py-3 text-sm text-slate-500">
      <p className="tabular">
        Showing <span className="font-medium text-slate-700">{meta.from}</span>–
        <span className="font-medium text-slate-700">{meta.to}</span> of{' '}
        <span className="font-medium text-slate-700">{meta.total}</span>
      </p>
      <div className="flex gap-2">
        <Button
          variant="outline"
          size="sm"
          icon={ChevronLeft}
          disabled={meta.current_page <= 1}
          onClick={() => onPage(meta.current_page - 1)}
        >
          Previous
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          Next
          <ChevronRight className="size-3.5" />
        </Button>
      </div>
    </div>
  )
}
