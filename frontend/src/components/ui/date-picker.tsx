import { CalendarDays, CalendarRange, Check, ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react'
import { useState } from 'react'
import type { DateRange } from 'react-day-picker'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { useMediaQuery } from '@/hooks/use-media-query'
import { cn } from '@/lib/cn'
import { formatDate, formatDateRange, fromIsoDate, toIsoDate } from '@/lib/format'

interface DatePickerProps {
  value: string
  onChange: (value: string) => void
  min?: string
  max?: string
  placeholder?: string
  className?: string
}

export function DatePicker({ value, onChange, min, max, placeholder = 'Pick a date', className }: DatePickerProps) {
  const [open, setOpen] = useState(false)
  const selected = value ? fromIsoDate(value) : undefined

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn('tabular w-full justify-start px-3 font-normal', !value && 'text-muted-foreground', className)}
        >
          <CalendarDays className="text-muted-foreground" />
          {value ? formatDate(value) : placeholder}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-auto p-0">
        <Calendar
          mode="single"
          weekStartsOn={1}
          selected={selected}
          defaultMonth={selected ?? (min ? fromIsoDate(min) : undefined)}
          disabled={[...(min ? [{ before: fromIsoDate(min) }] : []), ...(max ? [{ after: fromIsoDate(max) }] : [])]}
          onSelect={(date) => {
            if (!date) return
            onChange(toIsoDate(date))
            setOpen(false)
          }}
        />
      </PopoverContent>
    </Popover>
  )
}

export interface DatePreset {
  label: string
  from: string
  to: string
}

interface Period {
  from: string | null
  to: string | null
}

interface PeriodPickerProps extends Period {
  onChange: (period: Period) => void
  presets: DatePreset[]
  anyLabel?: string
  align?: 'start' | 'end'
  className?: string
}

export function PeriodPicker({ from, to, onChange, presets, anyLabel, align = 'start', className }: PeriodPickerProps) {
  const [open, setOpen] = useState(false)
  const [custom, setCustom] = useState(false)
  const [draft, setDraft] = useState<DateRange | undefined>()
  const wide = useMediaQuery('(min-width: 768px)')
  const preset = presets.find((candidate) => candidate.from === from && candidate.to === to)
  const isCustom = Boolean(from || to) && !preset
  const highlighted = Boolean(anyLabel) && Boolean(from || to)

  const openChange = (next: boolean) => {
    setOpen(next)
    setCustom(false)
  }

  const choose = (period: Period) => {
    onChange(period)
    openChange(false)
  }

  const startCustom = () => {
    setDraft(undefined)
    setCustom(true)
  }

  return (
    <Popover open={open} onOpenChange={openChange}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn(
            'w-full justify-start gap-2 px-3 font-normal',
            highlighted && 'border-brand-300 bg-brand-50 text-brand-800 hover:bg-brand-100/60 hover:text-brand-900',
            className,
          )}
        >
          <CalendarRange className={highlighted ? 'text-brand-600' : 'text-muted-foreground'} />
          <span className="flex min-w-0 items-baseline gap-2">
            <span className="tabular truncate font-medium">
              {preset?.label ?? (isCustom ? formatDateRange(from, to) : (anyLabel ?? 'Pick a period'))}
            </span>
            {preset && <span className="tabular truncate text-xs text-muted-foreground">{formatDateRange(from, to)}</span>}
          </span>
          <ChevronDown className="ml-auto text-muted-foreground" />
        </Button>
      </PopoverTrigger>
      <PopoverContent align={align} className="w-auto gap-0 p-0">
        {custom ? (
          <div>
            <div className="flex items-center gap-1 border-b border-slate-100 p-1.5">
              <Button variant="ghost" size="sm" icon={ChevronLeft} onClick={() => setCustom(false)}>
                Back
              </Button>
              <p className="text-sm font-medium text-slate-900">Custom range</p>
            </div>
            <Calendar
              mode="range"
              weekStartsOn={1}
              numberOfMonths={wide ? 2 : 1}
              selected={draft}
              defaultMonth={from ? fromIsoDate(from) : undefined}
              onSelect={setDraft}
            />
            <div className="flex items-center justify-between gap-4 border-t border-slate-100 px-3 py-2.5">
              <p className="tabular text-sm text-slate-600">
                {draft?.from ? formatDateRange(toIsoDate(draft.from), draft.to ? toIsoDate(draft.to) : null) : 'Pick the first and last day'}
              </p>
              <Button
                size="sm"
                disabled={!draft?.from || !draft.to}
                onClick={() => {
                  if (draft?.from && draft.to) choose({ from: toIsoDate(draft.from), to: toIsoDate(draft.to) })
                }}
              >
                Apply
              </Button>
            </div>
          </div>
        ) : (
          <div className="w-max min-w-64 p-1">
            {anyLabel && <PeriodOption label={anyLabel} checked={!from && !to} onClick={() => choose({ from: null, to: null })} />}
            {presets.map((option) => (
              <PeriodOption
                key={option.label}
                label={option.label}
                detail={formatDateRange(option.from, option.to)}
                checked={option === preset}
                onClick={() => choose({ from: option.from, to: option.to })}
              />
            ))}
            <div className="-mx-1 my-1 h-px bg-border" />
            <button
              type="button"
              onClick={startCustom}
              className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm outline-none hover:bg-accent focus-visible:bg-accent"
            >
              <CalendarDays className="size-4 text-muted-foreground" />
              <span className="flex min-w-0 flex-1 flex-col">
                <span>Custom range…</span>
                {isCustom && <span className="tabular text-xs text-muted-foreground">{formatDateRange(from, to)}</span>}
              </span>
              <ChevronRight className="size-4 text-muted-foreground" />
            </button>
          </div>
        )}
      </PopoverContent>
    </Popover>
  )
}

function PeriodOption({ label, detail, checked, onClick }: { label: string; detail?: string; checked: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      aria-pressed={checked}
      onClick={onClick}
      className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm outline-none hover:bg-accent focus-visible:bg-accent"
    >
      <span className={cn('flex-1 whitespace-nowrap', checked && 'font-medium text-slate-900')}>{label}</span>
      {detail && <span className="tabular ml-4 text-xs whitespace-nowrap text-muted-foreground">{detail}</span>}
      <Check className={cn('size-4 text-brand-600', !checked && 'invisible')} />
    </button>
  )
}
