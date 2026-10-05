import type { FormEvent, ReactNode } from 'react'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { cn } from '@/lib/cn'

interface ModalProps {
  open: boolean
  onClose: () => void
  title: ReactNode
  description?: ReactNode
  children: ReactNode
  footer?: ReactNode
  size?: 'md' | 'lg' | 'xl'
  onSubmit?: () => void
}

const WIDTHS = { md: 'sm:max-w-lg', lg: 'sm:max-w-2xl', xl: 'sm:max-w-4xl' }

export function Modal({ open, onClose, title, description, children, footer, size = 'md', onSubmit }: ModalProps) {
  const content = (
    <>
      <DialogHeader className="gap-1 border-b border-slate-100 px-6 py-4 pr-14">
        <DialogTitle className="text-base leading-normal font-semibold text-slate-900">{title}</DialogTitle>
        {description && <DialogDescription>{description}</DialogDescription>}
      </DialogHeader>
      <div className="max-h-[70vh] overflow-y-auto px-6 py-5">{children}</div>
      {footer && <DialogFooter className="border-t border-slate-100 bg-slate-50/70 px-6 py-3">{footer}</DialogFooter>}
    </>
  )

  return (
    <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
      <DialogContent
        className={cn('gap-0 overflow-hidden rounded-2xl bg-white p-0 shadow-2xl', WIDTHS[size])}
        {...(description ? {} : { 'aria-describedby': undefined })}
      >
        {onSubmit ? (
          <form
            onSubmit={(event: FormEvent) => {
              event.preventDefault()
              onSubmit()
            }}
          >
            {content}
          </form>
        ) : (
          content
        )}
      </DialogContent>
    </Dialog>
  )
}
