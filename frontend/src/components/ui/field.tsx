import type { ReactNode } from 'react'
import { Label } from '@/components/ui/label'

export function Field({
  label,
  hint,
  error,
  children,
  className,
}: {
  label?: ReactNode
  hint?: ReactNode
  error?: string
  children: ReactNode
  className?: string
}) {
  return (
    <div className={className}>
      {label && <Label className="mb-2 text-slate-700">{label}</Label>}
      {children}
      {error ? (
        <p className="mt-1.5 text-xs font-medium text-destructive">{error}</p>
      ) : (
        hint && <p className="mt-1.5 text-xs text-muted-foreground">{hint}</p>
      )}
    </div>
  )
}

export function FormError({ messages }: { messages: string[] }) {
  if (messages.length === 0) return null

  return (
    <div className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-700">
      {messages.length === 1 ? (
        messages[0]
      ) : (
        <ul className="list-disc space-y-0.5 pl-4">
          {messages.map((message) => (
            <li key={message}>{message}</li>
          ))}
        </ul>
      )}
    </div>
  )
}
