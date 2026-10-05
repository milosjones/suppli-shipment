import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogMedia,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/cn'

interface ConfirmDialogProps {
  open: boolean
  title: ReactNode
  description: ReactNode
  confirmLabel: string
  icon?: LucideIcon
  destructive?: boolean
  loading?: boolean
  onConfirm: () => void
  onCancel: () => void
}

export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  icon: Icon,
  destructive = false,
  loading = false,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  return (
    <AlertDialog open={open} onOpenChange={(next) => !next && !loading && onCancel()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          {Icon && (
            <AlertDialogMedia className={cn('size-12 rounded-full', destructive ? 'bg-rose-50 text-rose-600' : 'bg-brand-50 text-brand-600')}>
              <Icon className="size-6" />
            </AlertDialogMedia>
          )}
          <AlertDialogTitle className="text-base font-semibold text-slate-900">{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={loading}>Cancel</AlertDialogCancel>
          <Button variant={destructive ? 'destructive' : 'default'} loading={loading} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
