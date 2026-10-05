import { CalendarClock } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { DelayBadge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Field, FormError } from '@/components/ui/field'
import { Modal } from '@/components/ui/modal'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { api, ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { useRefreshShipment } from '@/lib/queries'
import type { Shipment } from '@/lib/types'
import type { CargoScope } from '@/features/shipments/StatusDialog'

function daysBetween(from: string | null, to: string | null): number {
  if (!from || !to) return 0
  return Math.round((Date.parse(to) - Date.parse(from)) / 86_400_000)
}

export function ShipmentDatesDialog({ shipment, open, onClose }: { shipment: Shipment; open: boolean; onClose: () => void }) {
  const refresh = useRefreshShipment()
  const [etd, setEtd] = useState(shipment.etd ?? '')
  const [eta, setEta] = useState(shipment.eta ?? '')
  const [reason, setReason] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [saving, setSaving] = useState(false)

  const changes = {
    ...(etd && etd !== shipment.etd ? { etd } : {}),
    ...(eta && eta !== shipment.eta ? { eta } : {}),
  }

  const submit = async () => {
    setSaving(true)
    setError(null)

    try {
      const result = await api<{ shipment: Shipment }>(`/shipments/${shipment.id}/dates`, {
        method: 'POST',
        body: { ...changes, reason },
      })
      refresh(result.shipment)
      toast.success('Dates updated and added to the audit trail')
      onClose()
    } catch (e) {
      setError(e instanceof ApiError ? e : null)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Change ETD / ETA"
      description="Every change is kept with the old and new date, your name and the reason."
      onSubmit={submit}
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" icon={CalendarClock} loading={saving} disabled={Object.keys(changes).length === 0 || reason.trim().length < 3}>
            Save dates
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {error && !error.fieldError('etd') && !error.fieldError('eta') && <FormError messages={error.messages()} />}
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="ETD"
            error={error?.fieldError('etd')}
            hint={
              <span className="inline-flex items-center gap-1.5">
                Planned {formatDate(shipment.etd_original)} <DelayBadge days={daysBetween(shipment.etd_original, etd || null)} />
              </span>
            }
          >
            <DatePicker value={etd} onChange={setEtd} />
          </Field>
          <Field
            label="ETA"
            error={error?.fieldError('eta')}
            hint={
              <span className="inline-flex items-center gap-1.5">
                Planned {formatDate(shipment.eta_original)} <DelayBadge days={daysBetween(shipment.eta_original, eta || null)} />
              </span>
            }
          >
            <DatePicker value={eta} min={etd || undefined} onChange={setEta} />
          </Field>
        </div>
        <Field label="Reason" error={error?.fieldError('reason')}>
          <Textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={2} placeholder="e.g. Vessel delayed by congestion in Colombo" required />
        </Field>
      </div>
    </Modal>
  )
}

export function DeliveryDateDialog({
  shipment,
  open,
  onClose,
  initialScope,
}: {
  shipment: Shipment
  open: boolean
  onClose: () => void
  initialScope: CargoScope
}) {
  const refresh = useRefreshShipment()
  const [scope, setScope] = useState<CargoScope>(initialScope)
  const [date, setDate] = useState('')
  const [reason, setReason] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [saving, setSaving] = useState(false)

  const submit = async () => {
    setSaving(true)
    setError(null)

    try {
      const result = await api<{ shipment: Shipment }>(`/shipments/${shipment.id}/dates`, {
        method: 'POST',
        body: {
          customer_delivery_date: date,
          reason,
          ...(scope.type === 'container' ? { container_ids: [scope.containerId] } : {}),
          ...(scope.type === 'items' ? { cargo_item_ids: scope.itemIds } : {}),
        },
      })
      refresh(result.shipment)
      toast.success('Customer delivery date updated')
      onClose()
    } catch (e) {
      setError(e instanceof ApiError ? e : null)
    } finally {
      setSaving(false)
    }
  }

  const scopeValue = scope.type === 'container' ? `container:${scope.containerId}` : scope.type

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Change customer delivery date"
      description="The first date set is the baseline; later changes show as days delayed."
      onSubmit={submit}
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" icon={CalendarClock} loading={saving} disabled={!date || reason.trim().length < 3}>
            Save date
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {error && !error.fieldError('customer_delivery_date') && <FormError messages={error.messages()} />}
        <Field label="Apply to">
          <Select
            value={scopeValue}
            onValueChange={(value) => {
              if (value === 'shipment') setScope({ type: 'shipment' })
              else if (value === 'items' && initialScope.type === 'items') setScope(initialScope)
              else setScope({ type: 'container', containerId: Number(value.split(':')[1]) })
            }}
          >
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="shipment">All packages in the shipment</SelectItem>
              {initialScope.type === 'items' && <SelectItem value="items">{initialScope.itemIds.length} selected packages</SelectItem>}
              {shipment.containers?.map((container) => (
                <SelectItem key={container.id} value={`container:${container.id}`}>
                  Container {container.container_no} ({container.items?.length ?? 0} packages)
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Field>
        <Field label="Customer delivery date" error={error?.fieldError('customer_delivery_date')}>
          <DatePicker value={date} min={shipment.etd ?? undefined} onChange={setDate} />
        </Field>
        <Field label="Reason" error={error?.fieldError('reason')}>
          <Textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={2} placeholder="e.g. Customer asked to postpone delivery" required />
        </Field>
      </div>
    </Modal>
  )
}
