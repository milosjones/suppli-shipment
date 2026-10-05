import { ArrowRight, Ban, Building2, CircleCheck, Plus } from 'lucide-react'
import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router'
import { toast } from 'sonner'
import { useAuth } from '@/auth/useAuth'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { ConfirmDialog } from '@/components/ui/confirm-dialog'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/ui/feedback'
import { Field, FormError } from '@/components/ui/field'
import { IconButton } from '@/components/ui/icon-button'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { PasswordInput } from '@/components/ui/password-input'
import { useConfirmation } from '@/hooks/use-confirmation'
import { api, ApiError } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { useApiMutation, useCompanies } from '@/lib/queries'
import type { Company } from '@/lib/types'

export function CompaniesPage() {
  const { user, companyId, selectCompany } = useAuth()
  const navigate = useNavigate()
  const { data: companies, isLoading, error, refetch } = useCompanies(user?.permissions.manage_companies ?? false)
  const [creating, setCreating] = useState(false)
  const statusChange = useConfirmation<Company>()
  const changing = statusChange.target

  const toggle = useApiMutation(
    (company: Company) => api(`/admin/companies/${company.id}`, { method: 'PATCH', body: { is_active: !company.is_active } }),
    [['admin', 'companies']],
  )

  if (!user?.permissions.manage_companies) return <Navigate to="/" replace />

  return (
    <>
      <PageHeader
        title="Companies"
        description="Each company only sees its own shipments. As super admin you can open any of them."
        actions={
          <Button icon={Plus} onClick={() => setCreating(true)}>
            New company
          </Button>
        }
      />
      <Card className="overflow-hidden">
        {isLoading ? (
          <TableSkeleton rows={3} columns={5} />
        ) : error ? (
          <ErrorState error={error} onRetry={() => void refetch()} />
        ) : !companies || companies.length === 0 ? (
          <EmptyState icon={Building2} title="No companies yet" />
        ) : (
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50/80 text-left text-xs font-medium text-slate-500">
              <tr>
                <th className="py-2.5 pr-3 pl-5">Company</th>
                <th className="px-3 py-2.5 text-right">Users</th>
                <th className="px-3 py-2.5 text-right">Shipments</th>
                <th className="px-3 py-2.5">Status</th>
                <th className="px-3 py-2.5 pr-5" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {companies.map((company) => (
                <tr key={company.id}>
                  <td className="py-3 pr-3 pl-5">
                    <p className="font-medium text-slate-900">
                      {company.name} {company.id === companyId && <Badge className="ml-1 bg-brand-50 text-brand-700 ring-brand-600/20">current</Badge>}
                    </p>
                    <p className="text-xs text-slate-500">Since {formatDate(company.created_at)}</p>
                  </td>
                  <td className="tabular px-3 py-3 text-right text-slate-700">{company.users_count}</td>
                  <td className="tabular px-3 py-3 text-right text-slate-700">{company.shipments_count}</td>
                  <td className="px-3 py-3">
                    {company.is_active ? (
                      <span className="text-emerald-700">Active</span>
                    ) : (
                      <span className="text-slate-400">Deactivated</span>
                    )}
                  </td>
                  <td className="px-3 py-3 pr-5">
                    <div className="flex justify-end gap-1">
                      <IconButton
                        label="Work in this company"
                        icon={ArrowRight}
                        onClick={() => {
                          selectCompany(company.id)
                          navigate('/')
                        }}
                      />
                      {company.is_active ? (
                        <IconButton
                          label="Deactivate company"
                          icon={Ban}
                          className="hover:bg-rose-50 hover:text-rose-600"
                          onClick={() => statusChange.ask(company)}
                        />
                      ) : (
                        <IconButton
                          label="Activate company"
                          icon={CircleCheck}
                          className="hover:bg-emerald-50 hover:text-emerald-700"
                          onClick={() => statusChange.ask(company)}
                        />
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
      <CreateCompanyDialog open={creating} onClose={() => setCreating(false)} />
      <ConfirmDialog
        open={statusChange.open}
        icon={changing?.is_active ? Ban : CircleCheck}
        destructive={changing?.is_active ?? false}
        title={`${changing?.is_active ? 'Deactivate' : 'Activate'} ${changing?.name ?? ''}?`}
        description={
          changing?.is_active
            ? 'Its users and API clients are signed out right away and cannot sign in until you activate the company again. Its shipments and history are kept.'
            : 'Its users and API clients can sign in again.'
        }
        confirmLabel={changing?.is_active ? 'Deactivate' : 'Activate'}
        loading={toggle.isPending}
        onCancel={statusChange.close}
        onConfirm={() => {
          if (!changing) return
          toggle.mutate(changing, {
            onSuccess: () => {
              toast.success(`${changing.name} ${changing.is_active ? 'deactivated' : 'activated'}`)
              statusChange.close()
            },
          })
        }}
      />
    </>
  )
}

function CreateCompanyDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [form, setForm] = useState({ name: '', adminName: '', adminEmail: '', adminPassword: '' })
  const create = useApiMutation(
    () =>
      api('/admin/companies', {
        method: 'POST',
        body: {
          name: form.name,
          ...(form.adminEmail ? { admin: { name: form.adminName, email: form.adminEmail, password: form.adminPassword } } : {}),
        },
      }),
    [['admin', 'companies']],
  )
  const error = create.error instanceof ApiError ? create.error : null

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="New company"
      description="Optionally add the first company admin, who can then invite colleagues."
      onSubmit={() =>
        create.mutate(undefined, {
          onSuccess: () => {
            toast.success(`${form.name} created`)
            setForm({ name: '', adminName: '', adminEmail: '', adminPassword: '' })
            onClose()
          },
        })
      }
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={create.isPending}>
            Create company
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {error && !error.fieldError('name') && <FormError messages={error.messages()} />}
        <Field label="Company name" error={error?.fieldError('name')}>
          <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </Field>
        <div className="rounded-xl bg-slate-50 p-4">
          <p className="mb-3 text-sm font-medium text-slate-700">First company admin (optional)</p>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Name">
              <Input value={form.adminName} onChange={(e) => setForm({ ...form, adminName: e.target.value })} required={Boolean(form.adminEmail)} />
            </Field>
            <Field label="Email" error={error?.fieldError('admin.email')}>
              <Input type="email" value={form.adminEmail} onChange={(e) => setForm({ ...form, adminEmail: e.target.value })} />
            </Field>
            <Field label="Password" className="sm:col-span-2" error={error?.fieldError('admin.password')}>
              <PasswordInput
                autoComplete="new-password"
                value={form.adminPassword}
                onChange={(e) => setForm({ ...form, adminPassword: e.target.value })}
                required={Boolean(form.adminEmail)}
              />
            </Field>
          </div>
        </div>
      </div>
    </Modal>
  )
}
