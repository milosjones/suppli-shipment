import { ChevronRight } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router'

export function PageHeader({
  title,
  description,
  actions,
  breadcrumbs,
  badge,
}: {
  title: ReactNode
  description?: ReactNode
  actions?: ReactNode
  breadcrumbs?: { label: string; to?: string }[]
  badge?: ReactNode
}) {
  return (
    <header className="mb-6">
      {breadcrumbs && (
        <nav className="mb-2 flex items-center gap-1 text-sm text-slate-500" aria-label="Breadcrumb">
          {breadcrumbs.map((crumb, index) => (
            <span key={crumb.label} className="flex items-center gap-1">
              {index > 0 && <ChevronRight className="size-3.5 text-slate-400" aria-hidden />}
              {crumb.to ? (
                <Link to={crumb.to} className="hover:text-slate-800">
                  {crumb.label}
                </Link>
              ) : (
                <span className="text-slate-700">{crumb.label}</span>
              )}
            </span>
          ))}
        </nav>
      )}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{title}</h1>
            {badge}
          </div>
          {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
        </div>
        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
      </div>
    </header>
  )
}
