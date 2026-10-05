import type { LucideIcon } from 'lucide-react'
import type { ComponentProps } from 'react'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

interface IconButtonProps extends Omit<ComponentProps<typeof Button>, 'icon' | 'children' | 'asChild'> {
  label: string
  icon: LucideIcon
}

export function IconButton({ label, icon, variant = 'ghost', size = 'icon-sm', ...props }: IconButtonProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button variant={variant} size={size} icon={icon} aria-label={label} {...props} />
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}
