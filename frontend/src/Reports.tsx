import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { api, ApiError } from './api'
import { useFilters, useList, useLookups, useSession, displayDate, displayQuantity } from './state'
import { Empty, ErrorNotice, Forbidden, Loading, PageTitle, Pagination } from './ui'

type StockRow = {
  store_id: number
  store_name: string
  product_id: number
  product_name: string
  sku: string
  base_uom: string
  quantity: string
  reorder_level: string
  incoming: string
  outgoing: string
  net: string
  movement_count: number
}
type AuditRow = {
  id: number
  actor_id: number | null
  action: string
  subject_type: string
  subject_id: number
  request_id: string | null
  metadata: Record<string, unknown>
  created_at: string
}
export function ReportSummary() {
  const { user } = useSession()
  const query = useQuery({
    queryKey: ['report-summary'],
    queryFn: ({ signal }) =>
      api.get<{
        low_stock_positions: number
        active_products: number
        active_stores: number
        pending_transfers: number
      }>('dashboard/summary', signal),
    enabled: user.permissions.includes('reports.view'),
  })
  if (!user.permissions.includes('reports.view')) return null
  return (
    <section className="panel report-summary">
      <div>
        <h3>Stock attention</h3>
        <p>
          Active products below their reorder level, including products with no recorded balance.
        </p>
      </div>
      <ErrorNotice error={query.error} retry={() => void query.refetch()} />
      {query.isLoading ? (
        <Loading />
      ) : (
        query.data && (
          <Link className="button secondary" to="/reports?view=low-stock">
            {query.data.data.low_stock_positions} low-stock positions
          </Link>
        )
      )}
    </section>
  )
}
export function Reports({ audit = false }: { audit?: boolean }) {
  const { user } = useSession()
  const filters = useFilters()
  const lookups = useLookups()
  const [exporting, setExporting] = useState(false)
  const [exportError, setExportError] = useState<unknown>(null)
  const allowed = user.permissions.includes(audit ? 'audit.view' : 'reports.view')
  const view = ['stock-summary', 'low-stock', 'movements'].includes(
    filters.params.get('view') ?? '',
  )
    ? filters.params.get('view')!
    : 'stock-summary'
  const params = new URLSearchParams(filters.params)
  params.delete('view')
  const query = useList<StockRow | AuditRow>(
    audit ? 'audit-logs' : 'reports/' + view,
    params.toString(),
    allowed,
  )
  async function download() {
    setExporting(true)
    setExportError(null)
    try {
      const response = await fetch('/api/v1/reports/movements/export?' + params.toString(), {
        credentials: 'same-origin',
        headers: { Accept: 'text/csv' },
      })
      if (!response.ok) {
        if ([401, 419].includes(response.status))
          window.dispatchEvent(new Event('stockflow:session-expired'))
        const body = await response.json().catch(() => null)
        throw new ApiError(
          response.status,
          body?.error?.details?.[0]?.message ?? body?.error?.message ?? 'Export failed. Try again.',
          body?.meta?.request_id,
        )
      }
      const url = URL.createObjectURL(await response.blob())
      const link = document.createElement('a')
      link.href = url
      link.download = 'stockflow-movements.csv'
      link.click()
      setTimeout(() => URL.revokeObjectURL(url), 1000)
    } catch (error) {
      setExportError(error)
    } finally {
      setExporting(false)
    }
  }
  if (!allowed) return <Forbidden />
  return (
    <>
      <PageTitle
        title={audit ? 'Audit history' : 'Inventory reports'}
        subtitle={
          audit
            ? 'Read-only accountability records within your access.'
            : 'Current stock and exact movement totals, grouped by store and product.'
        }
      />
      <section className="panel">
        <div className="toolbar">
          {!audit && (
            <select
              aria-label="Report type"
              value={view}
              onChange={(e) => {
                filters.update('view', e.target.value)
                setExportError(null)
              }}
            >
              <option value="stock-summary">Stock summary</option>
              <option value="low-stock">Low stock</option>
              <option value="movements">Movements</option>
            </select>
          )}
          {!audit && (
            <>
              <select
                aria-label="Store filter"
                value={params.get('store_id') ?? ''}
                onChange={(e) => filters.update('store_id', e.target.value)}
              >
                <option value="">All stores</option>
                {lookups.stores.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
              <select
                aria-label="Product filter"
                value={params.get('product_id') ?? ''}
                onChange={(e) => filters.update('product_id', e.target.value)}
              >
                <option value="">All products</option>
                {lookups.products.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.sku} · {p.name}
                  </option>
                ))}
              </select>
            </>
          )}
          {(audit || view === 'movements') && (
            <>
              {(['date_from', 'date_to'] as const).map((name) => (
                <label className="date-filter" key={name}>
                  {name === 'date_from' ? 'From (UTC)' : 'To (UTC)'}
                  <input
                    type="date"
                    aria-label={name === 'date_from' ? 'From date' : 'To date'}
                    value={params.get(name) ?? ''}
                    onChange={(e) => filters.update(name, e.target.value)}
                  />
                </label>
              ))}
            </>
          )}
          {!audit && view === 'movements' && user.permissions.includes('reports.export') && (
            <button
              className="secondary"
              disabled={exporting || !params.get('date_from') || !params.get('date_to')}
              onClick={() => void download()}
            >
              {exporting ? 'Exporting…' : 'Export movements CSV'}
            </button>
          )}
        </div>
        <ErrorNotice error={query.error ?? lookups.error} retry={() => void query.refetch()} />
        <ErrorNotice error={exportError} />
        {query.isLoading ? (
          <Loading />
        ) : query.data?.data.length ? (
          <>
            <div className="table-scroll">
              <table>
                <thead>
                  <tr>
                    {(audit
                      ? ['When', 'Action', 'Actor', 'Subject', 'Details']
                      : view === 'movements'
                        ? ['Product', 'Store', 'Unit', 'Incoming', 'Outgoing', 'Net', 'Movements']
                        : ['Product', 'Store', 'Unit', 'Available', 'Reorder level']
                    ).map((t) => (
                      <th key={t}>{t}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {query.data.data.map((row) =>
                    'action' in row ? (
                      <tr key={row.id}>
                        <td data-label="When">{displayDate(row.created_at)}</td>
                        <td data-label="Action">{row.action}</td>
                        <td data-label="Actor">{row.actor_id ?? 'System'}</td>
                        <td data-label="Subject">
                          {row.subject_type} #{row.subject_id}
                        </td>
                        <td data-label="Details">
                          <details>
                            <summary>View details</summary>
                            <pre className="audit-metadata">
                              {JSON.stringify(row.metadata, null, 2)}
                            </pre>
                            <small>Request: {row.request_id ?? '—'}</small>
                          </details>
                        </td>
                      </tr>
                    ) : (
                      <tr key={row.store_id + ':' + row.product_id}>
                        <td data-label="Product">
                          <strong>{row.product_name}</strong>
                          <small>{row.sku}</small>
                        </td>
                        <td data-label="Store">{row.store_name}</td>
                        <td data-label="Unit">{row.base_uom}</td>
                        {view === 'movements' ? (
                          <>
                            <td data-label="Incoming">{displayQuantity(row.incoming)}</td>
                            <td data-label="Outgoing">{displayQuantity(row.outgoing)}</td>
                            <td data-label="Net">{displayQuantity(row.net)}</td>
                            <td data-label="Movements">{row.movement_count}</td>
                          </>
                        ) : (
                          <>
                            <td data-label="Available">{displayQuantity(row.quantity)}</td>
                            <td data-label="Reorder level">{displayQuantity(row.reorder_level)}</td>
                          </>
                        )}
                      </tr>
                    ),
                  )}
                </tbody>
              </table>
            </div>
            <Pagination meta={query.data.meta} onPage={(p) => filters.update('page', p)} />
          </>
        ) : (
          !query.error && (
            <Empty
              title="No matching records"
              text="Adjust the filters or choose another report."
            />
          )
        )}
        <p className="table-note">
          {audit
            ? 'Store-scoped events require access to every affected store. Global events are visible only to System Admins.'
            : view === 'movements'
              ? 'UTC date bounds are inclusive. CSV contains individual movements, limited to 366 days and 10,000 rows. Totals are never combined across different products or units.'
              : 'Active stores and products only. Missing balance records count as zero; low stock means available quantity is below the reorder level.'}
        </p>
      </section>
    </>
  )
}
