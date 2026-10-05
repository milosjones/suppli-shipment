import { cva, type VariantProps } from 'class-variance-authority'
import { LoaderCircle, type LucideIcon } from 'lucide-react'
import { Slot } from 'radix-ui'
import type { ComponentProps } from 'react'
import { cn } from '@/lib/cn'

const buttonVariants = cva(
  "inline-flex shrink-0 items-center justify-center gap-2 rounded-md text-sm font-medium whitespace-nowrap transition-colors outline-none select-none focus-visible:ring-3 focus-visible:ring-ring/40 disabled:pointer-events-none disabled:opacity-60 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground shadow-sm shadow-brand-900/10 hover:bg-brand-700',
        outline:
          'border border-input bg-background text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 aria-expanded:bg-slate-50',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-brand-100',
        ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 aria-expanded:bg-slate-100',
        destructive: 'bg-destructive text-white shadow-sm hover:bg-rose-700 focus-visible:ring-destructive/30',
        link: 'text-primary underline-offset-4 hover:underline',
      },
      size: {
        default: 'h-9 px-3.5',
        sm: "h-8 gap-1.5 px-2.5 text-xs [&_svg:not([class*='size-'])]:size-3.5",
        lg: 'h-10 px-5',
        icon: 'size-9',
        'icon-sm': 'size-8',
        'icon-xs': "size-6 rounded-sm [&_svg:not([class*='size-'])]:size-3.5",
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
)

interface ButtonProps extends ComponentProps<'button'>, VariantProps<typeof buttonVariants> {
  asChild?: boolean
  icon?: LucideIcon
  loading?: boolean
}

function Button({
  className,
  variant,
  size,
  asChild = false,
  icon: Icon,
  loading = false,
  type = 'button',
  children,
  ...props
}: ButtonProps) {
  const classes = cn(buttonVariants({ variant, size, className }))

  if (asChild) {
    return (
      <Slot.Root data-slot="button" className={classes} {...props}>
        {children}
      </Slot.Root>
    )
  }

  return (
    <button data-slot="button" type={type} className={classes} {...props} disabled={props.disabled || loading}>
      {loading ? <LoaderCircle className="animate-spin" /> : Icon && <Icon aria-hidden />}
      {children}
    </button>
  )
}

export { Button, buttonVariants }
