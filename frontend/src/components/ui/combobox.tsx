import { ChevronsUpDown } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/cn'

interface ComboboxProps {
  label: string
  value: string | null
  onChange: (value: string | null) => void
  options: string[]
  allLabel: string
  searchPlaceholder: string
  emptyText?: string
  align?: 'start' | 'end'
  className?: string
}

export function Combobox({
  label,
  value,
  onChange,
  options,
  allLabel,
  searchPlaceholder,
  emptyText = 'No match found.',
  align = 'start',
  className,
}: ComboboxProps) {
  const [open, setOpen] = useState(false)

  const choose = (next: string | null) => {
    onChange(next)
    setOpen(false)
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          aria-label={`${label}: ${value ?? allLabel}`}
          className={cn(
            'w-full justify-between px-3 font-normal',
            value && 'border-brand-300 bg-brand-50 text-brand-800 hover:bg-brand-100/60 hover:text-brand-900',
            className,
          )}
        >
          <span className="truncate">{value ?? allLabel}</span>
          <ChevronsUpDown className="text-muted-foreground" />
        </Button>
      </PopoverTrigger>
      <PopoverContent align={align} className="w-(--radix-popover-trigger-width) min-w-64 p-0">
        <Command>
          <CommandInput placeholder={searchPlaceholder} />
          <CommandList>
            <CommandEmpty>{emptyText}</CommandEmpty>
            <CommandGroup>
              <CommandItem value={allLabel} data-checked={value === null} onSelect={() => choose(null)}>
                {allLabel}
              </CommandItem>
              {options.map((option) => (
                <CommandItem key={option} value={option} data-checked={value === option} onSelect={() => choose(option)}>
                  <span className="truncate">{option}</span>
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
