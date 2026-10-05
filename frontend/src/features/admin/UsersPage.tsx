import { Pencil, UserCheck, UserPlus, Users, UserX } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useConfirmation } from '@/hooks/use-confirmation'
import { api, ApiError } from '@/lib/api'
import { initials, relativeTime } from '@/lib/format'
import { useAllUsers, useApiMutation, useCompanies, useUsers } from '@/lib/queries'
import type { Company, User, UserRole } from '@/lib/types'

export function UsersPage() {
  const { user: me } = useAuth()
  const acrossCompanies = me?.permissions.manage_companies ?? false
  const [params, setParams] = useSearchParams()
  const companyFilter = params.get('company') ? Number(params.get('company')) : null
  const companyUsers = useUsers(!acrossCompanies)
  const everyone = useAllUsers(companyFilter, acrossCompanies)
  const { data: users, isLoading, error, refetch } = acrossCompanies ? everyone : companyUsers
  const { data: companies } = useCompanies(acrossCompanies)
  const [editing, setEditing] = useState<User | 'new' | null>(null)
  const statusChange = useConfirmation<User>()
  const changing = statusChange.target
  const setActive = useApiMutation(
    (target: User) => api(`${acrossCompanies ? '/admin/users' : '/users'}/${target.id}`, { method: 'PATCH', body: { is_active: !target.is_active } }),
    [['users'], ['admin', 'users']],
  )

  return (
    <>
      <PageHeader
        title="Users"
        description={
          acrossCompanies
            ? 'Everyone who can sign in, across all companies. Each user belongs to one company.'
            : "People who work with this company's shipments. Admins also manage users and API clients."
        }
        actions={
          <>
            {acrossCompanies && (
              <Select
                value={companyFilter ? String(companyFilter) : 'all'}
                onValueChange={(value) => setParams(value === 'all' ? {} : { company: value }, { replace: true })}
              >
                <SelectTrigger aria-label="Company" className="w-64 bg-white">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All companies</SelectItem>
                  {companies?.map((company) => (
                    <SelectItem key={company.id} value={String(company.id)}>
                      {company.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
            <Button icon={UserPlus} onClick={() => setEditing('new')}>
              Add user
            </Button>
          </>
        }
      />
      <Card className="overflow-hidden">
        {isLoading ? (
          <TableSkeleton rows={4} columns={acrossCompanies ? 6 : 5} />
        ) : error ? (
          <ErrorState error={error} onRetry={() => void refetch()} />
        ) : !users || users.length === 0 ? (
          <EmptyState icon={Users} title="No users yet" />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-slate-50/80 text-left text-xs font-medium text-slate-500">
                <tr>
                  <th className="py-2.5 pr-3 pl-5">Name</th>
                  {acrossCompanies && <th className="px-3 py-2.5">Company</th>}
                  <th className="px-3 py-2.5">Role</th>
                  <th className="px-3 py-2.5">Status</th>
                  <th className="px-3 py-2.5">Last sign-in</th>
                  <th className="px-3 py-2.5 pr-5" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {users.map((user) => (
                  <tr key={user.id}>
                    <td className="py-3 pr-3 pl-5">
                      <div className="flex items-center gap-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700">
                          {initials(user.name)}
                        </span>
                        <div>
                          <p className="font-medium text-slate-900">
                            {user.name} {user.id === me?.id && <span className="text-xs font-normal text-slate-400">(you)</span>}
                          </p>
                          <p className="text-xs text-slate-500">{user.email}</p>
                        </div>
                      </div>
                    </td>
                    {acrossCompanies && <td className="px-3 py-3 text-slate-700">{user.company?.name ?? '—'}</td>}
                    <td className="px-3 py-3">
                      <Badge
                        className={
                          user.role === 'company_user'
                            ? 'bg-slate-50 text-slate-600 ring-slate-500/15'
                            : 'bg-brand-50 text-brand-700 ring-brand-600/20'
                        }
                      >
                        {user.role_label}
                      </Badge>
                    </td>
                    <td className="px-3 py-3">
                      {user.is_active ? (
                        <span className="inline-flex items-center gap-1.5 text-emerald-700">
                          <span className="size-1.5 rounded-full bg-emerald-500" /> Active
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1.5 text-slate-400">
                          <span className="size-1.5 rounded-full bg-slate-300" /> Deactivated
                        </span>
                      )}
                    </td>
                    <td className="px-3 py-3 text-slate-500">{user.last_login_at ? relativeTime(user.last_login_at) : 'Never'}</td>
                    <td className="px-3 py-3 pr-5">
                      {user.role !== 'super_admin' && (
                        <div className="flex justify-end gap-1">
                          <IconButton label="Edit user" icon={Pencil} onClick={() => setEditing(user)} />
                          {user.id !== me?.id &&
                            (user.is_active ? (
                              <IconButton
                                label="Deactivate user"
                                icon={UserX}
                                className="hover:bg-rose-50 hover:text-rose-600"
                                onClick={() => statusChange.ask(user)}
                              />
                            ) : (
                              <IconButton
                                label="Activate user"
                                icon={UserCheck}
                                className="hover:bg-emerald-50 hover:text-emerald-700"
                                onClick={() => statusChange.ask(user)}
                              />
                            ))}
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      {editing && (
        <UserDialog
          user={editing}
          companies={acrossCompanies ? (companies ?? []) : null}
          defaultCompany={companyFilter}
          onClose={() => setEditing(null)}
        />
      )}
      <ConfirmDialog
        open={statusChange.open}
        icon={changing?.is_active ? UserX : UserCheck}
        destructive={changing?.is_active ?? false}
        title={`${changing?.is_active ? 'Deactivate' : 'Activate'} ${changing?.name ?? ''}?`}
        description={
          changing?.is_active
            ? 'They are signed out right away and can no longer sign in. Their history is kept, and you can activate them again at any time.'
            : 'They can sign in again with their current password.'
        }
        confirmLabel={changing?.is_active ? 'Deactivate' : 'Activate'}
        loading={setActive.isPending}
        onCancel={statusChange.close}
        onConfirm={() => {
          if (!changing) return
          setActive.mutate(changing, {
            onSuccess: () => {
              toast.success(`${changing.name} ${changing.is_active ? 'deactivated' : 'activated'}`)
              statusChange.close()
            },
            onError: (e) => toast.error(e instanceof ApiError ? (e.fieldError() ?? e.message) : 'Could not change the user'),
          })
        }}
      />
    </>
  )
}

function UserDialog({
  user,
  companies,
  defaultCompany,
  onClose,
}: {
  user: User | 'new'
  companies: Company[] | null
  defaultCompany: number | null
  onClose: () => void
}) {
  const isNew = user === 'new'
  const endpoint = companies ? '/admin/users' : '/users'
  const [companyId, setCompanyId] = useState(defaultCompany)
  const [form, setForm] = useState(() =>
    user === 'new'
      ? { name: '', email: '', role: 'company_user' as UserRole, password: '' }
      : { name: user.name, email: user.email ?? '', role: user.role, password: '' },
  )
  const [error, setError] = useState<ApiError | null>(null)

  const save = useApiMutation(
    () =>
      isNew
        ? api(endpoint, {
            method: 'POST',
            body: {
              ...(companies ? { company_id: companyId } : {}),
              name: form.name,
              email: form.email,
              role: form.role,
              password: form.password,
            },
          })
        : api(`${endpoint}/${(user as User).id}`, {
            method: 'PATCH',
            body: {
              name: form.name,
              email: form.email,
              role: form.role,
              ...(form.password ? { password: form.password } : {}),
            },
          }),
    [['users'], ['admin', 'users']],
  )

  const submit = () =>
    save.mutate(undefined, {
      onSuccess: () => {
        const company = companies?.find((candidate) => candidate.id === companyId)
        toast.success(!isNew ? 'User updated' : company ? `User added to ${company.name}` : 'User added')
        onClose()
      },
      onError: (e) => setError(e instanceof ApiError ? e : null),
    })

  const fieldErrors = ['company_id', 'name', 'email', 'password'].some((field) => error?.fieldError(field))

  return (
    <Modal
      open
      onClose={onClose}
      title={isNew ? 'Add user' : 'Edit user'}
      description={!isNew && companies ? (user as User).company?.name : undefined}
      onSubmit={submit}
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={save.isPending} disabled={isNew && companies !== null && !companyId}>
            {isNew ? 'Add user' : 'Save'}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {error && !fieldErrors && <FormError messages={error.messages()} />}
        {isNew && companies && (
          <Field label="Company" error={error?.fieldError('company_id')}>
            <Select value={companyId ? String(companyId) : ''} onValueChange={(value) => setCompanyId(Number(value))}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Select a company" />
              </SelectTrigger>
              <SelectContent>
                {companies.map((company) => (
                  <SelectItem key={company.id} value={String(company.id)}>
                    {company.is_active ? company.name : `${company.name} (inactive)`}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
        )}
        <Field label="Name" error={error?.fieldError('name')}>
          <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </Field>
        <Field label="Email" error={error?.fieldError('email')}>
          <Input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
        </Field>
        <Field label="Role" error={error?.fieldError('role')}>
          <Select value={form.role} onValueChange={(role) => setForm({ ...form, role: role as UserRole })}>
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="company_user">Company user - shipments, imports, reports</SelectItem>
              <SelectItem value="company_admin">Company admin - also users and API clients</SelectItem>
            </SelectContent>
          </Select>
        </Field>
        <Field
          label={isNew ? 'Password' : 'New password'}
          hint={isNew ? 'At least 8 characters. The user can change it after signing in.' : 'Leave empty to keep the current password. Setting one signs the user out.'}
          error={error?.fieldError('password')}
        >
          <PasswordInput autoComplete="new-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required={isNew} />
        </Field>
      </div>
    </Modal>
  )
}
