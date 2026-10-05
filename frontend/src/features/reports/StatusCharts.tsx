import { Bar, BarChart, CartesianGrid, Label, LabelList, Pie, PieChart, Rectangle, XAxis, YAxis } from 'recharts'
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from '@/components/ui/chart'
import { formatNumber, formatWeight } from '@/lib/format'
import { STATUS_ORDER, STATUS_STYLES } from '@/lib/status'
import type { CargoStatus, StatusOverviewReport } from '@/lib/types'

const chartConfig = Object.fromEntries(
  STATUS_ORDER.map((status) => [status, { label: STATUS_STYLES[status].label, color: STATUS_STYLES[status].chart }]),
) satisfies ChartConfig

export function CargoByStatus({ report }: { report: StatusOverviewReport }) {
  const total = report.totals.items
  const data = report.statuses
    .filter((row) => row.items > 0)
    .map((row) => ({ status: row.status, packages: row.items, fill: `var(--color-${row.status})` }))

  return (
    <div className="@container">
      <div className="flex flex-col items-center gap-6 @lg:flex-row">
        <ChartContainer config={chartConfig} className="aspect-square w-full max-w-56 shrink-0">
          <PieChart>
            <ChartTooltip content={<ChartTooltipContent nameKey="status" hideLabel />} />
            <Pie data={data} dataKey="packages" nameKey="status" innerRadius="64%" outerRadius="94%" stroke="white" strokeWidth={2}>
              <Label
                content={({ viewBox }) =>
                  viewBox && 'cx' in viewBox && 'cy' in viewBox ? (
                    <text x={viewBox.cx} y={viewBox.cy} textAnchor="middle" dominantBaseline="middle">
                      <tspan x={viewBox.cx} y={viewBox.cy} className="fill-slate-900 text-3xl font-semibold">
                        {formatNumber(total)}
                      </tspan>
                      <tspan x={viewBox.cx} y={(viewBox.cy ?? 0) + 24} className="fill-slate-500 text-xs">
                        packages
                      </tspan>
                    </text>
                  ) : null
                }
              />
            </Pie>
          </PieChart>
        </ChartContainer>

        <div className="w-full min-w-0 flex-1">
          <ul className="space-y-2.5">
            {STATUS_ORDER.map((status) => {
              const packages = report.statuses.find((row) => row.status === status)?.items ?? 0
              return (
                <li key={status} className="flex items-center gap-2.5 text-sm">
                  <span className="size-2.5 shrink-0 rounded-sm" style={{ backgroundColor: STATUS_STYLES[status].chart }} />
                  <span className="whitespace-nowrap text-slate-600">{STATUS_STYLES[status].label}</span>
                  <span className="tabular ml-auto font-medium text-slate-900">{formatNumber(packages)}</span>
                  <span className="tabular w-10 text-right text-xs text-slate-400">{total ? Math.round((packages / total) * 100) : 0}%</span>
                </li>
              )
            })}
          </ul>
          <p className="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
            {report.totals.containers} containers · {report.totals.shipments} shipments · {formatWeight(report.totals.gross_weight_kg)}
          </p>
        </div>
      </div>
    </div>
  )
}

interface BreakdownRow {
  name: string
  counts: Partial<Record<CargoStatus, number>>
  total: number
}

export function StatusBreakdownChart({ rows }: { rows: BreakdownRow[] }) {
  const data = rows.map((row) => ({
    name: row.name,
    total: row.total,
    ...Object.fromEntries(STATUS_ORDER.map((status) => [status, row.counts[status] ?? 0])),
  }))

  return (
    <ChartContainer config={chartConfig} className="aspect-auto w-full" style={{ height: rows.length * 40 + 64 }}>
      <BarChart data={data} layout="vertical" margin={{ top: 0, right: 36, bottom: 0, left: 0 }} barSize={20}>
        <CartesianGrid horizontal={false} stroke="var(--border)" />
        <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} tickMargin={8} />
        <YAxis type="category" dataKey="name" width={170} tickLine={false} axisLine={false} tickMargin={8} tick={(props) => <CategoryTick {...props} />} />
        <ChartTooltip cursor={{ fill: 'var(--muted)' }} itemSorter={byStatus} content={<ChartTooltipContent indicator="line" />} />
        <ChartLegend itemSorter={byStatus} content={<ChartLegendContent />} />
        {STATUS_ORDER.map((status, index) => (
          <Bar
            key={status}
            dataKey={status}
            stackId="cargo"
            fill={`var(--color-${status})`}
            stroke="white"
            strokeWidth={2}
            shape={(props) => <Rectangle {...props} radius={endsStack(props.payload, index) ? [0, 4, 4, 0] : 0} />}
          >
            {index === STATUS_ORDER.length - 1 && (
              <LabelList dataKey="total" position="right" offset={10} className="tabular fill-slate-700 text-xs font-medium" />
            )}
          </Bar>
        ))}
      </BarChart>
    </ChartContainer>
  )
}

function endsStack(row: Record<string, number> | undefined, index: number): boolean {
  return Boolean(row) && STATUS_ORDER.slice(index + 1).every((status) => !row?.[status])
}

function byStatus(item: { dataKey?: unknown }): number {
  return STATUS_ORDER.indexOf(item.dataKey as CargoStatus)
}

function CategoryTick({ x, y, payload }: { x?: number | string; y?: number | string; payload?: { value?: string } }) {
  const name = payload?.value ?? ''

  return (
    <text x={x} y={y} dy={4} textAnchor="end" className="fill-slate-500 text-xs">
      <title>{name}</title>
      {name.length > 18 ? `${name.slice(0, 17)}…` : name}
    </text>
  )
}
