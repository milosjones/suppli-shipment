import type { LucideIcon } from 'lucide-react'
import { CircleAlert, CircleCheck, Clock, Copy, Eye, KeyRound, Pause, Pencil, Play, Plus, RotateCcw, Send, Trash2, Webhook } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardHeader } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { ConfirmDialog } from '@/components/ui/confirm-dialog'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/ui/feedback'
import { Field, FormError } from '@/components/ui/field'
import { IconButton } from '@/components/ui/icon-button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { Pagination } from '@/components/ui/pagination'
import { Segmented } from '@/components/ui/segmented'
import { useConfirmation } from '@/hooks/use-confirmation'
import { api, ApiError } from '@/lib/api'
import { cn } from '@/lib/cn'
import { formatDateTime, relativeTime } from '@/lib/format'
import { useApiMutation, useWebhookDeliveries, useWebhooks } from '@/lib/queries'
import type { WebhookDelivery, WebhookDeliveryStatus, WebhookEndpoint, WebhookEventType } from '@/lib/types'

const EVENTS: { value: WebhookEventType; label: string; detail: string }[] = [
  { value: 'shipment.status_changed', label: 'Status changes', detail: 'Packages moved to a new status, e.g. in transit, arrived or delivered.' },
  { value: 'shipment.dates_changed', label: 'Date changes', detail: 'ETD, ETA and customer delivery dates.' },
]

const EVENT_LABELS: Record<WebhookEventType, string> = {
  'shipment.status_changed': 'Status changed',
  'shipment.dates_changed': 'Dates changed',
  'webhook.test': 'Test event',
}

const DELIVERY_STYLES: Record<WebhookDeliveryStatus, { label: string; icon: LucideIcon; className: string }> = {
  succeeded: { label: 'Delivered', icon: CircleCheck, className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' },
  pending: { label: 'Retrying', icon: Clock, className: 'bg-amber-50 text-amber-700 ring-amber-600/20' },
  failed: { label: 'Failed', icon: CircleAlert, className: 'bg-rose-50 text-rose-700 ring-rose-600/20' },
}

export function WebhooksPanel({ onAdd }: { onAdd: () => void }) {
  const { data: webhooks, isLoading, error, refetch } = useWebhooks()
  const [editing, setEditing] = useState<{ webhook: WebhookEndpoint | null; open: boolean; key: number }>({ webhook: null, open: false, key: 0 })
  const toggling = useConfirmation<WebhookEndpoint>()
  const rotating = useConfirmation<WebhookEndpoint>()
  const deleting = useConfirmation<WebhookEndpoint>()
  const revealing = useConfirmation<string>()
  const viewing = useConfirmation<WebhookDelivery>()
  const toggled = toggling.target

  const setActive = useApiMutation(
    (webhook: WebhookEndpoint) => api(`/webhooks/${webhook.id}`, { method: 'PATCH', body: { is_active: !webhook.is_active } }),
    [['webhooks']],
  )
  const rotate = useApiMutation(
    (webhook: WebhookEndpoint) => api<{ data: WebhookEndpoint }>(`/webhooks/${webhook.id}/rotate-secret`, { method: 'POST' }).then((r) => r.data),
    [['webhooks']],
  )
  const remove = useApiMutation((webhook: WebhookEndpoint) => api(`/webhooks/${webhook.id}`, { method: 'DELETE' }), [['webhooks']])

  if (isLoading) {
    return (
      <Card>
        <TableSkeleton rows={3} columns={5} />
      </Card>
    )
  }

  if (error) {
    return (
      <Card>
        <ErrorState error={error} onRetry={() => void refetch()} />
      </Card>
    )
  }

  return (
    <>
      {!webhooks || webhooks.length === 0 ? (
        <Card>
          <EmptyState
            icon={Webhook}
            title="No webhooks"
            description="Add Suppli's endpoint URL to get status and date changes pushed as they happen, signed with a shared secret."
            action={
              <Button icon={Plus} onClick={onAdd}>
                Add webhook
              </Button>
            }
          />
        </Card>
      ) : (
        <div className="space-y-5">
          {webhooks.map((webhook) => (
            <WebhookCard
              key={webhook.id}
              webhook={webhook}
              onEdit={() => setEditing((current) => ({ webhook, open: true, key: current.key + 1 }))}
              onToggle={() => toggling.ask(webhook)}
              onRotate={() => rotating.ask(webhook)}
              onDelete={() => deleting.ask(webhook)}
              onView={viewing.ask}
            />
          ))}
        </div>
      )}

      <WebhookDialog
        key={editing.key}
        open={editing.open}
        webhook={editing.webhook}
        onClose={() => setEditing((current) => ({ ...current, open: false }))}
      />
      <DeliveryDialog delivery={viewing.target} open={viewing.open} onClose={viewing.close} />
      <Modal
        open={revealing.open}
        onClose={revealing.close}
        title="Copy the new signing secret"
        description="It will not be shown again. Update it in Suppli now: deliveries are signed with the new secret from here on."
        footer={<Button onClick={revealing.close}>Done</Button>}
      >
        {revealing.target && <SecretBox secret={revealing.target} />}
      </Modal>
      <ConfirmDialog
        open={toggling.open}
        icon={toggled?.is_active ? Pause : Play}
        title={toggled?.is_active ? 'Pause this webhook?' : 'Resume this webhook?'}
        description={
          toggled?.is_active
            ? 'Changes made while it is paused are not sent to it. Deliveries that are already waiting are kept and sent when you resume it.'
            : 'Waiting deliveries are sent right away, and new changes are sent again as they happen.'
        }
        confirmLabel={toggled?.is_active ? 'Pause' : 'Resume'}
        loading={setActive.isPending}
        onCancel={toggling.close}
        onConfirm={() => {
          if (!toggled) return
          setActive.mutate(toggled, {
            onSuccess: () => {
              toast.success(toggled.is_active ? 'Webhook paused' : 'Webhook resumed')
              toggling.close()
            },
          })
        }}
      />
      <ConfirmDialog
        open={rotating.open}
        icon={KeyRound}
        title="Rotate the signing secret?"
        description="The current secret stops working at once. Suppli has to switch to the new secret, or its signature checks will fail."
        confirmLabel="Rotate secret"
        loading={rotate.isPending}
        onCancel={rotating.close}
        onConfirm={() => {
          if (!rotating.target) return
          rotate.mutate(rotating.target, {
            onSuccess: (webhook) => {
              rotating.close()
              if (webhook.secret) revealing.ask(webhook.secret)
            },
          })
        }}
      />
      <ConfirmDialog
        open={deleting.open}
        icon={Trash2}
        destructive
        title="Delete this webhook?"
        description="Suppli stops getting changes at this URL, and its delivery log is deleted."
        confirmLabel="Delete"
        loading={remove.isPending}
        onCancel={deleting.close}
        onConfirm={() => {
          if (!deleting.target) return
          remove.mutate(deleting.target, {
            onSuccess: () => {
              toast.success('Webhook deleted')
              deleting.close()
            },
          })
        }}
      />
    </>
  )
}

function WebhookCard({
  webhook,
  onEdit,
  onToggle,
  onRotate,
  onDelete,
  onView,
}: {
  webhook: WebhookEndpoint
  onEdit: () => void
  onToggle: () => void
  onRotate: () => void
  onDelete: () => void
  onView: (delivery: WebhookDelivery) => void
}) {
  const test = useApiMutation(
    () => api<{ data: WebhookDelivery }>(`/webhooks/${webhook.id}/test`, { method: 'POST' }).then((r) => r.data),
    [['webhooks']],
  )

  const sendTest = () =>
    test.mutate(undefined, {
      onSuccess: (delivery) => {
        if (delivery.status === 'succeeded') toast.success('Test event delivered')
        else if (delivery.attempts > 0) toast.error('The endpoint did not accept the test event')
        else toast.success('Test event queued')
      },
      onError: (e) => toast.error(e instanceof ApiError ? e.message : 'The test event could not be sent'),
    })

  return (
    <Card className="overflow-hidden">
      <CardHeader
        icon={Webhook}
        title={
          <span className="flex min-w-0 items-center gap-2">
            <span className="truncate font-mono text-[13px]" title={webhook.url}>
              {webhook.url}
            </span>
            {!webhook.is_active && <Badge className="bg-slate-100 text-slate-500 ring-slate-400/20">Paused</Badge>}
          </span>
        }
        description={[webhook.description, webhook.events.map((event) => EVENT_LABELS[event]).join(' · ')].filter(Boolean).join(' - ')}
        actions={
          <>
            <Button size="sm" variant="outline" icon={Send} loading={test.isPending} disabled={!webhook.is_active} onClick={sendTest}>
              Send test
            </Button>
            <IconButton label="Edit" icon={Pencil} onClick={onEdit} />
            <IconButton label="Rotate secret" icon={KeyRound} onClick={onRotate} />
            <IconButton label={webhook.is_active ? 'Pause' : 'Resume'} icon={webhook.is_active ? Pause : Play} onClick={onToggle} />
            <IconButton label="Delete" icon={Trash2} className="hover:bg-rose-50 hover:text-rose-600" onClick={onDelete} />
          </>
        }
      />
      <DeliveryLog webhook={webhook} onView={onView} />
    </Card>
  )
}

function DeliveryLog({ webhook, onView }: { webhook: WebhookEndpoint; onView: (delivery: WebhookDelivery) => void }) {
  const [status, setStatus] = useState<'all' | WebhookDeliveryStatus>('all')
  const [page, setPage] = useState(1)
  const { data, isLoading, error, refetch } = useWebhookDeliveries(webhook.id, status === 'all' ? null : status, page)
  const resend = useApiMutation(
    (delivery: WebhookDelivery) => api(`/webhook-deliveries/${delivery.id}/retry`, { method: 'POST' }),
    [['webhooks']],
  )

  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-3 px-5 pt-4 pb-3">
        <p className="text-sm font-medium text-slate-700">Delivery log</p>
        <Segmented
          value={status}
          onChange={(value) => {
            setStatus(value)
            setPage(1)
          }}
          options={[
            { value: 'all', label: 'All' },
            { value: 'failed', label: 'Failed' },
            { value: 'pending', label: 'Waiting' },
            { value: 'succeeded', label: 'Delivered' },
          ]}
        />
      </div>
      {isLoading ? (
        <TableSkeleton rows={3} columns={5} />
      ) : error ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : !data || data.data.length === 0 ? (
        <p className="px-5 pb-5 text-sm text-slate-500">
          {status === 'all' ? 'No deliveries yet. Send a test event to check the connection.' : 'No deliveries with this status.'}
        </p>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50/80 text-left text-xs font-medium text-slate-500">
                <tr>
                  <th className="py-2 pr-3 pl-5">Event</th>
                  <th className="px-3 py-2">Status</th>
                  <th className="px-3 py-2">Attempts</th>
                  <th className="px-3 py-2">Response</th>
                  <th className="px-3 py-2">Created</th>
                  <th className="px-3 py-2 pr-5" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.data.map((delivery) => (
                  <tr key={delivery.id}>
                    <td className="py-2.5 pr-3 pl-5 whitespace-nowrap">
                      <span className="font-medium text-slate-900">{EVENT_LABELS[delivery.event_type]}</span>
                      {delivery.shipment && (
                        <Link to={`/shipments/${delivery.shipment.id}`} className="ml-2 font-medium text-brand-700 hover:underline">
                          {delivery.shipment.reference}
                        </Link>
                      )}
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      <DeliveryStatusBadge delivery={delivery} />
                      {deliveryLabel(delivery) === 'Retrying' && delivery.next_attempt_at && (
                        <span className="ml-2 text-xs text-slate-500">next {relativeTime(delivery.next_attempt_at)}</span>
                      )}
                    </td>
                    <td className="tabular px-3 py-2.5 text-slate-600">
                      {delivery.attempts}/{delivery.max_attempts}
                    </td>
                    <td className="max-w-64 px-3 py-2.5">
                      <ResponseSummary delivery={delivery} />
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap text-slate-500" title={formatDateTime(delivery.created_at)}>
                      {relativeTime(delivery.created_at)}
                    </td>
                    <td className="px-3 py-2.5 pr-5">
                      <div className="flex justify-end gap-1">
                        <IconButton label="View details" icon={Eye} onClick={() => onView(delivery)} />
                        {delivery.status !== 'pending' && (
                          <IconButton
                            label="Send again"
                            icon={RotateCcw}
                            disabled={!webhook.is_active || resend.isPending}
                            onClick={() =>
                              resend.mutate(delivery, {
                                onSuccess: () => toast.success('Delivery queued again'),
                                onError: (e) => toast.error(e instanceof ApiError ? e.message : 'The delivery could not be queued'),
                              })
                            }
                          />
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination meta={data.meta} onPage={setPage} />
        </>
      )}
    </>
  )
}

function deliveryLabel(delivery: WebhookDelivery): string {
  if (delivery.status !== 'pending') return DELIVERY_STYLES[delivery.status].label
  if (!delivery.next_attempt_at) return 'Paused'
  if (delivery.attempts === 0 || (delivery.response_status === null && delivery.error === null)) return 'Sending'
  return DELIVERY_STYLES.pending.label
}

function DeliveryStatusBadge({ delivery }: { delivery: WebhookDelivery }) {
  const style = DELIVERY_STYLES[delivery.status]
  const Icon = style.icon

  return (
    <Badge className={style.className}>
      <Icon className="size-3.5" aria-hidden />
      {deliveryLabel(delivery)}
    </Badge>
  )
}

function ResponseSummary({ delivery }: { delivery: WebhookDelivery }) {
  if (delivery.response_status !== null) {
    const ok = delivery.response_status >= 200 && delivery.response_status < 300
    return <span className={cn('tabular font-medium', ok ? 'text-emerald-700' : 'text-rose-700')}>HTTP {delivery.response_status}</span>
  }

  if (delivery.error) {
    return (
      <span className="block truncate text-rose-700" title={delivery.error}>
        {delivery.error}
      </span>
    )
  }

  return <span className="text-slate-400">—</span>
}

function DeliveryDialog({ delivery, open, onClose }: { delivery: WebhookDelivery | null; open: boolean; onClose: () => void }) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      size="lg"
      title={delivery ? [EVENT_LABELS[delivery.event_type], delivery.shipment?.reference].filter(Boolean).join(' · ') : ''}
      description={delivery && <span className="font-mono text-xs">{delivery.event_id}</span>}
      footer={<Button onClick={onClose}>Close</Button>}
    >
      {delivery && (
        <div className="space-y-4">
          <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
            <Info label="Status">
              <DeliveryStatusBadge delivery={delivery} />
            </Info>
            <Info label="Attempts">
              {delivery.attempts} of {delivery.max_attempts}
            </Info>
            <Info label="Response">{delivery.response_status !== null ? `HTTP ${delivery.response_status}` : '—'}</Info>
            <Info label="Created">{formatDateTime(delivery.created_at)}</Info>
            <Info label="Last attempt">{formatDateTime(delivery.last_attempt_at)}</Info>
            {delivery.status === 'succeeded' ? (
              <Info label="Delivered">{formatDateTime(delivery.delivered_at)}</Info>
            ) : (
              <Info label="Next attempt">{formatDateTime(delivery.next_attempt_at)}</Info>
            )}
          </dl>
          {delivery.error && (
            <div className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-700">{delivery.error}</div>
          )}
          {delivery.response_body && <CodeBlock label="Response body">{delivery.response_body}</CodeBlock>}
          <CodeBlock label="Payload">{JSON.stringify(delivery.payload, null, 2)}</CodeBlock>
        </div>
      )}
    </Modal>
  )
}

function Info({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs font-medium text-slate-500">{label}</dt>
      <dd className="mt-1 text-slate-800">{children}</dd>
    </div>
  )
}

function CodeBlock({ label, children }: { label: string; children: string }) {
  return (
    <div>
      <p className="mb-1.5 text-xs font-medium text-slate-500">{label}</p>
      <pre className="max-h-72 overflow-auto rounded-lg bg-ink-900 px-3 py-2.5 font-mono text-xs leading-relaxed text-brand-100">{children}</pre>
    </div>
  )
}

function SecretBox({ secret }: { secret: string }) {
  return (
    <div className="flex items-center gap-2">
      <code className="block flex-1 overflow-x-auto rounded-lg bg-ink-900 px-3 py-2.5 font-mono text-xs text-brand-100">{secret}</code>
      <Button
        variant="outline"
        icon={Copy}
        onClick={() => {
          void navigator.clipboard.writeText(secret)
          toast.success('Secret copied')
        }}
      >
        Copy
      </Button>
    </div>
  )
}

export function WebhookDialog({ open, webhook, onClose }: { open: boolean; webhook: WebhookEndpoint | null; onClose: () => void }) {
  const [url, setUrl] = useState(webhook?.url ?? '')
  const [description, setDescription] = useState(webhook?.description ?? '')
  const [events, setEvents] = useState<WebhookEventType[]>(webhook?.events ?? EVENTS.map((event) => event.value))
  const [secret, setSecret] = useState<string | null>(null)

  const save = useApiMutation(
    () =>
      api<{ data: WebhookEndpoint }>(webhook ? `/webhooks/${webhook.id}` : '/webhooks', {
        method: webhook ? 'PATCH' : 'POST',
        body: { url, description: description.trim() || null, events },
      }).then((response) => response.data),
    [['webhooks']],
  )

  const submit = () =>
    save.mutate(undefined, {
      onSuccess: (saved) => {
        if (saved.secret) {
          setSecret(saved.secret)
          return
        }
        toast.success('Webhook saved')
        onClose()
      },
    })

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={secret ? 'Copy the signing secret now' : webhook ? 'Edit webhook' : 'Add webhook'}
      description={
        secret
          ? 'It will not be shown again. Store it in Suppli: it checks the X-Webhook-Signature header of every delivery with it.'
          : 'Suppli gets a signed POST request for every change of the kinds you choose.'
      }
      onSubmit={secret ? onClose : submit}
      footer={
        secret ? (
          <Button type="submit">Done</Button>
        ) : (
          <>
            <Button variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={save.isPending} disabled={events.length === 0}>
              {webhook ? 'Save' : 'Add webhook'}
            </Button>
          </>
        )
      }
    >
      {secret ? (
        <SecretBox secret={secret} />
      ) : (
        <div className="space-y-4">
          {save.error instanceof ApiError && <FormError messages={save.error.messages()} />}
          <Field label="Endpoint URL" hint="A public HTTPS address that accepts POST requests and answers with a 2xx status.">
            <Input
              type="url"
              value={url}
              onChange={(e) => setUrl(e.target.value)}
              placeholder="https://suppli.example.com/webhooks/shipments"
              required
            />
          </Field>
          <Field label="Description" hint="Optional, e.g. which Suppli installation this is.">
            <Input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Suppli production" />
          </Field>
          <Field label="Events">
            <div className="space-y-2">
              {EVENTS.map((event) => (
                <label key={event.value} className="flex items-start gap-2.5 text-sm">
                  <Checkbox
                    className="mt-0.5"
                    checked={events.includes(event.value)}
                    onCheckedChange={(checked) =>
                      setEvents(checked === true ? [...events, event.value] : events.filter((value) => value !== event.value))
                    }
                  />
                  <span>
                    <span className="font-medium text-slate-800">{event.label}</span>
                    <span className="block text-xs text-slate-500">{event.detail}</span>
                  </span>
                </label>
              ))}
            </div>
          </Field>
        </div>
      )}
    </Modal>
  )
}
