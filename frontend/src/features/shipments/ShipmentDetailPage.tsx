import {
  Anchor,
  ArrowRight,
  Boxes,
  CalendarClock,
  CalendarDays,
  Container as ContainerIcon,
  History,
  Info,
  Package,
  Pencil,
  RefreshCw,
  Route as RouteIcon,
  Ship,
  X,
} from 'lucide-react'
import { useState } from 'react'
import { useParams } from 'react-router'
import { toast } from 'sonner'
import { PageHeader } from '@/components/layout/PageHeader'
import { StatusBar } from '@/components/StatusBar'
import { CategoryBadge, DelayBadge, OverdueBadge, StatusBadge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardHeader } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { EmptyState, ErrorState, Skeleton } from '@/components/ui/feedback'
import { Segmented } from '@/components/ui/segmented'
import { api, ApiError } from '@/lib/api'
import { cn } from '@/lib/cn'
import { daysSince, formatDate, formatWeight } from '@/lib/format'
import { useRefreshShipment, useShipment, useShipmentHistory } from '@/lib/queries'
import type { CargoItem, Container, TrackingSyncResult } from '@/lib/types'
import { ContainerDialog } from '@/features/shipments/ContainerDialog'
import { DeliveryDateDialog, ShipmentDatesDialog } from '@/features/shipments/DateDialogs'
import { HistoryTimeline } from '@/features/shipments/HistoryTimeline'
import { MethodIcon } from '@/features/shipments/parts'
import { StatusDialog, type CargoScope } from '@/features/shipments/StatusDialog'

export function ShipmentDetailPage() {
  const { id } = useParams()
  const { data: shipment, isLoading, error, refetch } = useShipment(id)
  const [historyType, setHistoryType] = useState<'all' | 'status' | 'dates'>('all')
  const history = useShipmentHistory(id, historyType === 'all' ? undefined : historyType)
  const refresh = useRefreshShipment()

  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [statusScope, setStatusScope] = useState<CargoScope | null>(null)
  const [deliveryScope, setDeliveryScope] = useState<CargoScope | null>(null)
  const [datesOpen, setDatesOpen] = useState(false)
  const [editContainer, setEditContainer] = useState<Container | null>(null)
  const [syncing, setSyncing] = useState(false)

  if (isLoading) return <DetailSkeleton />
  if (error || !shipment) {
    return (
      <Card className="mt-6">
        {error instanceof ApiError && error.status === 404 ? (
          <EmptyState icon={Package} title="Shipment not found" description="It does not exist or belongs to another company." />
        ) : (
          <ErrorState error={error} onRetry={() => void refetch()} />
        )}
      </Card>
    )
  }

  const toggle = (itemId: number) =>
    setSelected((current) => {
      const next = new Set(current)
      if (next.has(itemId)) next.delete(itemId)
      else next.add(itemId)
      return next
    })

  const toggleContainer = (container: Container) =>
    setSelected((current) => {
      const ids = container.items?.map((item) => item.id) ?? []
      const allSelected = ids.every((itemId) => current.has(itemId))
      const next = new Set(current)
      ids.forEach((itemId) => (allSelected ? next.delete(itemId) : next.add(itemId)))
      return next
    })

  const selectedScope: CargoScope | null = selected.size > 0 ? { type: 'items', itemIds: [...selected] } : null

  const syncTracking = async () => {
    setSyncing(true)
    try {
      const result = await api<TrackingSyncResult>(`/shipments/${shipment.id}/tracking-sync`, { method: 'POST' })
      refresh(result.shipment)
      if (result.applied.length > 0) toast.success(`Tracking: ${result.applied.map((a) => a.change).join(', ')}`)
      else if (result.skipped.length > 0) toast.warning(`Tracking update skipped: ${result.skipped[0].reason}`)
      else toast.info('No new tracking updates')
    } catch (e) {
      toast.error(e instanceof ApiError ? e.message : 'Tracking sync failed')
    } finally {
      setSyncing(false)
    }
  }

  return (
    <>
      <PageHeader
        breadcrumbs={[{ label: 'Shipments', to: '/shipments' }, { label: shipment.reference }]}
        title={shipment.reference}
        badge={<StatusBadge status={shipment.status} className="text-sm" />}
        description={
          <span className="inline-flex flex-wrap items-center gap-x-3 gap-y-1">
            <span className="inline-flex items-center gap-1.5">
              <MethodIcon method={shipment.shipping_method} /> {shipment.shipping_line ?? 'No shipping line'}
            </span>
            {shipment.external_reference && <span>Suppli ref. {shipment.external_reference}</span>}
          </span>
        }
        actions={
          <>
            <Button variant="outline" icon={RefreshCw} loading={syncing} onClick={() => void syncTracking()}>
              Sync tracking
            </Button>
            <Button variant="outline" icon={CalendarClock} onClick={() => setDatesOpen(true)}>
              Change dates
            </Button>
            <Button icon={Ship} onClick={() => setStatusScope({ type: 'shipment' })}>
              Change status
            </Button>
          </>
        }
      />

      <div className="grid gap-5 lg:grid-cols-3">
        <Card>
          <CardHeader title="Route" icon={RouteIcon} />
          <div className="space-y-4 px-5 py-4">
            <div className="flex items-center gap-3">
              <div className="min-w-0 flex-1">
                <p className="text-xs text-slate-500">From</p>
                <p className="truncate font-medium text-slate-900">{shipment.origin_port ?? '—'}</p>
              </div>
              <ArrowRight className="size-4 shrink-0 text-slate-300" />
              <div className="min-w-0 flex-1 text-right">
                <p className="text-xs text-slate-500">To</p>
                <p className="truncate font-medium text-slate-900">{shipment.destination_port}</p>
              </div>
            </div>
            <dl className="grid grid-cols-2 gap-3 text-sm">
              <Detail label="Method" value={shipment.shipping_method.toUpperCase()} />
              <Detail label="Incoterm" value={shipment.incoterm} />
              <Detail label="Containers" value={shipment.summary?.containers_count} />
              <Detail label="Packages" value={shipment.summary?.items_count} />
            </dl>
          </div>
        </Card>

        <Card>
          <CardHeader title="Dates" icon={CalendarDays} />
          <div className="grid grid-cols-2 gap-4 px-5 py-4">
            <DateBlock label="ETD" date={shipment.etd} original={shipment.etd_original} delay={shipment.etd_delay_days} />
            <DateBlock label="ETA" date={shipment.eta} original={shipment.eta_original} delay={shipment.eta_delay_days} overdue={shipment.overdue_days} />
          </div>
        </Card>

        <Card>
          <CardHeader title="Cargo status" icon={Anchor} />
          <div className="px-5 py-4">
            {shipment.summary && <StatusBar counts={shipment.summary.status_counts} showLegend />}
            <p className="mt-3 flex items-start gap-1.5 text-xs text-slate-500">
              <Info className="mt-0.5 size-3.5 shrink-0" />
              The shipment shows the least advanced status of its packages.
            </p>
          </div>
        </Card>
      </div>

      {shipment.notes && (
        <p className="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{shipment.notes}</p>
      )}

      <div className="mt-6 space-y-5">
        {shipment.containers?.map((container) => (
          <ContainerCard
            key={container.id}
            container={container}
            selected={selected}
            onToggle={toggle}
            onToggleAll={() => toggleContainer(container)}
            onEdit={() => setEditContainer(container)}
            onStatus={() => setStatusScope({ type: 'container', containerId: container.id })}
            onDeliveryDate={() => setDeliveryScope({ type: 'container', containerId: container.id })}
          />
        ))}
      </div>

      <Card className="mt-6">
        <CardHeader
          title="History"
          description="Status changes and the date audit trail, newest first."
          icon={History}
          actions={
            <Segmented
              value={historyType}
              onChange={setHistoryType}
              options={[
                { value: 'all', label: 'All' },
                { value: 'status', label: 'Status' },
                { value: 'dates', label: 'Dates' },
              ]}
            />
          }
        />
        <HistoryTimeline groups={history.data} loading={history.isLoading} />
      </Card>

      {selectedScope && (
        <div className="fixed inset-x-0 bottom-5 z-40 flex justify-center px-4 lg:pl-64">
          <div className="flex flex-wrap items-center gap-3 rounded-2xl bg-ink-900 px-4 py-3 text-sm text-white shadow-2xl ring-1 ring-white/10">
            <span className="tabular font-medium">{selected.size} selected</span>
            <span className="h-5 w-px bg-white/15" />
            <Button size="sm" variant="secondary" onClick={() => setStatusScope(selectedScope)}>
              Set status
            </Button>
            <Button size="sm" variant="secondary" onClick={() => setDeliveryScope(selectedScope)}>
              Delivery date
            </Button>
            <button type="button" onClick={() => setSelected(new Set())} className="rounded-md p-1 text-slate-400 hover:text-white" aria-label="Clear selection">
              <X className="size-4" />
            </button>
          </div>
        </div>
      )}

      {statusScope && (
        <StatusDialog
          shipment={shipment}
          open
          initialScope={statusScope}
          onClose={() => {
            setStatusScope(null)
            setSelected(new Set())
          }}
        />
      )}
      {deliveryScope && (
        <DeliveryDateDialog shipment={shipment} open initialScope={deliveryScope} onClose={() => setDeliveryScope(null)} />
      )}
      {datesOpen && <ShipmentDatesDialog shipment={shipment} open onClose={() => setDatesOpen(false)} />}
      {editContainer && <ContainerDialog shipment={shipment} container={editContainer} onClose={() => setEditContainer(null)} />}
    </>
  )
}

function Detail({ label, value }: { label: string; value: string | number | null | undefined }) {
  return (
    <div>
      <dt className="text-xs text-slate-500">{label}</dt>
      <dd className="tabular mt-0.5 font-medium text-slate-900">{value ?? '—'}</dd>
    </div>
  )
}

function DateBlock({ label, date, original, delay, overdue = 0 }: { label: string; date: string | null; original: string | null; delay: number; overdue?: number }) {
  return (
    <div>
      <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">{label}</p>
      <p className="tabular mt-1 text-lg font-semibold text-slate-900">{formatDate(date)}</p>
      <p className="tabular mt-0.5 text-xs text-slate-500">Planned {formatDate(original)}</p>
      <div className="mt-2 flex flex-wrap gap-1">
        <DelayBadge days={delay} />
        <OverdueBadge days={overdue} label="overdue at port" />
        {delay === 0 && overdue === 0 && <span className="text-xs text-emerald-600">On plan</span>}
      </div>
    </div>
  )
}

function ContainerCard({
  container,
  selected,
  onToggle,
  onToggleAll,
  onEdit,
  onStatus,
  onDeliveryDate,
}: {
  container: Container
  selected: Set<number>
  onToggle: (id: number) => void
  onToggleAll: () => void
  onEdit: () => void
  onStatus: () => void
  onDeliveryDate: () => void
}) {
  const items = container.items ?? []
  const allSelected = items.length > 0 && items.every((item) => selected.has(item.id))
  const weight = items.reduce((sum, item) => sum + (item.gross_weight_kg ?? 0), 0)

  return (
    <Card className="overflow-hidden">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-3">
        <div className="flex flex-wrap items-center gap-3">
          <span className="flex size-9 items-center justify-center rounded-lg bg-white text-brand-600 ring-1 ring-slate-200">
            <ContainerIcon className="size-4.5" />
          </span>
          <div>
            <p className="font-mono text-sm font-semibold tracking-wide text-slate-900">{container.container_no}</p>
            <p className="text-xs text-slate-500">
              {container.container_type ?? 'No type'} · Seal {container.seal_no ?? '—'} · {items.length} packages · {formatWeight(weight)}
            </p>
          </div>
          {container.status && <StatusBadge status={container.status} />}
        </div>
        <div className="flex flex-wrap gap-2">
          <Button size="sm" variant="ghost" icon={Pencil} onClick={onEdit}>
            Seal / type
          </Button>
          <Button size="sm" variant="outline" icon={CalendarDays} onClick={onDeliveryDate}>
            Delivery date
          </Button>
          <Button size="sm" variant="outline" icon={Boxes} onClick={onStatus}>
            Container status
          </Button>
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead className="text-left text-xs font-medium text-slate-500">
            <tr className="border-b border-slate-100">
              <th className="w-10 py-2.5 pl-5">
                <Checkbox checked={allSelected} onCheckedChange={onToggleAll} aria-label="Select all packages in this container" />
              </th>
              <th className="px-3 py-2.5">Package / tag</th>
              <th className="px-3 py-2.5">Product</th>
              <th className="px-3 py-2.5">
                Customer <SuppliTag />
              </th>
              <th className="px-3 py-2.5">
                Proforma / exporter ref. <SuppliTag />
              </th>
              <th className="px-3 py-2.5">
                Storage <SuppliTag />
              </th>
              <th className="px-3 py-2.5">Status</th>
              <th className="px-3 py-2.5 pr-5">Customer delivery</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {items.map((item) => (
              <ItemRow key={item.id} item={item} selected={selected.has(item.id)} onToggle={() => onToggle(item.id)} />
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  )
}

function SuppliTag() {
  return (
    <span className="ml-1 rounded bg-slate-100 px-1 py-px text-[10px] font-medium text-slate-500" title="Read-only reference from Suppli">
      Suppli
    </span>
  )
}

function ItemRow({ item, selected, onToggle }: { item: CargoItem; selected: boolean; onToggle: () => void }) {
  return (
    <tr className={cn('align-top transition-colors', selected ? 'bg-brand-50/50' : 'hover:bg-slate-50/60')}>
      <td className="py-3 pl-5">
        <Checkbox checked={selected} onCheckedChange={onToggle} aria-label={`Select ${item.package_no}`} />
      </td>
      <td className="px-3 py-3">
        <p className="font-mono text-xs font-semibold text-slate-900">{item.package_no}</p>
        <p className="mt-0.5 text-xs text-slate-500">{item.tag_number ?? 'No tag'}</p>
      </td>
      <td className="max-w-72 px-3 py-3">
        <div className="flex items-start gap-1.5">
          <CategoryBadge code={item.product_category_code} />
          <p className="truncate text-slate-700" title={item.product_description ?? undefined}>
            {item.product_description ?? '—'}
          </p>
        </div>
        <p className="mt-0.5 text-xs text-slate-500">
          {item.net_type ?? '—'} · {item.quantity ?? '—'} {item.unit ?? ''} · {formatWeight(item.gross_weight_kg)}
        </p>
      </td>
      <td className="px-3 py-3">
        <p className="text-slate-700">{item.customer_name ?? '—'}</p>
        {item.customer_po && <p className="mt-0.5 text-xs text-slate-500">PO {item.customer_po}</p>}
      </td>
      <td className="tabular px-3 py-3 text-slate-700">
        <p>{item.proforma_invoice_no ?? '—'}</p>
        <p className="mt-0.5 text-xs text-slate-500">{item.exporter_reference ?? '—'}</p>
      </td>
      <td className="px-3 py-3 text-slate-700">{item.storage_facility ?? '—'}</td>
      <td className="px-3 py-3">
        <StatusBadge status={item.status} />
        {item.raw_status && item.raw_status.toLowerCase() !== item.status_label.toLowerCase() && (
          <p className="mt-1 text-[11px] text-slate-400" title="Original value in the imported delivery plan">
            Excel: “{item.raw_status}”
          </p>
        )}
      </td>
      <td className="px-3 py-3 pr-5">
        <p className="tabular text-slate-900">{formatDate(item.customer_delivery_date)}</p>
        <div className="mt-1 flex flex-wrap gap-1">
          <DelayBadge days={item.delivery_delay_days} />
          {item.is_delivery_overdue && <OverdueBadge days={daysSince(item.customer_delivery_date)} />}
        </div>
      </td>
    </tr>
  )
}

function DetailSkeleton() {
  return (
    <div aria-busy>
      <Skeleton className="mb-3 h-4 w-40" />
      <Skeleton className="mb-8 h-8 w-64" />
      <div className="grid gap-5 lg:grid-cols-3">
        {[0, 1, 2].map((i) => (
          <div key={i} className="card space-y-3 p-5">
            <Skeleton className="h-4 w-24" />
            <Skeleton className="h-6 w-40" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-2/3" />
          </div>
        ))}
      </div>
      <div className="card mt-6 space-y-4 p-5">
        <Skeleton className="h-5 w-48" />
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-10 w-full" />
        ))}
      </div>
    </div>
  )
}
