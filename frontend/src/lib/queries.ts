import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '@/auth/useAuth'
import { api } from '@/lib/api'
import type {
  ApiClient,
  Company,
  DelayedReport,
  HistoryGroup,
  ImportRun,
  LookupResult,
  Meta,
  Paginated,
  Shipment,
  StatusOverviewReport,
  UpcomingReport,
  User,
  WebhookDelivery,
  WebhookDeliveryStatus,
  WebhookEndpoint,
} from '@/lib/types'

function useCompanyKey() {
  return useAuth().companyId ?? 'none'
}

export function useMeta() {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['meta', company],
    queryFn: () => api<Meta>('/meta'),
    staleTime: 5 * 60_000,
  })
}

export interface ShipmentFilters {
  q?: string
  status?: string[]
  destination_port?: string
  shipping_method?: string
  shipping_line?: string
  customer?: string
  eta_from?: string
  eta_to?: string
  delayed?: boolean
  overdue?: boolean
  sort?: string
  page?: number
}

export function useShipments(filters: ShipmentFilters) {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['shipments', company, filters],
    queryFn: ({ signal }) => api<Paginated<Shipment>>('/shipments', { query: { ...filters, per_page: 20 }, signal }),
    placeholderData: keepPreviousData,
  })
}

export function useShipment(id: string | undefined) {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['shipment', company, id],
    queryFn: () => api<{ data: Shipment }>(`/shipments/${id}`).then((r) => r.data),
    enabled: Boolean(id),
  })
}

export function useShipmentHistory(id: string | undefined, type?: 'status' | 'dates') {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['shipment-history', company, id, type ?? 'all'],
    queryFn: () => api<{ data: HistoryGroup[] }>(`/shipments/${id}/history`, { query: { type } }).then((r) => r.data),
    enabled: Boolean(id),
  })
}

export function useRefreshShipment() {
  const queryClient = useQueryClient()
  const company = useCompanyKey()

  return (shipment: Shipment) => {
    queryClient.setQueryData(['shipment', company, String(shipment.id)], shipment)
    void queryClient.invalidateQueries({ queryKey: ['shipment-history', company, String(shipment.id)] })
    void queryClient.invalidateQueries({ queryKey: ['shipments'] })
    void queryClient.invalidateQueries({ queryKey: ['reports'] })
  }
}

export function useLookup(q: string, type: string) {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['lookup', company, q, type],
    queryFn: ({ signal }) => api<LookupResult>('/lookup', { query: { q, type }, signal }),
    enabled: q.trim().length >= 2,
    placeholderData: keepPreviousData,
  })
}

export function useUpcomingReport(params: { from: string; to: string; destination_port?: string; customer?: string }) {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['reports', 'upcoming', company, params],
    queryFn: () => api<UpcomingReport>('/reports/upcoming-deliveries', { query: params }),
    placeholderData: keepPreviousData,
  })
}

export function useStatusOverview(params: { destination_port?: string; customer?: string } = {}) {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['reports', 'overview', company, params],
    queryFn: () => api<StatusOverviewReport>('/reports/status-overview', { query: params }),
    placeholderData: keepPreviousData,
  })
}

export function useDelayedReport(params: { min_days: number; as_of?: string }) {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['reports', 'delayed', company, params],
    queryFn: () => api<DelayedReport>('/reports/delayed', { query: params }),
    placeholderData: keepPreviousData,
  })
}

export function useImports() {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['imports', company],
    queryFn: () => api<Paginated<ImportRun>>('/imports'),
  })
}

export function useImport(id: number | null) {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['import', company, id],
    queryFn: () => api<{ data: ImportRun }>(`/imports/${id}`).then((r) => r.data),
    enabled: id !== null,
  })
}

export function useUsers(enabled = true) {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['users', company],
    queryFn: () => api<{ data: User[] }>('/users').then((r) => r.data),
    enabled,
  })
}

export function useAllUsers(companyId: number | null, enabled = true) {
  return useQuery({
    queryKey: ['admin', 'users', companyId],
    queryFn: () => api<{ data: User[] }>('/admin/users', { query: { company_id: companyId } }).then((r) => r.data),
    enabled,
  })
}

export function useApiClients() {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['api-clients', company],
    queryFn: () => api<{ data: ApiClient[] }>('/api-clients').then((r) => r.data),
  })
}

function deliveryPollInterval(deliveries: (WebhookDelivery | null | undefined)[]): number | false {
  const waiting = deliveries.flatMap((delivery) =>
    delivery?.status === 'pending' && delivery.next_attempt_at ? [new Date(delivery.next_attempt_at).getTime()] : [],
  )
  if (waiting.length === 0) return false
  return waiting.some((time) => time - Date.now() < 180_000) ? 3000 : 30_000
}

export function useWebhooks() {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['webhooks', company],
    queryFn: () => api<{ data: WebhookEndpoint[] }>('/webhooks').then((r) => r.data),
    refetchInterval: (query) => deliveryPollInterval((query.state.data ?? []).map((webhook) => webhook.last_delivery)),
  })
}

export function useWebhookDeliveries(webhookId: number, status: WebhookDeliveryStatus | null, page: number) {
  const company = useCompanyKey()
  return useQuery({
    queryKey: ['webhooks', company, webhookId, 'deliveries', status, page],
    queryFn: ({ signal }) =>
      api<Paginated<WebhookDelivery>>(`/webhooks/${webhookId}/deliveries`, {
        query: { status: status ?? undefined, page, per_page: 10 },
        signal,
      }),
    placeholderData: keepPreviousData,
    refetchInterval: (query) => deliveryPollInterval(query.state.data?.data ?? []),
  })
}

export function useCompanies(enabled = true) {
  return useQuery({
    queryKey: ['admin', 'companies'],
    queryFn: () => api<{ data: Company[] }>('/admin/companies').then((r) => r.data),
    enabled,
  })
}

export function useApiMutation<TInput, TResult>(
  mutationFn: (input: TInput) => Promise<TResult>,
  invalidate: string[][] = [],
) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn,
    onSuccess: () => {
      invalidate.forEach((queryKey) => void queryClient.invalidateQueries({ queryKey }))
    },
  })
}
