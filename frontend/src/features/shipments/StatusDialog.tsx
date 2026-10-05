import { RotateCcw } from 'lucide-react'
import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { DatePicker } from '@/components/ui/date-picker'
import { Field, FormError } from '@/components/ui/field'
import { Modal } from '@/components/ui/modal'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { api, ApiError } from '@/lib/api'
import { cn } from '@/lib/cn'
import { todayIso } from '@/lib/format'
import { useMeta, useRefreshShipment } from '@/lib/queries'
import { rankOf, STATUS_ORDER, STATUS_STYLES } from '@/lib/status'
import type { CargoItem, CargoStatus, Shipment } from '@/lib/types'

export type CargoScope = { type: 'shipment' } | { type: 'container'; containerId: number } | { type: 'items'; itemIds: number[] }

const HINTS: Partial<Record<CargoStatus, string>> = {
  in_transit: 'Departure - also sets the ETD to this date.',
  arrived: 'Arrival at the destination port - also sets the ETA to this date.',
  in_storage: 'Unloaded into the storage facility.',
  delivered: 'Delivered to the customer - also sets the customer delivery date.',
}

export function StatusDialog({
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
  const { data: meta } = useMeta()
  const refresh = useRefreshShipment()
  const [scope, setScope] = useState<CargoScope>(initialScope)
  const [chosen, setChosen] = useState<CargoStatus | null>(null)
  const [correction, setCorrection] = useState(false)
  const [date, setDate] = useState(todayIso())
  const [reason, setReason] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [saving, setSaving] = useState(false)

  const allItems = useMemo(() => shipment.containers?.flatMap((c) => c.items ?? []) ?? [], [shipment])
  const items = useMemo<CargoItem[]>(() => {
    if (scope.type === 'container') return allItems.filter((item) => item.container_id === scope.containerId)
    if (scope.type === 'items') return allItems.filter((item) => scope.itemIds.includes(item.id))
    return allItems
  }, [scope, allItems])

  const targets = useMemo(() => {
    const statuses = new Set(items.map((item) => item.status))
    const nextOf = (status: CargoStatus) => meta?.statuses.find((s) => s.value === status)?.next ?? []

    return STATUS_ORDER.filter((candidate) => {
      if (scope.type === 'items' && !meta?.statuses.find((s) => s.value === candidate)?.item_level) return false
      if (statuses.size === 1 && statuses.has(candidate)) return false

      return [...statuses].every((current) =>
        current === candidate || (correction ? rankOf(candidate) < rankOf(current) : nextOf(current).includes(candidate)),
      )
    })
  }, [items, meta, scope.type, correction])

  const target = chosen && targets.includes(chosen) ? chosen : null

  const submit = async () => {
    if (!target) return
    setSaving(true)
    setError(null)

    try {
      const result = await api<{ changed: number; shipment: Shipment }>(`/shipments/${shipment.id}/status`, {
        method: 'POST',
        body: {
          status: target,
          correction,
          reason: reason || null,
          occurred_at: date,
          ...(scope.type === 'container' ? { container_ids: [scope.containerId] } : {}),
          ...(scope.type === 'items' ? { cargo_item_ids: scope.itemIds } : {}),
        },
      })
      refresh(result.shipment)
      toast.success(`${result.changed} package${result.changed === 1 ? '' : 's'} moved to ${STATUS_STYLES[target].label.toLowerCase()}`)
      onClose()
    } catch (e) {
      setError(e instanceof ApiError ? e : null)
    } finally {
      setSaving(false)
    }
  }

  const selectedItemIds = initialScope.type === 'items' ? initialScope.itemIds : []

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="lg"
      title="Change status"
      description="Departure and arrival apply to whole containers; storage and delivery can also be set per package."
      onSubmit={submit}
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={saving} disabled={!target || (correction && reason.trim() === '')}>
            {correction ? 'Correct status' : 'Change status'}
          </Button>
        </>
      }
    >
      <div className="space-y-5">
        {error && <FormError messages={error.messages()} />}

        <Field label="Apply to">
          <div className="grid gap-2 sm:grid-cols-3">
            <ScopeOption active={scope.type === 'shipment'} onClick={() => setScope({ type: 'shipment' })} title="Whole shipment" detail={`${allItems.length} packages`} />
            <ScopeOption
              active={scope.type === 'container'}
              onClick={() => setScope({ type: 'container', containerId: shipment.containers?.[0]?.id ?? 0 })}
              title="One container"
              detail={`${shipment.containers?.length ?? 0} in this shipment`}
            />
            <ScopeOption
              active={scope.type === 'items'}
              disabled={selectedItemIds.length === 0}
              onClick={() => setScope({ type: 'items', itemIds: selectedItemIds })}
              title="Selected packages"
              detail={selectedItemIds.length ? `${selectedItemIds.length} selected` : 'Select in the table'}
            />
          </div>
          {scope.type === 'container' && (
            <Select value={String(scope.containerId)} onValueChange={(value) => setScope({ type: 'container', containerId: Number(value) })}>
              <SelectTrigger className="mt-2 w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {shipment.containers?.map((container) => (
                  <SelectItem key={container.id} value={String(container.id)}>
                    {container.container_no} · {container.status_label} · {container.items?.length ?? 0} packages
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </Field>

        <Field label="New status">
          {targets.length === 0 ? (
            <p className="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-500">
              {correction
                ? 'There is no earlier status to go back to for this selection.'
                : 'No next step is possible for all of the selected cargo. Select fewer packages, or a container whose cargo shares one status.'}
            </p>
          ) : (
            <div className="grid gap-2 sm:grid-cols-2">
              {targets.map((status) => {
                const style = STATUS_STYLES[status]
                const Icon = style.icon
                return (
                  <button
                    key={status}
                    type="button"
                    onClick={() => setChosen(status)}
                    className={cn(
                      'flex items-start gap-3 rounded-xl p-3 text-left ring-1 transition ring-inset',
                      target === status ? 'bg-brand-50/60 ring-2 ring-brand-500' : 'ring-slate-200 hover:bg-slate-50',
                    )}
                  >
                    <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-lg', style.soft)}>
                      <Icon className="size-4" />
                    </span>
                    <span>
                      <span className="block text-sm font-medium text-slate-900">{style.label}</span>
                      {!correction && HINTS[status] && <span className="mt-0.5 block text-xs text-slate-500">{HINTS[status]}</span>}
                    </span>
                  </button>
                )
              })}
            </div>
          )}
        </Field>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="When did it happen?" error={error?.fieldError('occurred_at')}>
            <DatePicker value={date} max={todayIso()} onChange={setDate} />
          </Field>
          <label className="mt-7 flex items-start gap-2.5 text-sm text-slate-700">
            <Checkbox checked={correction} onCheckedChange={(checked) => setCorrection(checked === true)} className="mt-0.5" />
            <span>
              <span className="inline-flex items-center gap-1 font-medium">
                <RotateCcw className="size-3.5" /> Correction
              </span>
              <span className="block text-xs text-slate-500">Move back to an earlier status to fix a mistake.</span>
            </span>
          </label>
        </div>

        <Field
          label={correction ? 'Reason (required)' : 'Reason (optional)'}
          error={error?.fieldError('reason')}
          hint="Shown in the history together with your name."
        >
          <Textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={2} placeholder="e.g. Vessel berthed in Orkanger" />
        </Field>
      </div>
    </Modal>
  )
}

function ScopeOption({
  active,
  disabled,
  onClick,
  title,
  detail,
}: {
  active: boolean
  disabled?: boolean
  onClick: () => void
  title: string
  detail: string
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={cn(
        'rounded-xl p-3 text-left ring-1 transition ring-inset disabled:cursor-not-allowed disabled:opacity-50',
        active ? 'bg-brand-50/60 ring-2 ring-brand-500' : 'ring-slate-200 hover:bg-slate-50',
      )}
    >
      <span className="block text-sm font-medium text-slate-900">{title}</span>
      <span className="mt-0.5 block text-xs text-slate-500">{detail}</span>
    </button>
  )
}
