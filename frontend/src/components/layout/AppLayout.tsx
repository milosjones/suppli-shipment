import {
  BarChart3,
  Building2,
  ChevronsUpDown,
  FileSpreadsheet,
  KeyRound,
  Lock,
  LogOut,
  Menu,
  Package,
  ScanSearch,
  Users,
  X,
  type LucideIcon,
} from 'lucide-react'
import { Suspense, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router'
import { useAuth } from '@/auth/useAuth'
import { ChangePasswordDialog } from '@/features/auth/ChangePasswordDialog'
import { cn } from '@/lib/cn'
import { initials } from '@/lib/format'
import { useCompanies } from '@/lib/queries'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Skeleton } from '@/components/ui/feedback'
import { Wordmark } from '@/components/Wordmark'
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet'

interface NavItem {
  to: string
  label: string
  icon: LucideIcon
}

export function AppLayout() {
  const [mobileOpen, setMobileOpen] = useState(false)
  const location = useLocation()

  return (
    <div className="min-h-screen">
      <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
        <SheetContent
          side="left"
          showCloseButton={false}
          aria-describedby={undefined}
          className="gap-0 border-none bg-ink-900 p-0 data-[side=left]:w-72 lg:hidden"
        >
          <SheetTitle className="sr-only">Navigation</SheetTitle>
          <button
            type="button"
            onClick={() => setMobileOpen(false)}
            className="absolute top-4 -right-11 rounded-md p-1.5 text-white/80 hover:text-white"
            aria-label="Close menu"
          >
            <X className="size-6" />
          </button>
          <Sidebar onNavigate={() => setMobileOpen(false)} />
        </SheetContent>
      </Sheet>

      <div className="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64">
        <Sidebar />
      </div>

      <div className="lg:pl-64">
        <div className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-slate-200/80 bg-white/85 px-4 backdrop-blur lg:hidden">
          <button type="button" onClick={() => setMobileOpen(true)} className="-ml-1 rounded-md p-1.5 text-slate-600" aria-label="Open menu">
            <Menu className="size-6" />
          </button>
          <Wordmark onDark={false} className="text-base" />
        </div>
        <main key={location.pathname} className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <Suspense fallback={<PageSkeleton />}>
            <Outlet />
          </Suspense>
        </main>
      </div>
    </div>
  )
}

function PageSkeleton() {
  return (
    <div aria-busy>
      <Skeleton className="mb-2 h-8 w-56" />
      <Skeleton className="mb-8 h-4 w-80" />
      <div className="card space-y-4 p-5">
        {[0, 1, 2, 3, 4].map((i) => (
          <Skeleton key={i} className="h-9 w-full" />
        ))}
      </div>
    </div>
  )
}

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const { user } = useAuth()
  const canManageUsers = user?.permissions.manage_users ?? false
  const isSuperAdmin = user?.permissions.manage_companies ?? false

  const main: NavItem[] = [
    { to: '/shipments', label: 'Shipments', icon: Package },
    { to: '/lookup', label: 'Lookup', icon: ScanSearch },
    { to: '/reports', label: 'Reports', icon: BarChart3 },
    { to: '/imports', label: 'Excel import', icon: FileSpreadsheet },
    ...(canManageUsers ? [{ to: '/api-clients', label: 'API clients', icon: KeyRound }] : []),
  ]

  const admin: NavItem[] = [
    ...(canManageUsers ? [{ to: '/admin/users', label: 'Users', icon: Users }] : []),
    ...(isSuperAdmin ? [{ to: '/admin/companies', label: 'Companies', icon: Building2 }] : []),
  ]

  return (
    <aside className="flex grow flex-col gap-y-5 overflow-y-auto bg-ink-900 px-4 pb-4 ring-1 ring-white/5">
      <div className="flex h-16 shrink-0 items-center px-2">
        <Wordmark className="text-lg" />
      </div>

      {isSuperAdmin && <CompanySwitcher />}

      <nav className="flex flex-1 flex-col gap-6">
        <NavGroup items={main} onNavigate={onNavigate} />
        {admin.length > 0 && <NavGroup title="Administration" items={admin} onNavigate={onNavigate} />}
      </nav>

      <UserCard />
    </aside>
  )
}

function NavGroup({ title, items, onNavigate }: { title?: string; items: NavItem[]; onNavigate?: () => void }) {
  return (
    <div>
      {title && <p className="mb-2 px-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{title}</p>}
      <ul className="space-y-0.5">
        {items.map((item) => (
          <li key={item.to}>
            <NavLink
              to={item.to}
              onClick={onNavigate}
              className={({ isActive }) =>
                cn(
                  'group flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors',
                  isActive ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white',
                )
              }
            >
              {({ isActive }) => (
                <>
                  <item.icon className={cn('size-4.5', isActive ? 'text-brand-300' : 'text-slate-400 group-hover:text-slate-300')} aria-hidden />
                  {item.label}
                  {isActive && <span className="ml-auto h-4 w-1 rounded-full bg-brand-400" />}
                </>
              )}
            </NavLink>
          </li>
        ))}
      </ul>
    </div>
  )
}

function CompanySwitcher() {
  const { companyId, selectCompany } = useAuth()
  const { data: companies, isLoading } = useCompanies()
  const current = companies?.find((company) => company.id === companyId)

  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="flex w-full items-center gap-2.5 rounded-lg bg-white/5 px-2.5 py-2 text-left ring-1 ring-white/10 outline-none hover:bg-white/10 focus-visible:ring-brand-400">
        <Building2 className="size-4 shrink-0 text-brand-300" aria-hidden />
        <div className="min-w-0 flex-1">
          <p className="text-[11px] text-slate-400">Working in</p>
          <p className="truncate text-sm font-medium text-white">
            {isLoading ? 'Loading…' : (current?.name ?? 'Select a company')}
          </p>
        </div>
        <ChevronsUpDown className="size-4 text-slate-400" aria-hidden />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="min-w-56">
        <DropdownMenuLabel>Companies</DropdownMenuLabel>
        <DropdownMenuRadioGroup value={companyId ? String(companyId) : ''} onValueChange={(value) => selectCompany(Number(value))}>
          {companies?.map((company) => (
            <DropdownMenuRadioItem key={company.id} value={String(company.id)}>
              <span className="truncate">{company.name}</span>
              {!company.is_active && <span className="ml-auto text-xs text-muted-foreground">inactive</span>}
            </DropdownMenuRadioItem>
          ))}
        </DropdownMenuRadioGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

function UserCard() {
  const { user, logout } = useAuth()
  const [passwordOpen, setPasswordOpen] = useState(false)

  if (!user) return null

  return (
    <>
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left outline-none hover:bg-white/5 focus-visible:ring-2 focus-visible:ring-brand-400">
          <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-600/30 text-sm font-semibold text-brand-100 ring-1 ring-brand-400/30">
            {initials(user.name)}
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-medium text-white">{user.name}</p>
            <p className="truncate text-xs text-slate-400">{user.company?.name ?? user.role_label}</p>
          </div>
          <ChevronsUpDown className="size-4 text-slate-500" aria-hidden />
        </DropdownMenuTrigger>
        <DropdownMenuContent side="top" align="start" className="min-w-56">
          <DropdownMenuLabel className="font-normal">
            <span className="block truncate text-sm font-medium text-slate-900">{user.email}</span>
            <span className="text-xs text-slate-500">{user.role_label}</span>
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuItem onSelect={() => setPasswordOpen(true)}>
            <Lock className="text-slate-400" /> Change password
          </DropdownMenuItem>
          <DropdownMenuItem onSelect={() => void logout()}>
            <LogOut className="text-slate-400" /> Sign out
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
      <ChangePasswordDialog open={passwordOpen} onClose={() => setPasswordOpen(false)} />
    </>
  )
}
