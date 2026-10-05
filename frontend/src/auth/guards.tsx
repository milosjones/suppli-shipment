import { Building2, Ship } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link, Navigate, Outlet, useLocation } from 'react-router'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/ui/feedback'
import { useAuth } from '@/auth/useAuth'

export function RequireAuth({ children }: { children: ReactNode }) {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'loading') {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-slate-50">
        <span className="flex size-12 animate-pulse items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-700 shadow-lg shadow-brand-900/20">
          <Ship className="size-6 text-white" />
        </span>
        <p className="text-sm text-slate-500">Loading your workspace…</p>
      </div>
    )
  }

  if (status === 'guest') {
    return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />
  }

  return children
}

export function RequireCompany() {
  const { companyId, user } = useAuth()

  if (companyId === null) {
    return (
      <div className="card mt-10">
        <EmptyState
          icon={Building2}
          title="Select a company"
          description={
            user?.permissions.select_company
              ? 'As super admin you can work in any company. Choose one in the sidebar to see its shipments, reports and API clients.'
              : 'Your account is not linked to a company. Ask an administrator to add you to one.'
          }
          action={
            user?.permissions.manage_companies && (
              <Link to="/admin/companies">
                <Button variant="outline" icon={Building2}>
                  Manage companies
                </Button>
              </Link>
            )
          }
        />
      </div>
    )
  }

  return <Outlet />
}
