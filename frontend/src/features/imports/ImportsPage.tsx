import { CheckCircle2, CircleAlert, CloudUpload, FileSpreadsheet, History, Sparkles, TriangleAlert, Upload } from 'lucide-react'
import { useRef, useState, type DragEvent } from 'react'
import { toast } from 'sonner'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardHeader } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { EmptyState, ErrorState, Skeleton, TableSkeleton } from '@/components/ui/feedback'
import { Modal } from '@/components/ui/modal'
import { Segmented } from '@/components/ui/segmented'
import { api, ApiError } from '@/lib/api'
import { cn } from '@/lib/cn'
import { formatDateTime, formatNumber } from '@/lib/format'
import { useApiMutation, useImport, useImports } from '@/lib/queries'
import type { ImportRun } from '@/lib/types'

export function ImportsPage() {
  const [result, setResult] = useState<ImportRun | null>(null)
  const [openId, setOpenId] = useState<number | null>(null)

  return (
    <>
      <PageHeader
        title="Excel import"
        description="Import the delivery plan. Rows are cleaned up; rows that cannot be imported are listed with the reason."
      />
      <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <UploadCard onResult={setResult} />
        {result ? (
          <ImportReportCard run={result} onImported={setResult} />
        ) : (
          <Card>
            <EmptyState
              icon={Sparkles}
              title="How the import works"
              description="Rows on the same voyage become one shipment and rows with the same container number one container. Packages that already exist are skipped, so you can import the same file again safely. Use “Dry run” to see the report without saving."
            />
          </Card>
        )}
      </div>
      <PastImports onOpen={setOpenId} />
      <ImportDetailModal
        id={openId}
        onClose={() => setOpenId(null)}
        onImported={(run) => {
          setOpenId(null)
          setResult(run)
        }}
      />
    </>
  )
}

function UploadCard({ onResult }: { onResult: (run: ImportRun) => void }) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [file, setFile] = useState<File | null>(null)
  const [dryRun, setDryRun] = useState(true)
  const [dragging, setDragging] = useState(false)

  const upload = useApiMutation(
    async ({ file, dryRun }: { file: File; dryRun: boolean }) => {
      const form = new FormData()
      form.append('file', file)
      form.append('dry_run', dryRun ? '1' : '0')
      try {
        return (await api<{ data: ImportRun }>('/imports', { method: 'POST', body: form })).data
      } catch (e) {
        const report = e instanceof ApiError ? (e.data as { data?: ImportRun } | null)?.data : undefined
        if (report) return report
        throw e
      }
    },
    [['imports'], ['shipments'], ['reports'], ['meta']],
  )

  const onDrop = (event: DragEvent) => {
    event.preventDefault()
    setDragging(false)
    const dropped = event.dataTransfer.files[0]
    if (dropped) setFile(dropped)
  }

  const submit = () => {
    if (!file) return
    upload.mutate(
      { file, dryRun },
      {
        onSuccess: (run) => {
          onResult(run)
          if (run.status === 'failed') toast.error('The file could not be imported')
          else if (run.dry_run) toast.info(`Dry run: ${run.imported_rows} packages would be imported`)
          else toast.success(`${run.imported_rows} packages imported`)
        },
        onError: (e) => toast.error(e instanceof ApiError ? e.messages()[0] : 'Upload failed'),
      },
    )
  }

  return (
    <Card className="xl:sticky xl:top-6">
      <CardHeader title="Upload delivery plan" description=".xlsx, up to 20 MB" icon={Upload} />
      <div className="space-y-4 p-5">
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          onDragOver={(e) => {
            e.preventDefault()
            setDragging(true)
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={onDrop}
          className={cn(
            'flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors',
            dragging ? 'border-brand-400 bg-brand-50/60' : 'border-slate-300 hover:border-brand-300 hover:bg-slate-50',
          )}
        >
          <span className="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
            {file ? <FileSpreadsheet className="size-6" /> : <CloudUpload className="size-6" />}
          </span>
          {file ? (
            <>
              <p className="mt-3 text-sm font-medium text-slate-900">{file.name}</p>
              <p className="text-xs text-slate-500">{formatNumber(file.size / 1024)} KB · click to choose another file</p>
            </>
          ) : (
            <>
              <p className="mt-3 text-sm font-medium text-slate-900">Drop the Excel file here or click to browse</p>
              <p className="text-xs text-slate-500">e.g. “Norway delivery plan Template.xlsx”</p>
            </>
          )}
        </button>
        <input
          ref={inputRef}
          type="file"
          accept=".xlsx,.xls"
          className="hidden"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
        />

        <label className="flex items-start gap-2.5 rounded-lg bg-slate-50 p-3 text-sm">
          <Checkbox checked={dryRun} onCheckedChange={(checked) => setDryRun(checked === true)} className="mt-0.5" />
          <span>
            <span className="font-medium text-slate-800">Dry run</span>
            <span className="block text-xs text-slate-500">Check the file and show the report without saving anything.</span>
          </span>
        </label>

        <Button className="w-full" icon={Upload} loading={upload.isPending} disabled={!file} onClick={submit}>
          {dryRun ? 'Check file' : 'Import file'}
        </Button>
      </div>
    </Card>
  )
}

function ImportReportCard({ run, onImported }: { run: ImportRun; onImported: (run: ImportRun) => void }) {
  const [filter, setFilter] = useState<'all' | 'error' | 'warning'>('all')
  const importChecked = useApiMutation(
    () => api<{ data: ImportRun }>(`/imports/${run.id}/commit`, { method: 'POST' }).then((response) => response.data),
    [['imports'], ['shipments'], ['reports'], ['meta']],
  )

  const importNow = () =>
    importChecked.mutate(undefined, {
      onSuccess: (imported) => {
        onImported(imported)
        toast.success(`${imported.imported_rows} packages imported`)
      },
      onError: (e) => toast.error(e instanceof ApiError ? e.message : 'The file could not be imported'),
    })
  const issues = (run.issues ?? []).filter((issue) => filter === 'all' || issue.severity === filter)
  const errors = run.issues?.filter((issue) => issue.severity === 'error').length ?? 0

  return (
    <Card className="overflow-hidden">
      <CardHeader
        title={
          <span className="inline-flex flex-wrap items-center gap-2">
            {run.file_name}
            {run.dry_run && <Badge className="bg-sky-50 text-sky-700 ring-sky-600/20">Dry run - nothing saved</Badge>}
            {run.status === 'failed' && <Badge className="bg-rose-50 text-rose-700 ring-rose-600/20">Failed</Badge>}
          </span>
        }
        description={`Import #${run.id} · ${formatDateTime(run.created_at)}${run.user ? ` · ${run.user.name}` : ''}${run.duration_ms !== null ? ` · ${formatNumber(run.duration_ms / 1000)} s` : ''}`}
        icon={run.status === 'failed' ? CircleAlert : CheckCircle2}
        actions={
          run.dry_run &&
          run.status === 'completed' && (
            <Button icon={Upload} loading={importChecked.isPending} onClick={importNow}>
              Import this file
            </Button>
          )
        }
      />

      {run.error_message ? (
        <div className="m-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{run.error_message}</div>
      ) : (
        <div className="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
          <Stat label="Rows read" value={run.total_rows} />
          <Stat label={run.dry_run ? 'Would import' : 'Imported'} value={run.imported_rows} tone="emerald" />
          <Stat label="Rejected" value={run.rejected_rows} tone={run.rejected_rows ? 'rose' : undefined} />
          <Stat label="Warnings" value={run.warning_count} tone={run.warning_count ? 'amber' : undefined} />
          <Stat label="Already imported" value={run.skipped_rows} />
          <Stat label="Blank rows" value={run.blank_rows} />
          <Stat label="Shipments created" value={run.shipments_created} />
          <Stat label="Containers created" value={run.containers_created} />
        </div>
      )}

      {(run.normalisations?.length ?? 0) > 0 && (
        <details className="group border-t border-slate-100 px-5 py-3">
          <summary className="cursor-pointer text-sm font-medium text-slate-700">
            Normalisations ({run.normalisations!.length}) - how values were cleaned up
          </summary>
          <ul className="mt-3 grid gap-1.5 sm:grid-cols-2">
            {run.normalisations!.map((n) => (
              <li key={n.description} className="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-2.5 py-1.5 text-xs">
                <span className="text-slate-700">{n.description}</span>
                <span className="tabular font-semibold text-slate-900">×{n.count}</span>
              </li>
            ))}
          </ul>
        </details>
      )}

      {(run.issues?.length ?? 0) > 0 && (
        <div className="border-t border-slate-100">
          <div className="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
            <p className="text-sm font-medium text-slate-700">Rows that need attention</p>
            <Segmented
              value={filter}
              onChange={setFilter}
              options={[
                { value: 'all', label: 'All', count: run.issues!.length },
                { value: 'error', label: 'Rejected', count: errors },
                { value: 'warning', label: 'Warnings', count: run.warning_count },
              ]}
            />
          </div>
          <div className="max-h-96 overflow-auto">
            <table className="min-w-full text-sm">
              <thead className="sticky top-0 bg-slate-50 text-left text-xs font-medium text-slate-500">
                <tr>
                  <th className="py-2 pr-3 pl-5">Row</th>
                  <th className="px-3 py-2">Column</th>
                  <th className="px-3 py-2">Problem</th>
                  <th className="px-3 py-2 pr-5">Value</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {issues.map((issue, index) => (
                  <tr key={index} className="align-top">
                    <td className="tabular py-2.5 pr-3 pl-5 font-medium text-slate-900">{issue.row ?? '—'}</td>
                    <td className="px-3 py-2.5 text-slate-600">{issue.column ?? 'File'}</td>
                    <td className="px-3 py-2.5">
                      <span className="flex items-start gap-1.5">
                        {issue.severity === 'error' ? (
                          <CircleAlert className="mt-0.5 size-4 shrink-0 text-rose-500" />
                        ) : (
                          <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-500" />
                        )}
                        <span className={issue.severity === 'error' ? 'text-rose-700' : 'text-slate-700'}>{issue.message}</span>
                      </span>
                    </td>
                    <td className="max-w-40 truncate px-3 py-2.5 pr-5 font-mono text-xs text-slate-500">{issue.value ?? ''}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </Card>
  )
}

function Stat({ label, value, tone }: { label: string; value: number; tone?: 'emerald' | 'rose' | 'amber' }) {
  const tones = { emerald: 'text-emerald-600', rose: 'text-rose-600', amber: 'text-amber-600' }
  return (
    <div className="bg-white px-5 py-3">
      <p className="text-xs text-slate-500">{label}</p>
      <p className={cn('tabular mt-0.5 text-xl font-semibold text-slate-900', tone && tones[tone])}>{formatNumber(value)}</p>
    </div>
  )
}

function PastImports({ onOpen }: { onOpen: (id: number) => void }) {
  const { data, isLoading, error, refetch } = useImports()

  return (
    <Card className="mt-5 overflow-hidden">
      <CardHeader title="Past imports" description="Every run is kept with its report and the original file." icon={History} />
      {isLoading ? (
        <TableSkeleton rows={3} columns={6} />
      ) : error ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : !data || data.data.length === 0 ? (
        <EmptyState icon={FileSpreadsheet} title="No imports yet" className="py-10" />
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-slate-50/80 text-left text-xs font-medium text-slate-500">
              <tr>
                <th className="py-2.5 pr-3 pl-5">File</th>
                <th className="px-3 py-2.5">When</th>
                <th className="px-3 py-2.5">By</th>
                <th className="px-3 py-2.5 text-right">Imported</th>
                <th className="px-3 py-2.5 text-right">Rejected</th>
                <th className="px-3 py-2.5 text-right">Warnings</th>
                <th className="px-3 py-2.5 pr-5" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.data.map((run) => (
                <tr key={run.id} className="hover:bg-slate-50/60">
                  <td className="py-3 pr-3 pl-5">
                    <p className="font-medium text-slate-900">{run.file_name}</p>
                    <p className="text-xs text-slate-500">
                      #{run.id} {run.dry_run && '· dry run'} {run.status === 'failed' && '· failed'}
                    </p>
                  </td>
                  <td className="tabular px-3 py-3 text-slate-600">{formatDateTime(run.created_at)}</td>
                  <td className="px-3 py-3 text-slate-600">{run.user?.name ?? '—'}</td>
                  <td className="tabular px-3 py-3 text-right text-slate-900">{run.imported_rows}</td>
                  <td className={cn('tabular px-3 py-3 text-right', run.rejected_rows ? 'font-semibold text-rose-600' : 'text-slate-500')}>{run.rejected_rows}</td>
                  <td className={cn('tabular px-3 py-3 text-right', run.warning_count ? 'text-amber-600' : 'text-slate-500')}>{run.warning_count}</td>
                  <td className="px-3 py-3 pr-5 text-right">
                    <Button size="sm" variant="ghost" onClick={() => onOpen(run.id)}>
                      View report
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  )
}

function ImportDetailModal({
  id,
  onClose,
  onImported,
}: {
  id: number | null
  onClose: () => void
  onImported: (run: ImportRun) => void
}) {
  const { data, isLoading } = useImport(id)

  return (
    <Modal open={id !== null} onClose={onClose} size="xl" title={`Import #${id ?? ''}`}>
      {isLoading || !data ? (
        <div className="space-y-3">
          <Skeleton className="h-6 w-1/2" />
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-40 w-full" />
        </div>
      ) : (
        <div className="-mx-6 -my-5">
          <ImportReportCard run={data} onImported={onImported} />
        </div>
      )}
    </Modal>
  )
}
