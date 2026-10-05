import { Ban, CircleCheck, Copy, KeyRound, Plus, Trash2, Webhook } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { toast } from 'sonner'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardHeader } from '@/components/ui/card'
import { ConfirmDialog } from '@/components/ui/confirm-dialog'
import { Checkbox } from '@/components/ui/checkbox'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/ui/feedback'
import { Field, FormError } from '@/components/ui/field'
import { IconButton } from '@/components/ui/icon-button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { Segmented } from '@/components/ui/segmented'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { WebhookDialog, WebhooksPanel } from '@/features/admin/WebhooksPanel'
import { useConfirmation } from '@/hooks/use-confirmation'
import { api, ApiError } from '@/lib/api'
import { formatDate, relativeTime } from '@/lib/format'
import { useApiClients, useApiMutation } from '@/lib/queries'
import type { ApiClient, ApiToken } from '@/lib/types'

const ABILITIES = [
  { value: 'write', label: 'Change shipments', detail: 'Register shipments, change status and dates.' },
  { value: 'references:write', label: 'Update Suppli references', detail: 'Customer, proforma invoice, tag, storage facility …' },
]

type Tab = 'clients' | 'webhooks'

export function ApiClientsPage() {
  const [params, setParams] = useSearchParams()
  const tab: Tab = params.get('tab') === 'webhooks' ? 'webhooks' : 'clients'
  const [creating, setCreating] = useState(false)
  const [webhookForm, setWebhookForm] = useState({ open: false, key: 0 })
  const addWebhook = () => setWebhookForm((form) => ({ open: true, key: form.key + 1 }))

  return (
    <>
      <PageHeader
        title="API clients"
        description="Integrations such as Suppli use API tokens instead of passwords and get status and date changes pushed to their webhooks."
        actions={
          tab === 'clients' ? (
            <Button icon={Plus} onClick={() => setCreating(true)}>
              New API client
            </Button>
          ) : (
            <Button icon={Plus} onClick={addWebhook}>
              Add webhook
            </Button>
          )
        }
      />
      <Segmented
        className="mb-5"
        value={tab}
        onChange={(value) => setParams(value === 'clients' ? {} : { tab: value }, { replace: true })}
        options={[
          { value: 'clients', label: 'API clients', icon: KeyRound },
          { value: 'webhooks', label: 'Webhooks', icon: Webhook },
        ]}
      />
      {tab === 'clients' ? <ApiClientList onCreate={() => setCreating(true)} /> : <WebhooksPanel onAdd={addWebhook} />}

      <CreateClientDialog open={creating} onClose={() => setCreating(false)} />
      <WebhookDialog
        key={webhookForm.key}
        open={webhookForm.open}
        webhook={null}
        onClose={() => setWebhookForm((form) => ({ ...form, open: false }))}
      />
    </>
  )
}

function ApiClientList({ onCreate }: { onCreate: () => void }) {
  const { data: clients, isLoading, error, refetch } = useApiClients()
  const [tokenFor, setTokenFor] = useState<ApiClient | null>(null)
  const statusChange = useConfirmation<ApiClient>()
  const changing = statusChange.target
  const revoking = useConfirmation<{ client: ApiClient; token: ApiToken }>()

  const setActive = useApiMutation(
    (client: ApiClient) => api(`/api-clients/${client.id}`, { method: 'PATCH', body: { is_active: !client.is_active } }),
    [['api-clients']],
  )
  const revoke = useApiMutation(
    ({ client, tokenId }: { client: ApiClient; tokenId: number }) => api(`/api-clients/${client.id}/tokens/${tokenId}`, { method: 'DELETE' }),
    [['api-clients']],
  )

  return (
    <>
      {isLoading ? (
        <Card>
          <TableSkeleton rows={3} columns={4} />
        </Card>
      ) : error ? (
        <Card>
          <ErrorState error={error} onRetry={() => void refetch()} />
        </Card>
      ) : !clients || clients.length === 0 ? (
        <Card>
          <EmptyState
            icon={KeyRound}
            title="No API clients"
            description="Create one for Suppli, then issue a token and store it in Suppli's settings."
            action={
              <Button icon={Plus} onClick={onCreate}>
                New API client
              </Button>
            }
          />
        </Card>
      ) : (
        <div className="space-y-5">
          {clients.map((client) => (
            <Card key={client.id} className="overflow-hidden">
              <CardHeader
                title={
                  <span className="inline-flex items-center gap-2">
                    {client.name}
                    {!client.is_active && <Badge className="bg-slate-100 text-slate-500 ring-slate-400/20">Disabled</Badge>}
                  </span>
                }
                description={`Created ${formatDate(client.created_at)}`}
                icon={KeyRound}
                actions={
                  client.is_active ? (
                    <>
                      <Button size="sm" variant="outline" icon={Plus} onClick={() => setTokenFor(client)}>
                        Issue token
                      </Button>
                      <Button size="sm" variant="ghost" icon={Ban} onClick={() => statusChange.ask(client)}>
                        Disable
                      </Button>
                    </>
                  ) : (
                    <Button size="sm" variant="outline" icon={CircleCheck} onClick={() => statusChange.ask(client)}>
                      Enable
                    </Button>
                  )
                }
              />
              {(client.tokens?.length ?? 0) === 0 ? (
                <p className="px-5 py-4 text-sm text-slate-500">No tokens.</p>
              ) : (
                <table className="min-w-full text-sm">
                  <thead className="bg-slate-50/80 text-left text-xs font-medium text-slate-500">
                    <tr>
                      <th className="py-2 pr-3 pl-5">Token</th>
                      <th className="px-3 py-2">Abilities</th>
                      <th className="px-3 py-2">Last used</th>
                      <th className="px-3 py-2">Expires</th>
                      <th className="px-3 py-2 pr-5" />
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {client.tokens!.map((token) => (
                      <tr key={token.id}>
                        <td className="py-2.5 pr-3 pl-5 font-medium text-slate-900">{token.name}</td>
                        <td className="px-3 py-2.5">
                          <div className="flex flex-wrap gap-1">
                            {token.abilities.length === 0 ? (
                              <Badge className="bg-slate-50 text-slate-600 ring-slate-500/15">read only</Badge>
                            ) : (
                              token.abilities.map((ability) => (
                                <Badge key={ability} className="bg-brand-50 text-brand-700 ring-brand-600/20">
                                  {ability}
                                </Badge>
                              ))
                            )}
                          </div>
                        </td>
                        <td className="px-3 py-2.5 text-slate-500">{token.last_used_at ? relativeTime(token.last_used_at) : 'Never'}</td>
                        <td className="px-3 py-2.5 text-slate-500">{token.expires_at ? formatDate(token.expires_at) : 'Never'}</td>
                        <td className="px-3 py-2.5 pr-5">
                          <div className="flex justify-end">
                            <IconButton
                              label="Revoke token"
                              icon={Trash2}
                              className="hover:bg-rose-50 hover:text-rose-600"
                              onClick={() => revoking.ask({ client, token })}
                            />
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </Card>
          ))}
        </div>
      )}

      <IssueTokenDialog client={tokenFor} onClose={() => setTokenFor(null)} />
      <ConfirmDialog
        open={statusChange.open}
        icon={changing?.is_active ? Ban : CircleCheck}
        destructive={changing?.is_active ?? false}
        title={`${changing?.is_active ? 'Disable' : 'Enable'} ${changing?.name ?? ''}?`}
        description={
          changing?.is_active
            ? 'Its tokens stop working until you enable it again. To remove a token for good, revoke it.'
            : 'Its tokens work again right away, so Suppli can reconnect without changes.'
        }
        confirmLabel={changing?.is_active ? 'Disable' : 'Enable'}
        loading={setActive.isPending}
        onCancel={statusChange.close}
        onConfirm={() => {
          if (!changing) return
          setActive.mutate(changing, {
            onSuccess: () => {
              toast.success(`${changing.name} ${changing.is_active ? 'disabled' : 'enabled'}`)
              statusChange.close()
            },
          })
        }}
      />
      <ConfirmDialog
        open={revoking.open}
        icon={Trash2}
        destructive
        title={`Revoke the token “${revoking.target?.token.name ?? ''}”?`}
        description={`Integrations that use it can no longer reach the API as ${revoking.target?.client.name ?? 'this client'}. Issue a new token if it is still needed.`}
        confirmLabel="Revoke"
        loading={revoke.isPending}
        onCancel={revoking.close}
        onConfirm={() => {
          const target = revoking.target
          if (!target) return
          revoke.mutate(
            { client: target.client, tokenId: target.token.id },
            {
              onSuccess: () => {
                toast.success('Token revoked')
                revoking.close()
              },
            },
          )
        }}
      />
    </>
  )
}

function CreateClientDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [name, setName] = useState('Suppli integration')
  const create = useApiMutation(() => api('/api-clients', { method: 'POST', body: { name } }), [['api-clients']])

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="New API client"
      onSubmit={() => create.mutate(undefined, { onSuccess: () => { toast.success('API client created'); onClose() } })}
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={create.isPending}>
            Create
          </Button>
        </>
      }
    >
      {create.error instanceof ApiError && <FormError messages={create.error.messages()} />}
      <Field label="Name" hint="Shown in the shipment history for changes made through this client.">
        <Input value={name} onChange={(e) => setName(e.target.value)} required />
      </Field>
    </Modal>
  )
}

function IssueTokenDialog({ client, onClose }: { client: ApiClient | null; onClose: () => void }) {
  const [name, setName] = useState('production')
  const [abilities, setAbilities] = useState<string[]>(['write', 'references:write'])
  const [expires, setExpires] = useState('365')
  const [token, setToken] = useState<string | null>(null)

  const issue = useApiMutation(
    () =>
      api<{ token: string }>(`/api-clients/${client!.id}/tokens`, {
        method: 'POST',
        body: { name, abilities, expires_in_days: expires === 'never' ? null : Number(expires) },
      }),
    [['api-clients']],
  )

  const close = () => {
    setToken(null)
    issue.reset()
    onClose()
  }

  return (
    <Modal
      open={client !== null}
      onClose={close}
      title={token ? 'Copy the token now' : `Issue token for ${client?.name ?? ''}`}
      description={token ? 'It will not be shown again. Store it in Suppli as a bearer token.' : undefined}
      onSubmit={token ? close : () => issue.mutate(undefined, { onSuccess: (result) => setToken(result.token) })}
      footer={
        token ? (
          <Button type="submit">Done</Button>
        ) : (
          <>
            <Button variant="outline" onClick={close}>
              Cancel
            </Button>
            <Button type="submit" loading={issue.isPending}>
              Issue token
            </Button>
          </>
        )
      }
    >
      {token ? (
        <div className="flex items-center gap-2">
          <code className="block flex-1 overflow-x-auto rounded-lg bg-ink-900 px-3 py-2.5 font-mono text-xs text-brand-100">{token}</code>
          <Button
            variant="outline"
            icon={Copy}
            onClick={() => {
              void navigator.clipboard.writeText(token)
              toast.success('Token copied')
            }}
          >
            Copy
          </Button>
        </div>
      ) : (
        <div className="space-y-4">
          {issue.error instanceof ApiError && <FormError messages={issue.error.messages()} />}
          <Field label="Token name">
            <Input value={name} onChange={(e) => setName(e.target.value)} required />
          </Field>
          <Field label="Abilities" hint="Without abilities the token can only read.">
            <div className="space-y-2">
              {ABILITIES.map((ability) => (
                <label key={ability.value} className="flex items-start gap-2.5 text-sm">
                  <Checkbox
                    className="mt-0.5"
                    checked={abilities.includes(ability.value)}
                    onCheckedChange={(checked) =>
                      setAbilities(checked === true ? [...abilities, ability.value] : abilities.filter((a) => a !== ability.value))
                    }
                  />
                  <span>
                    <span className="font-medium text-slate-800">{ability.label}</span>
                    <span className="block text-xs text-slate-500">{ability.detail}</span>
                  </span>
                </label>
              ))}
            </div>
          </Field>
          <Field label="Expires">
            <Select value={expires} onValueChange={setExpires}>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="30">In 30 days</SelectItem>
                <SelectItem value="90">In 90 days</SelectItem>
                <SelectItem value="365">In 1 year</SelectItem>
                <SelectItem value="never">Never</SelectItem>
              </SelectContent>
            </Select>
          </Field>
        </div>
      )}
    </Modal>
  )
}
