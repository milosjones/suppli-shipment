import { ArrowDown, ArrowUp, ChevronsUpDown, Filter, Package, Search, X } from 'lucide-react'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { PageHeader } from '@/components/layout/PageHeader'
import { StatusBar } from '@/components/StatusBar'
import { StatusBadge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Combobox } from '@/components/ui/combobox'
import { PeriodPicker } from '@/components/ui/date-picker'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/ui/feedback'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Pagination } from '@/components/ui/pagination'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { cn } from '@/lib/cn'
import { addDaysIso, monthRange, todayIso } from '@/lib/format'
import { useMeta, useShipments, type ShipmentFilters } from '@/lib/queries'
import { progressText, STATUS_ORDER, STATUS_STYLES } from '@/lib/status'
import { DateWithDelay, Route } from '@/features/shipments/parts'

const ACTIVE_FILTER = 'border-brand-300 bg-brand-50 text-brand-800'

export function ShipmentsPage() {
  const [params, setParams] = useSearchParams()
  const navigate = useNavigate()
  const { data: meta } = useMeta()

  const filters = useMemo<ShipmentFilters>(
    () => ({
      q: params.get('q') ?? undefined,
      status: params.getAll('status'),
      destination_port: params.get('destination_port') ?? undefined,
      shipping_method: params.get('shipping_method') ?? undefined,
      shipping_line: params.get('shipping_line') ?? undefined,
      customer: params.get('customer') ?? undefined,
      eta_from: params.get('eta_from') ?? undefined,
      eta_to: params.get('eta_to') ?? undefined,
      delayed: params.get('delayed') === '1',
      overdue: params.get('overdue') === '1',
      sort: params.get('sort') ?? '-eta',
      page: Number(params.get('page') ?? 1),
    }),
    [params],
  )

  const { data, isLoading, isFetching, error, refetch } = useShipments(filters)

  const [search, setSearch] = useState(filters.q ?? '')
  useEffect(() => {
    const timer = setTimeout(() => {
      setParams(
        (current) => {
          if ((current.get('q') ?? '') === search) return current
          const next = new URLSearchParams(current)
          if (search) next.set('q', search)
          else next.delete('q')
          next.delete('page')
          return next
        },
        { replace: true },
      )
    }, 350)
    return () => clearTimeout(timer)
  }, [search, setParams])

  function update(changes: Record<string, string | string[] | null>) {
    const next = new URLSearchParams(params)
    for (const [key, value] of Object.entries(changes)) {
      next.delete(key)
      if (Array.isArray(value)) value.forEach((v) => next.append(key, v))
      else if (value) next.set(key, value)
    }
    if (!('page' in changes)) next.delete('page')
    setParams(next, { replace: true })
  }

  const activeFilters =
    ['destination_port', 'shipping_method', 'shipping_line', 'customer', 'delayed', 'overdue', 'q'].filter((key) => params.get(key)).length +
    (filters.eta_from || filters.eta_to ? 1 : 0) +
    (filters.status?.length ?? 0)

  const clearFilters = () => {
    setSearch('')
    const sort = params.get('sort')
    setParams(sort ? { sort } : {}, { replace: true })
  }

  const status = filters.status?.[0] ?? null
  const sorting = { sort: filters.sort ?? '-eta', onSort: (sort: string) => update({ sort }) }

  const etaPresets = [
    { label: 'Next 7 days', from: todayIso(), to: addDaysIso(7) },
    { label: 'Next 14 days', from: todayIso(), to: addDaysIso(14) },
    { label: 'Next 30 days', from: todayIso(), to: addDaysIso(30) },
    { label: 'Last 30 days', from: addDaysIso(-30), to: todayIso() },
    { label: 'This month', ...monthRange(0) },
  ]

  return (
    <>
      <PageHeader
        title="Shipments"
        description="Every voyage with its containers and cargo. Status shows the least advanced cargo."
      />

      <Card className="mb-5">
        <div className="space-y-4 p-4">
          <div className="relative">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search reference, container, package, tag, proforma invoice or customer…"
              aria-label="Search shipments"
              className="pl-9"
            />
          </div>

          <div className="grid gap-x-3 gap-y-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-[repeat(5,minmax(0,1fr))_minmax(0,1.5fr)]">
            <FilterField label="Status">
              <Select value={status ?? 'all'} onValueChange={(value) => update({ status: value === 'all' ? null : value })}>
                <SelectTrigger aria-label="Status" className={cn('w-full', status && ACTIVE_FILTER)}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All statuses</SelectItem>
                  {STATUS_ORDER.map((value) => (
                    <SelectItem key={value} value={value}>
                      <span className={cn('size-2 rounded-full', STATUS_STYLES[value].dot)} />
                      {STATUS_STYLES[value].label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </FilterField>
            <FilterField label="Destination">
              <Combobox
                label="Destination"
                value={filters.destination_port ?? null}
                onChange={(destination_port) => update({ destination_port })}
                options={meta?.filters.destination_ports ?? []}
                allLabel="All ports"
                searchPlaceholder="Search ports…"
              />
            </FilterField>
            <FilterField label="Method">
              <Select
                value={filters.shipping_method ?? 'all'}
                onValueChange={(method) => update({ shipping_method: method === 'all' ? null : method })}
              >
                <SelectTrigger aria-label="Shipping method" className={cn('w-full', filters.shipping_method && ACTIVE_FILTER)}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All methods</SelectItem>
                  {meta?.shipping_methods.map((method) => (
                    <SelectItem key={method.value} value={method.value}>
                      {method.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </FilterField>
            <FilterField label="Shipping line">
              <Combobox
                label="Shipping line"
                value={filters.shipping_line ?? null}
                onChange={(shipping_line) => update({ shipping_line })}
                options={meta?.filters.shipping_lines ?? []}
                allLabel="All lines"
                searchPlaceholder="Search shipping lines…"
              />
            </FilterField>
            <FilterField label="Customer">
              <Combobox
                label="Customer"
                value={filters.customer ?? null}
                onChange={(customer) => update({ customer })}
                options={meta?.filters.customers ?? []}
                allLabel="All customers"
                searchPlaceholder="Search customers…"
              />
            </FilterField>
            <FilterField label="ETA">
              <PeriodPicker
                from={filters.eta_from ?? null}
                to={filters.eta_to ?? null}
                onChange={({ from, to }) => update({ eta_from: from, eta_to: to })}
                presets={etaPresets}
                anyLabel="Any date"
              />
            </FilterField>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-t border-slate-100 pt-4">
            <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
              <FilterSwitch label="Delayed" checked={filters.delayed} onChange={(on) => update({ delayed: on ? '1' : null })} />
              <FilterSwitch label="Overdue at port" checked={filters.overdue} onChange={(on) => update({ overdue: on ? '1' : null })} />
            </div>
            {activeFilters > 0 && (
              <div className="flex items-center gap-3 text-sm">
                <span className="inline-flex items-center gap-1.5 text-slate-500">
                  <Filter className="size-4" /> {activeFilters} filter{activeFilters === 1 ? '' : 's'} active
                </span>
                <Button variant="ghost" size="sm" icon={X} onClick={clearFilters}>
                  Clear filters
                </Button>
              </div>
            )}
          </div>
        </div>
      </Card>

      <Card className="overflow-hidden">
        {isLoading ? (
          <TableSkeleton rows={8} columns={7} />
        ) : error ? (
          <ErrorState error={error} onRetry={() => void refetch()} />
        ) : !data || data.data.length === 0 ? (
          <EmptyState icon={Package} title="No shipments found" description="Try other filters, or import the delivery plan." />
        ) : (
          <>
            <div className={cn('overflow-x-auto transition-opacity', isFetching && 'opacity-60')}>
              <table className="min-w-full text-sm">
                <thead className="bg-slate-50/80 text-left text-xs font-medium text-slate-500">
                  <tr>
                    <SortHeader label="Shipment" field="reference" className="px-5 py-2.5" {...sorting} />
                    <SortHeader label="Route" field="destination_port" className="px-4 py-2.5" {...sorting} />
                    <SortHeader label="ETD" field="etd" className="px-4 py-2.5" {...sorting} />
                    <SortHeader label="ETA" field="eta" className="px-4 py-2.5" {...sorting} />
                    <SortHeader label="Status" field="status" className="px-4 py-2.5" {...sorting} />
                    <th className="px-4 py-2.5 text-right">Cargo</th>
                    <th className="px-5 py-2.5">Customers</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {data.data.map((shipment) => {
                    const progress = progressText(shipment.summary?.status_counts)
                    return (
                      <tr
                        key={shipment.id}
                        onClick={() => navigate(`/shipments/${shipment.id}`)}
                        className="cursor-pointer align-top transition-colors hover:bg-brand-50/40"
                      >
                        <td className="px-5 py-3.5">
                          <Link to={`/shipments/${shipment.id}`} className="font-semibold whitespace-nowrap text-slate-900 hover:text-brand-700" onClick={(e) => e.stopPropagation()}>
                            {shipment.reference}
                          </Link>
                          <p className="mt-0.5 text-xs whitespace-nowrap text-slate-500">{shipment.shipping_line ?? '—'}</p>
                        </td>
                        <td className="px-4 py-3.5">
                          <Route origin={shipment.origin_port} destination={shipment.destination_port} method={shipment.shipping_method} />
                        </td>
                        <td className="px-4 py-3.5">
                          <DateWithDelay date={shipment.etd} delayDays={shipment.etd_delay_days} compact />
                        </td>
                        <td className="px-4 py-3.5">
                          <DateWithDelay date={shipment.eta} delayDays={shipment.eta_delay_days} overdueDays={shipment.overdue_days} compact />
                        </td>
                        <td className="px-4 py-3.5">
                          <StatusBadge status={shipment.status} />
                          {shipment.summary && <StatusBar counts={shipment.summary.status_counts} className="mt-2 w-32" />}
                          {progress && <p className="mt-1 text-xs text-slate-500">{progress}</p>}
                        </td>
                        <td className="tabular px-4 py-3.5 text-right whitespace-nowrap text-slate-600">
                          <p>{shipment.summary?.containers_count ?? 0} cont.</p>
                          <p className="text-xs text-slate-400">{shipment.summary?.items_count ?? 0} packages</p>
                        </td>
                        <td className="max-w-56 px-5 py-3.5 text-slate-600">
                          <p className="truncate">{shipment.summary?.customers.join(', ') || '—'}</p>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            <Pagination meta={data.meta} onPage={(page) => update({ page: String(page) })} />
          </>
        )}
      </Card>
    </>
  )
}

function FilterField({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <Label className="mb-1.5 text-xs text-slate-500">{label}</Label>
      {children}
    </div>
  )
}

function FilterSwitch({ label, checked, onChange }: { label: string; checked?: boolean; onChange: (checked: boolean) => void }) {
  return (
    <label className="inline-flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-700 select-none">
      <Switch checked={checked} onCheckedChange={onChange} />
      {label}
    </label>
  )
}

function SortHeader({
  label,
  field,
  sort,
  onSort,
  className,
}: {
  label: string
  field: string
  sort: string
  onSort: (sort: string) => void
  className?: string
}) {
  const ascending = sort === field
  const descending = sort === `-${field}`
  const Icon = ascending ? ArrowUp : descending ? ArrowDown : ChevronsUpDown

  return (
    <th className={className} aria-sort={ascending ? 'ascending' : descending ? 'descending' : 'none'}>
      <button
        type="button"
        onClick={() => onSort(ascending ? `-${field}` : field)}
        className={cn(
          '-mx-1.5 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 transition-colors hover:bg-slate-200/60 hover:text-slate-800',
          (ascending || descending) && 'text-slate-900',
        )}
      >
        {label}
        <Icon className={cn('size-3.5', ascending || descending ? 'text-brand-600' : 'text-slate-400')} />
      </button>
    </th>
  )
}
