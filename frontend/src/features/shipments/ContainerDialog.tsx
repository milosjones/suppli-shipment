import { useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Field, FormError } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Modal } from '@/components/ui/modal'
import { api, ApiError } from '@/lib/api'
import { useRefreshShipment } from '@/lib/queries'
import type { Container, Shipment } from '@/lib/types'

export function ContainerDialog({
  shipment,
  container,
  onClose,
}: {
  shipment: Shipment
  container: Container
  onClose: () => void
}) {
  const refresh = useRefreshShipment()
  const [sealNo, setSealNo] = useState(container.seal_no ?? '')
  const [type, setType] = useState(container.container_type ?? '')
  const [error, setError] = useState<ApiError | null>(null)
  const [saving, setSaving] = useState(false)

  const submit = async () => {
    setSaving(true)
    setError(null)

    try {
      await api(`/containers/${container.id}`, {
        method: 'PATCH',
        body: { seal_no: sealNo || null, container_type: type || null },
      })
      const updated = await api<{ data: Shipment }>(`/shipments/${shipment.id}`)
      refresh(updated.data)
      toast.success(`Container ${container.container_no} updated`)
      onClose()
    } catch (e) {
      setError(e instanceof ApiError ? e : null)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={`Container ${container.container_no}`}
      description="Seal number and container type are maintained in this service."
      onSubmit={submit}
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={saving}>
            Save
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {error && <FormError messages={error.messages()} />}
        <Field label="Seal no" error={error?.fieldError('seal_no')}>
          <Input value={sealNo} onChange={(e) => setSealNo(e.target.value)} placeholder="e.g. SL-1001" />
        </Field>
        <Field label="Container type" hint="ISO size/type, e.g. 45UT, 40OT, 22UT, 45GP" error={error?.fieldError('container_type')}>
          <Input value={type} onChange={(e) => setType(e.target.value.toUpperCase())} maxLength={10} />
        </Field>
      </div>
    </Modal>
  )
}
