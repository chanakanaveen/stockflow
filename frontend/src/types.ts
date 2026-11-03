export interface User {
  id: number
  name: string
  email: string
  is_active: boolean
  roles: string[]
  permissions: string[]
  store_ids: number[]
  assigned_store_ids?: number[]
}
export interface Store {
  id: number
  code: string
  name: string
  location_label: string | null
  is_active: boolean
}
export interface Category {
  id: number
  name: string
  is_active: boolean
}
export interface Product {
  id: number
  sku: string
  name: string
  category_id: number
  base_uom: string
  reorder_level: string
  is_active: boolean
}
export interface Line {
  id?: number
  product_id: number
  quantity: string
}
export interface StockDocument {
  id: number
  number: string
  type: 'receipt' | 'issue'
  store_id: number
  status: string
  reference: string | null
  remarks: string | null
  created_by: number
  posted_by: number | null
  posted_at: string | null
  created_at: string
  lines: Line[]
}
export interface Transfer {
  id: number
  number: string
  from_store_id: number
  to_store_id: number
  status: string
  created_by: number
  submitted_by: number | null
  approved_by: number | null
  rejected_by: number | null
  created_at: string
  submitted_at: string | null
  posted_at: string | null
  rejection_reason: string | null
  lines: Line[]
}
export interface Balance {
  id: number
  store_id: number
  product_id: number
  quantity: string
}
export interface Ledger {
  id: number
  store_id: number
  product_id: number
  movement_type: string
  source_type: string
  source_id: number
  quantity_delta: string
  balance_after: string
  posted_at: string
}
export interface Envelope<T> {
  data: T
  meta: {
    request_id: string
    current_page?: number
    last_page?: number
    total?: number
    per_page?: number
  }
}
