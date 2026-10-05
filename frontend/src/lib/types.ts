export type CargoStatus = 'planned' | 'in_transit' | 'arrived' | 'in_storage' | 'delivered'
export type ShippingMethod = 'sea' | 'air' | 'road'
export type ProductCategory = 'net_pen' | 'dead_fish_collector' | 'other'
export type ChangeSource = 'manual' | 'api' | 'import' | 'provider' | 'system'
export type UserRole = 'super_admin' | 'company_admin' | 'company_user'

export type StatusCounts = Partial<Record<CargoStatus, number>>

export interface ShipmentSummary {
  status_counts: StatusCounts
  items_count: number
  containers_count: number
  customers: string[]
}

export interface CargoItem {
  id: number
  container_id: number
  package_no: string
  proforma_invoice_no: string | null
  exporter_reference: string | null
  tag_number: string | null
  product_code: string | null
  product_description: string | null
  product_category: ProductCategory
  product_category_code: 'NP' | 'DF' | null
  net_type: string | null
  customer_name: string | null
  customer_reference: string | null
  customer_po: string | null
  storage_facility: string | null
  quantity: number | null
  unit: string | null
  net_weight_kg: number | null
  gross_weight_kg: number | null
  status: CargoStatus
  status_label: string
  raw_status: string | null
  customer_delivery_date: string | null
  customer_delivery_date_original: string | null
  delivery_delay_days: number
  is_delivery_overdue: boolean
  notes: string | null
  container?: { id: number; container_no: string; container_type: string | null }
  shipment?: {
    id: number
    reference: string
    status: CargoStatus
    shipping_method: ShippingMethod
    destination_port: string
    etd: string | null
    eta: string | null
  }
  matched_on?: string[]
}

export interface Container {
  id: number
  shipment_id: number
  container_no: string
  seal_no: string | null
  container_type: string | null
  status?: CargoStatus | null
  status_label?: string | null
  status_counts?: StatusCounts
  items?: CargoItem[]
}

export interface Shipment {
  id: number
  reference: string
  external_reference: string | null
  shipping_method: ShippingMethod
  shipping_line: string | null
  origin_port: string | null
  destination_port: string
  incoterm: string | null
  etd: string | null
  etd_original: string | null
  etd_delay_days: number
  eta: string | null
  eta_original: string | null
  eta_delay_days: number
  status: CargoStatus
  status_label: string
  is_overdue: boolean
  overdue_days: number
  notes: string | null
  summary?: ShipmentSummary
  containers?: Container[]
  created_at: string
  updated_at: string
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }
}

export interface StatusHistoryEntry {
  from: CargoStatus | null
  to: CargoStatus
  is_correction: boolean
  reason: string | null
  source: ChangeSource
  items_count: number
  containers: string[]
  packages: string[]
}

export interface DateHistoryEntry {
  field: 'etd' | 'eta' | 'customer_delivery_date'
  field_label: string
  old_date: string | null
  new_date: string | null
  shift_days: number | null
  reason: string
  source: ChangeSource
  items_count: number
  containers: string[]
  packages: string[]
}

export interface HistoryGroup {
  change_group: string
  at: string
  recorded_at: string
  source: ChangeSource
  user: { id: number; name: string } | null
  status_changes: StatusHistoryEntry[]
  date_changes: DateHistoryEntry[]
}

export interface StatusMeta {
  value: CargoStatus
  label: string
  rank: number
  next: CargoStatus[]
  item_level: boolean
}

export interface Meta {
  today: string
  timezone: string
  statuses: StatusMeta[]
  shipping_methods: { value: ShippingMethod; label: string; unit_label: string }[]
  product_categories: { value: ProductCategory; label: string; code: string | null }[]
  company_roles: { value: UserRole; label: string }[]
  reference_fields: string[]
  filters: {
    destination_ports: string[]
    shipping_lines: string[]
    customers: string[]
    storage_facilities: string[]
  }
}

export interface User {
  id: number
  name: string
  email: string | null
  role: UserRole
  role_label: string
  is_service_account: boolean
  is_active: boolean
  company?: { id: number; name: string } | null
  last_login_at: string | null
  created_at: string
}

export interface CurrentUser extends User {
  permissions: { manage_companies: boolean; manage_users: boolean; select_company: boolean }
}

export interface Company {
  id: number
  name: string
  is_active: boolean
  users_count?: number
  shipments_count?: number
  created_at: string
}

export interface ApiToken {
  id: number
  name: string
  abilities: string[]
  last_used_at: string | null
  expires_at: string | null
  created_at: string
}

export interface ApiClient {
  id: number
  name: string
  is_active: boolean
  created_at: string
  tokens?: ApiToken[]
}

export type WebhookEventType = 'shipment.status_changed' | 'shipment.dates_changed' | 'webhook.test'
export type WebhookDeliveryStatus = 'pending' | 'succeeded' | 'failed'

export interface WebhookDelivery {
  id: number
  event_id: string
  event_type: WebhookEventType
  status: WebhookDeliveryStatus
  attempts: number
  max_attempts: number
  next_attempt_at: string | null
  last_attempt_at: string | null
  delivered_at: string | null
  response_status: number | null
  response_body: string | null
  error: string | null
  shipment?: { id: number; reference: string } | null
  payload: Record<string, unknown>
  created_at: string
}

export interface WebhookEndpoint {
  id: number
  url: string
  description: string | null
  events: WebhookEventType[]
  is_active: boolean
  secret?: string
  last_delivery?: WebhookDelivery | null
  created_at: string
  updated_at: string
}

export interface ImportIssue {
  row: number | null
  severity: 'error' | 'warning'
  column: string | null
  message: string
  value: string | null
}

export interface ImportRun {
  id: number
  file_name: string
  file_hash: string
  status: 'processing' | 'completed' | 'failed'
  dry_run: boolean
  total_rows: number
  imported_rows: number
  skipped_rows: number
  rejected_rows: number
  blank_rows: number
  warning_count: number
  shipments_created: number
  containers_created: number
  error_message: string | null
  normalisations?: { description: string; count: number }[]
  issues?: ImportIssue[]
  user?: { id: number; name: string } | null
  started_at: string | null
  finished_at: string | null
  duration_ms: number | null
  created_at: string
}

export interface UpcomingReport {
  from: string
  to: string
  as_of: string
  arrivals: Shipment[]
  deliveries: CargoItem[]
  overdue_arrivals: Shipment[]
  overdue_deliveries: CargoItem[]
}

export interface StatusOverviewReport {
  totals: { shipments: number; containers: number; items: number; gross_weight_kg: number }
  statuses: { status: CargoStatus; label: string; items: number; gross_weight_kg: number; containers: number; shipments: number }[]
  by_destination: { destination_port: string; counts: Record<CargoStatus, number>; total: number }[]
  by_customer: { customer_name: string; counts: Record<CargoStatus, number>; total: number }[]
}

export interface DelayedReport {
  as_of: string
  min_days: number
  shipments: { shipment: Shipment; etd_delay_days: number; eta_delay_days: number; overdue_days: number; max_delay_days: number }[]
  items: { item: CargoItem; delivery_delay_days: number; overdue_days: number; max_delay_days: number }[]
  at_risk: { item: CargoItem; days_short: number }[]
}

export interface LookupResult {
  query: string
  type: string
  total_items: number
  truncated: boolean
  results: {
    shipment: Shipment
    containers: { id: number; container_no: string; container_type: string | null; items: CargoItem[] }[]
  }[]
}

export interface TrackingSyncResult {
  providers: string[]
  applied: { provider: string; target: string; change: string }[]
  skipped: { provider: string; target: string; reason: string }[]
  shipment: Shipment
}
