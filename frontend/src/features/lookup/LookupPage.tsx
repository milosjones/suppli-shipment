import { Container as ContainerIcon, FileText, Package, ScanSearch, Search, Tag, Truck, Users } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { PageHeader } from '@/components/layout/PageHeader'
import { CategoryBadge, StatusBadge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { EmptyState, ErrorState, Skeleton } from '@/components/ui/feedback'
import { Input } from '@/components/ui/input'
import { Segmented } from '@/components/ui/segmented'
import { cn } from '@/lib/cn'
import { formatDate } from '@/lib/format'
import { useLookup } from '@/lib/queries'
import { Route } from '@/features/shipments/parts'

type LookupType = 'all' | 'container' | 'invoice' | 'tag' | 'package' | 'product' | 'customer'

const TYPES: { value: LookupType; label: string; icon: typeof Search }[] = [
  { value: 'all', label: 'Everything', icon: Search },
  { value: 'container', label: 'Container / AWB', icon: ContainerIcon },
  { value: 'invoice', label: 'Proforma invoice', icon: FileText },
  { value: 'tag', label: 'Tag', icon: Tag },
  { value: 'package', label: 'Package', icon: Package },
  { value: 'product', label: 'Product', icon: Truck },
  { value: 'customer', label: 'Customer', icon: Users },
]

const EXAMPLES = ['HLBU8073625', '2606018', '862600578', 'DFColle', 'Finnmark']

const FIELD_LABELS: Record<string, string> = {
  container_no: 'container',
  proforma_invoice_no: 'proforma invoice',
  exporter_reference: "exporter's ref.",
  package_no: 'package',
  tag_number: 'tag',
  product_code: 'product code',
  product_description: 'product',
  net_type: 'net type',
  customer_name: 'customer',
  customer_po: 'customer PO',
}

export function LookupPage() {
  const [params, setParams] = useSearchParams()
  const [text, setText] = useState(params.get('q') ?? '')
  const query = params.get('q') ?? ''
  const type = (params.get('type') ?? 'all') as LookupType
  const { data, isFetching, isLoading, error, refetch } = useLookup(query, type)

  useEffect(() => {
    const timer = setTimeout(() => {
      if (text !== query) setParams((p) => ({ ...Object.fromEntries(p), q: text }), { replace: true })
    }, 300)
    return () => clearTimeout(timer)
  }, [text, query, setParams])

  return (
    <>
      <PageHeader title="Lookup" description="Find any container, proforma invoice, tag, package, product or customer." />

      <Card className="p-4">
        <div className="relative">
          <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
          <Input
            autoFocus
            value={text}
            onChange={(e) => setText(e.target.value)}
            placeholder="e.g. HLBU8073625, GNP-2606018, 862600578 or Finnmark"
            className="h-12 pl-12 text-base md:text-base"
          />
        </div>
        <Segmented
          className="mt-3"
          options={TYPES}
          value={type}
          onChange={(value) => setParams((p) => ({ ...Object.fromEntries(p), type: value }), { replace: true })}
        />
      </Card>

      <div className="mt-5">
        {query.trim().length < 2 ? (
          <Card>
            <EmptyState
              icon={ScanSearch}
              title="Start typing to search"
              description="Tag numbers match with or without the GNP- prefix; names ignore upper and lower case."
              action={
                <div className="flex flex-wrap justify-center gap-2">
                  {EXAMPLES.map((example) => (
                    <button
                      key={example}
                      type="button"
                      onClick={() => setText(example)}
                      className="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700 hover:bg-brand-50 hover:text-brand-700"
                    >
                      {example}
                    </button>
                  ))}
                </div>
              }
            />
          </Card>
        ) : isLoading ? (
          <div className="space-y-4">
            {[0, 1].map((i) => (
              <div key={i} className="card space-y-3 p-5">
                <Skeleton className="h-5 w-56" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
              </div>
            ))}
          </div>
        ) : error ? (
          <Card>
            <ErrorState error={error} onRetry={() => void refetch()} />
          </Card>
        ) : !data || data.total_items === 0 ? (
          <Card>
            <EmptyState icon={Search} title={`Nothing found for “${query}”`} description="Try another spelling or search everything." />
          </Card>
        ) : (
          <div className={cn('space-y-4 transition-opacity', isFetching && 'opacity-60')}>
            <p className="text-sm text-slate-500">
              {data.total_items} package{data.total_items === 1 ? '' : 's'} in {data.results.length} shipment
              {data.results.length === 1 ? '' : 's'}
              {data.truncated && ' (showing the first 200)'}
            </p>
            {data.results.map(({ shipment, containers }) => (
              <Card key={shipment.id} className="overflow-hidden">
                <Link
                  to={`/shipments/${shipment.id}`}
                  className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-3 hover:bg-slate-100/70"
                >
                  <div className="flex flex-wrap items-center gap-3">
                    <span className="font-semibold text-slate-900">{shipment.reference}</span>
                    <StatusBadge status={shipment.status} />
                    <Route origin={shipment.origin_port} destination={shipment.destination_port} method={shipment.shipping_method} />
                  </div>
                  <span className="tabular text-sm text-slate-500">
                    ETD {formatDate(shipment.etd)} · ETA {formatDate(shipment.eta)}
                  </span>
                </Link>
                {containers.map((container) => (
                  <div key={container.id}>
                    <p className="flex items-center gap-2 px-5 pt-3 font-mono text-xs font-semibold text-slate-600">
                      <ContainerIcon className="size-3.5 text-slate-400" /> {container.container_no}
                      {container.container_type && <span className="font-sans font-normal text-slate-400">{container.container_type}</span>}
                    </p>
                    <ul className="divide-y divide-slate-100">
                      {container.items.map((item) => (
                        <li key={item.id} className="flex flex-wrap items-center gap-x-6 gap-y-2 px-5 py-3 text-sm">
                          <div className="w-36">
                            <p className="font-mono text-xs font-semibold text-slate-900">{item.package_no}</p>
                            <p className="text-xs text-slate-500">{item.tag_number ?? 'No tag'}</p>
                          </div>
                          <div className="flex min-w-0 flex-1 items-center gap-1.5">
                            <CategoryBadge code={item.product_category_code} />
                            <span className="truncate text-slate-700">{item.product_description ?? '—'}</span>
                          </div>
                          <div className="w-44 truncate text-slate-700">{item.customer_name ?? '—'}</div>
                          <div className="tabular w-28 text-slate-500">{item.proforma_invoice_no ?? '—'}</div>
                          <StatusBadge status={item.status} />
                          <div className="flex flex-wrap gap-1">
                            {item.matched_on?.map((field) => (
                              <span key={field} className="rounded bg-brand-50 px-1.5 py-0.5 text-[11px] font-medium text-brand-700">
                                {FIELD_LABELS[field] ?? field}
                              </span>
                            ))}
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </Card>
            ))}
          </div>
        )}
      </div>
    </>
  )
}
