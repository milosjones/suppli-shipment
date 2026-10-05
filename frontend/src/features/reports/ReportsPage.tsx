import {
  AlertTriangle,
  Anchor,
  BarChart3,
  CalendarRange,
  Clock,
  PackageCheck,
  PieChart as PieChartIcon,
  Table2,
  Truck,
} from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router'
import { PageHeader } from '@/components/layout/PageHeader'
import { DelayBadge, OverdueBadge, StatusBadge } from '@/components/ui/badge'
import { Card, CardHeader, StatTile } from '@/components/ui/card'
import { Combobox } from '@/components/ui/combobox'
import { PeriodPicker } from '@/components/ui/date-picker'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/ui/feedback'
import { Field } from '@/components/ui/field'
import { Segmented } from '@/components/ui/segmented'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { cn } from '@/lib/cn'
import { addDaysIso, daysSince, formatDate, formatNumber, formatWeight, monthRange, todayIso } from '@/lib/format'
import { useDelayedReport, useMeta, useStatusOverview, useUpcomingReport } from '@/lib/queries'
import { STATUS_ORDER, STATUS_STYLES } from '@/lib/status'
import type { CargoItem, CargoStatus, Shipment } from '@/lib/types'
import { CargoByStatus, StatusBreakdownChart } from '@/features/reports/StatusCharts'
import { MethodIcon } from '@/features/shipments/parts'

type Tab = 'upcoming' | 'overview' | 'delayed'

export function ReportsPage() {
  const [params, setParams] = useSearchParams()
  const tab = (params.get('tab') ?? 'upcoming') as Tab

  return (
    <>
      <PageHeader title="Reports" description="Upcoming deliveries, where the cargo is, and what is late." />
      <Segmented
        className="mb-5"
        value={tab}
        onChange={(value) => setParams({ tab: value }, { replace: true })}
        options={[
          { value: 'upcoming', label: 'Upcoming deliveries', icon: CalendarRange },
          { value: 'overview', label: 'Status overview', icon: BarChart3 },
          { value: 'delayed', label: 'Delayed', icon: AlertTriangle },
        ]}
      />
      {tab === 'upcoming' && <UpcomingReportView />}
      {tab === 'overview' && <StatusOverviewView />}
      {tab === 'delayed' && <DelayedView />}
    </>
  )
}

function UpcomingReportView() {
  const { data: meta } = useMeta()
  const [range, setRange] = useState({ from: todayIso(), to: addDaysIso(30) })
  const [destination, setDestination] = useState<string | null>(null)
  const report = useUpcomingReport({ ...range, destination_port: destination ?? undefined })

  return (
    <>
      <Card className="mb-5 p-4">
        <div className="flex flex-wrap items-center justify-end gap-3">
          <PeriodPicker
            from={range.from}
            to={range.to}
            presets={[
              { label: 'Next 7 days', from: todayIso(), to: addDaysIso(7) },
              { label: 'Next 14 days', from: todayIso(), to: addDaysIso(14) },
              { label: 'Next 30 days', from: todayIso(), to: addDaysIso(30) },
              { label: 'Next 90 days', from: todayIso(), to: addDaysIso(90) },
              { label: 'This month', ...monthRange(0) },
              { label: 'Next month', ...monthRange(1) },
            ]}
            onChange={(next) => {
              if (next.from && next.to) setRange({ from: next.from, to: next.to })
            }}
            align="end"
            className="sm:w-auto sm:min-w-72"
          />
          <Combobox
            label="Destination"
            value={destination}
            onChange={setDestination}
            options={meta?.filters.destination_ports ?? []}
            allLabel="All ports"
            searchPlaceholder="Search ports…"
            align="end"
            className="sm:w-52"
          />
        </div>
      </Card>

      {report.error ? (
        <Card>
          <ErrorState error={report.error} onRetry={() => void report.refetch()} />
        </Card>
      ) : (
        <div className="space-y-5">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatTile label="Arrivals at port" icon={Anchor} tone="indigo" loading={report.isLoading} value={report.data?.arrivals.length ?? 0} />
            <StatTile label="Customer deliveries" icon={Truck} tone="emerald" loading={report.isLoading} value={report.data?.deliveries.length ?? 0} />
            <StatTile label="Overdue arrivals" icon={Clock} tone="rose" loading={report.isLoading} value={report.data?.overdue_arrivals.length ?? 0} hint="ETA passed, not arrived" />
            <StatTile label="Overdue deliveries" icon={AlertTriangle} tone="orange" loading={report.isLoading} value={report.data?.overdue_deliveries.length ?? 0} hint="Delivery date passed" />
          </div>

          <Card className="overflow-hidden">
            <CardHeader title="Arrivals at port" description={`ETA between ${formatDate(range.from)} and ${formatDate(range.to)}`} icon={Anchor} />
            {report.isLoading ? <TableSkeleton rows={3} /> : <ShipmentTable shipments={report.data?.arrivals ?? []} empty="No arrivals in this period." />}
          </Card>

          <Card className="overflow-hidden">
            <CardHeader title="Customer deliveries" description="Packages due at customers in this period" icon={PackageCheck} />
            {report.isLoading ? <TableSkeleton rows={4} /> : <ItemTable items={report.data?.deliveries ?? []} empty="No deliveries due in this period." />}
          </Card>

          <Card className="overflow-hidden">
            <CardHeader title="Overdue" description={`As of ${formatDate(report.data?.as_of)}`} icon={Clock} />
            {report.isLoading ? (
              <TableSkeleton rows={3} />
            ) : (
              <>
                <ShipmentTable shipments={report.data?.overdue_arrivals ?? []} empty="No shipments overdue at port." overdue />
                <div className="border-t border-slate-100">
                  <ItemTable items={report.data?.overdue_deliveries ?? []} empty="No overdue customer deliveries." overdue />
                </div>
              </>
            )}
          </Card>
        </div>
      )}
    </>
  )
}

function StatusOverviewView() {
  const { data: meta } = useMeta()
  const [filters, setFilters] = useState<{ destination_port: string | null; customer: string | null }>({
    destination_port: null,
    customer: null,
  })
  const report = useStatusOverview({
    destination_port: filters.destination_port ?? undefined,
    customer: filters.customer ?? undefined,
  })

  return (
    <>
      <Card className="mb-5 p-4">
        <div className="flex flex-wrap items-center justify-end gap-3">
          <Combobox
            label="Destination"
            value={filters.destination_port}
            onChange={(destination_port) => setFilters({ ...filters, destination_port })}
            options={meta?.filters.destination_ports ?? []}
            allLabel="All ports"
            searchPlaceholder="Search ports…"
            align="end"
            className="sm:w-52"
          />
          <Combobox
            label="Customer"
            value={filters.customer}
            onChange={(customer) => setFilters({ ...filters, customer })}
            options={meta?.filters.customers ?? []}
            allLabel="All customers"
            searchPlaceholder="Search customers…"
            align="end"
            className="sm:w-64"
          />
        </div>
      </Card>

      {report.error ? (
        <Card>
          <ErrorState error={report.error} onRetry={() => void report.refetch()} />
        </Card>
      ) : (
        <div className="space-y-5">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            {(report.data?.statuses ?? STATUS_ORDER.map((status) => ({ status, label: STATUS_STYLES[status].label, items: 0, containers: 0, shipments: 0, gross_weight_kg: 0 }))).map((row) => {
              const style = STATUS_STYLES[row.status]
              return (
                <div key={row.status} className="card p-4">
                  <div className="flex items-center justify-between">
                    <StatusBadge status={row.status} />
                  </div>
                  {report.isLoading ? (
                    <div className="skeleton mt-3 h-8 w-16" />
                  ) : (
                    <>
                      <p className="tabular mt-3 text-2xl font-semibold text-slate-900">{formatNumber(row.items)}</p>
                      <p className="text-xs text-slate-500">packages · {formatWeight(row.gross_weight_kg)}</p>
                      <p className="mt-2 text-xs text-slate-500">
                        <span className={cn('font-medium', style.soft, 'rounded px-1')}>{row.containers}</span> containers ·{' '}
                        <span className="font-medium text-slate-700">{row.shipments}</span> shipments
                      </p>
                    </>
                  )}
                </div>
              )
            })}
          </div>

          <div className="grid gap-5 xl:grid-cols-3">
            <Card className="overflow-hidden">
              <CardHeader title="Cargo by status" description="Packages per status" icon={PieChartIcon} />
              <div className="p-5">
                {report.isLoading || !report.data ? (
                  <div className="flex items-center gap-6">
                    <div className="skeleton size-48 shrink-0 rounded-full" />
                    <div className="flex-1 space-y-3">
                      {STATUS_ORDER.map((status) => (
                        <div key={status} className="skeleton h-4 w-full" />
                      ))}
                    </div>
                  </div>
                ) : (
                  <CargoByStatus report={report.data} />
                )}
              </div>
            </Card>
            <MatrixCard
              className="xl:col-span-2"
              title="By destination port"
              loading={report.isLoading}
              rows={(report.data?.by_destination ?? []).map((row) => ({ name: row.destination_port, counts: row.counts, total: row.total }))}
            />
          </div>

          <MatrixCard
            title="By customer"
            loading={report.isLoading}
            rows={(report.data?.by_customer ?? []).map((row) => ({ name: row.customer_name, counts: row.counts, total: row.total }))}
          />
        </div>
      )}
    </>
  )
}

function MatrixCard({
  title,
  rows,
  loading,
  className,
}: {
  title: string
  loading: boolean
  rows: { name: string; counts: Record<CargoStatus, number>; total: number }[]
  className?: string
}) {
  const [view, setView] = useState<'chart' | 'table'>('chart')

  return (
    <Card className={cn('overflow-hidden', className)}>
      <CardHeader
        title={title}
        description="Packages per status"
        icon={BarChart3}
        actions={
          <Segmented
            value={view}
            onChange={setView}
            options={[
              { value: 'chart', label: 'Chart', icon: BarChart3 },
              { value: 'table', label: 'Table', icon: Table2 },
            ]}
          />
        }
      />
      {loading ? (
        <TableSkeleton rows={5} columns={6} />
      ) : rows.length === 0 ? (
        <EmptyState icon={BarChart3} title="No cargo" className="py-10" />
      ) : view === 'chart' ? (
        <div className="px-5 pt-5 pb-3">
          <StatusBreakdownChart rows={rows} />
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50/80 text-xs font-medium text-slate-500">
              <tr>
                <th className="px-5 py-2.5 text-left">Name</th>
                {STATUS_ORDER.map((status) => (
                  <th key={status} className="px-2 py-2.5 text-right" title={STATUS_STYLES[status].label}>
                    <span className={cn('mr-1 inline-block size-2 rounded-full', STATUS_STYLES[status].dot)} />
                    {STATUS_STYLES[status].short}
                  </th>
                ))}
                <th className="px-5 py-2.5 text-right">Total</th>
              </tr>
            </thead>
            <tbody className="tabular divide-y divide-slate-100">
              {rows.map((row) => (
                <tr key={row.name}>
                  <td className="max-w-52 truncate px-5 py-2.5 font-medium text-slate-800">{row.name}</td>
                  {STATUS_ORDER.map((status) => (
                    <td key={status} className={cn('px-2 py-2.5 text-right', row.counts[status] ? 'text-slate-800' : 'text-slate-300')}>
                      {row.counts[status] || '·'}
                    </td>
                  ))}
                  <td className="px-5 py-2.5 text-right font-semibold text-slate-900">{row.total}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  )
}

function DelayedView() {
  const [minDays, setMinDays] = useState(1)
  const report = useDelayedReport({ min_days: minDays })

  return (
    <>
      <Card className="mb-5 p-4">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <p className="pb-2 text-sm text-slate-500">
            Compared with the first planned date, or overdue as of {formatDate(report.data?.as_of)}.
          </p>
          <Field label="Delayed by at least" className="w-full sm:w-48">
            <Select value={String(minDays)} onValueChange={(value) => setMinDays(Number(value))}>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent align="end">
                {[1, 3, 7, 14, 30].map((days) => (
                  <SelectItem key={days} value={String(days)}>
                    {days} day{days === 1 ? '' : 's'}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
        </div>
      </Card>

      {report.error ? (
        <Card>
          <ErrorState error={report.error} onRetry={() => void report.refetch()} />
        </Card>
      ) : (
        <div className="space-y-5">
          <Card className="overflow-hidden">
            <CardHeader title="Delayed shipments" description="ETD/ETA moved later than planned, or ETA passed before arrival" icon={Clock} />
            {report.isLoading ? (
              <TableSkeleton rows={4} />
            ) : (report.data?.shipments.length ?? 0) === 0 ? (
              <EmptyState icon={Clock} title="No delayed shipments" className="py-10" />
            ) : (
              <Table
                head={['Shipment', 'Route', 'ETD', 'ETA', 'Status', 'Delay']}
                rows={report.data!.shipments.map((row) => [
                  <ShipmentLink key="s" shipment={row.shipment} />,
                  <span key="r" className="inline-flex items-center gap-1.5">
                    <MethodIcon method={row.shipment.shipping_method} /> {row.shipment.destination_port}
                  </span>,
                  <span key="etd" className="tabular">{formatDate(row.shipment.etd)}</span>,
                  <span key="eta" className="tabular">
                    {formatDate(row.shipment.eta)} <span className="text-xs text-slate-400">(planned {formatDate(row.shipment.eta_original)})</span>
                  </span>,
                  <StatusBadge key="st" status={row.shipment.status} />,
                  <span key="d" className="flex flex-wrap gap-1">
                    <DelayBadge days={row.eta_delay_days || row.etd_delay_days} />
                    <OverdueBadge days={row.overdue_days} label="overdue at port" />
                  </span>,
                ])}
              />
            )}
          </Card>

          <Card className="overflow-hidden">
            <CardHeader title="Late customer deliveries" description="Delivery date moved later, or passed without delivery" icon={Truck} />
            {report.isLoading ? (
              <TableSkeleton rows={4} />
            ) : (report.data?.items.length ?? 0) === 0 ? (
              <EmptyState icon={Truck} title="No late deliveries" className="py-10" />
            ) : (
              <Table
                head={['Package', 'Customer', 'Shipment', 'Delivery date', 'Status', 'Delay']}
                rows={report.data!.items.map((row) => itemCells(row.item, [
                  <DelayBadge key="d1" days={row.delivery_delay_days} />,
                  <OverdueBadge key="d2" days={row.overdue_days} />,
                ]))}
              />
            )}
          </Card>
        </div>
      )}
    </>
  )
}

function itemCells(item: CargoItem, last: ReactNode[]): ReactNode[] {
  return [
    <span key="p">
      <span className="font-mono text-xs font-semibold text-slate-900">{item.package_no}</span>
      <span className="block text-xs text-slate-500">{item.tag_number ?? ''}</span>
    </span>,
    <span key="c">{item.customer_name ?? '—'}</span>,
    item.shipment ? (
      <Link key="s" to={`/shipments/${item.shipment.id}`} className="font-medium text-brand-700 hover:text-brand-800">
        {item.shipment.reference}
      </Link>
    ) : (
      <span key="s">—</span>
    ),
    <span key="dd" className="tabular">{formatDate(item.customer_delivery_date)}</span>,
    <StatusBadge key="st" status={item.status} />,
    <span key="x" className="flex flex-wrap gap-1">
      {last}
    </span>,
  ]
}

function ShipmentLink({ shipment }: { shipment: Shipment }) {
  return (
    <Link to={`/shipments/${shipment.id}`} className="font-semibold text-slate-900 hover:text-brand-700">
      {shipment.reference}
      <span className="block text-xs font-normal text-slate-500">{shipment.shipping_line}</span>
    </Link>
  )
}

function ShipmentTable({ shipments, empty, overdue = false }: { shipments: Shipment[]; empty: string; overdue?: boolean }) {
  if (shipments.length === 0) return <EmptyState icon={Anchor} title={empty} className="py-10" />

  return (
    <Table
      head={['Shipment', 'Destination', 'ETA', 'Status', 'Cargo', 'Customers']}
      rows={shipments.map((shipment) => [
        <ShipmentLink key="s" shipment={shipment} />,
        <span key="d" className="inline-flex items-center gap-1.5">
          <MethodIcon method={shipment.shipping_method} /> {shipment.destination_port}
        </span>,
        <span key="eta" className="flex flex-col gap-1">
          <span className="tabular">{formatDate(shipment.eta)}</span>
          {overdue ? <OverdueBadge days={shipment.overdue_days} /> : <DelayBadge days={shipment.eta_delay_days} />}
        </span>,
        <StatusBadge key="st" status={shipment.status} />,
        <span key="c" className="tabular whitespace-nowrap text-slate-600">
          {shipment.summary?.containers_count ?? 0} cont. · {shipment.summary?.items_count ?? 0} pkgs
        </span>,
        <span key="cu" className="block max-w-56 truncate text-slate-600">
          {shipment.summary?.customers.join(', ') || '—'}
        </span>,
      ])}
    />
  )
}

function ItemTable({ items, empty, overdue = false }: { items: CargoItem[]; empty: string; overdue?: boolean }) {
  if (items.length === 0) return <EmptyState icon={PackageCheck} title={empty} className="py-10" />

  return (
    <Table
      head={['Package', 'Customer', 'Shipment', 'Delivery date', 'Status', overdue ? 'Overdue' : 'Storage']}
      rows={items.map((item) =>
        itemCells(item, [
          overdue ? (
            <OverdueBadge key="o" days={daysSince(item.customer_delivery_date)} />
          ) : (
            <span key="sf" className="text-slate-600">
              {item.storage_facility ?? item.container?.container_no ?? '—'}
            </span>
          ),
        ]),
      )}
    />
  )
}

function Table({ head, rows }: { head: string[]; rows: ReactNode[][] }) {
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full text-sm">
        <thead className="bg-slate-50/80 text-left text-xs font-medium text-slate-500">
          <tr>
            {head.map((label, index) => (
              <th key={label} className={cn('py-2.5', index === 0 ? 'pr-3 pl-5' : 'px-3', index === head.length - 1 && 'pr-5')}>
                {label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {rows.map((cells, rowIndex) => (
            <tr key={rowIndex} className="align-top hover:bg-slate-50/60">
              {cells.map((cell, index) => (
                <td key={index} className={cn('py-3 text-slate-700', index === 0 ? 'pr-3 pl-5' : 'px-3', index === cells.length - 1 && 'pr-5')}>
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
