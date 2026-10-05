import { useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/ui/field'
import { Modal } from '@/components/ui/modal'
import { PasswordInput } from '@/components/ui/password-input'
import { api, ApiError } from '@/lib/api'

export function ChangePasswordDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [error, setError] = useState<ApiError | null>(null)
  const [saving, setSaving] = useState(false)

  const close = () => {
    setForm({ current_password: '', password: '', password_confirmation: '' })
    setError(null)
    onClose()
  }

  const submit = async () => {
    setSaving(true)
    setError(null)

    try {
      await api('/auth/password', { method: 'PUT', body: form })
      toast.success('Password changed. Other sessions were signed out.')
      close()
    } catch (e) {
      setError(e instanceof ApiError ? e : null)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={close}
      title="Change password"
      description="At least 8 characters."
      onSubmit={submit}
      footer={
        <>
          <Button variant="outline" onClick={close}>
            Cancel
          </Button>
          <Button type="submit" loading={saving}>
            Change password
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <Field label="Current password" error={error?.fieldError('current_password')}>
          <PasswordInput
            autoComplete="current-password"
            required
            value={form.current_password}
            onChange={(e) => setForm({ ...form, current_password: e.target.value })}
          />
        </Field>
        <Field label="New password" error={error?.fieldError('password')}>
          <PasswordInput
            autoComplete="new-password"
            required
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
          />
        </Field>
        <Field label="Repeat new password">
          <PasswordInput
            autoComplete="new-password"
            required
            value={form.password_confirmation}
            onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
          />
        </Field>
      </div>
    </Modal>
  )
}
