import { Link } from 'react-router-dom'
import { ReportSummary } from './Reports'
import {
  ArrowDownLeft,
  ArrowUpRight,
  ArrowRight,
  Package,
  Store as StoreIcon,
  ArrowLeftRight,
  Layers3,
} from 'lucide-react'
import {
  useFilters,
  useList,
  useLookups,
  useSession,
  displayDate,
  displayQuantity,
  quantityUnits,
} from './state'
import { Badge, Empty, ErrorNotice, Forbidden, Loading, PageTitle, Pagination } from './ui'
import type { Balance, Ledger, Product, Store, Transfer } from './types'
export function Overview() {
  const { user } = useSession()
  const lookups = useLookups()
  const can = (p: string) => user.permissions.includes(p)
  const products = useList<Product>('products', 'is_active=1&per_page=1', can('products.view'))
  const stores = useList<Store>('stores', 'per_page=1', can('stores.view'))
  const pending = useList<Transfer>(
    'transfers',
    'status=submitted&per_page=1',
    can('transfers.view'),
  )
  const balances = useList<Balance>('inventory/balances', 'per_page=1', can('inventory.view'))
  const recent = useList<Ledger>('inventory/ledger', 'per_page=5', can('inventory.view'))
  const cards = [
    {
      label: 'Active products',
      query: products,
      icon: Package,
      path: '/products',
      allowed: can('products.view'),
      note: 'Across your catalog',
    },
    {
      label: 'Accessible stores',
      query: stores,
      icon: StoreIcon,
      path: '/stores',
      allowed: can('stores.view'),
      note: 'Within your access',
    },
    {
      label: 'Pending transfers',
      query: pending,
      icon: ArrowLeftRight,
      path: '/transfers?status=submitted',
      allowed: can('transfers.view'),
      note: 'Awaiting a decision',
    },
    {
      label: 'Stock positions',
      query: balances,
      icon: Layers3,
      path: '/balances',
      allowed: can('inventory.view'),
      note: 'Recorded store / product pairs',
    },
  ]
  return (
    <>
      <PageTitle
        title="Inventory overview"
        subtitle={
          'Welcome back, ' +
          user.name.split(' ')[0] +
          '. Here’s what’s happening across your stores.'
        }
        action={
          can('transfers.create') && (
            <Link className="button" to="/transfers">
              <PlusLabel />
              Create a transfer
            </Link>
          )
        }
      />
      <ReportSummary />
      <div className="overview-banner">
        <div>
          <span className="eyebrow">YOUR OPERATIONS, CONNECTED</span>
          <h2>
            Right stock. Right place.
            <br />
            Every movement accounted for.
          </h2>
          <p>Manage incoming stock, review transfers, and follow every change.</p>
        </div>
        <div className="warehouse-art" aria-hidden="true">
          <div className="warehouse-roof" />
          <div className="warehouse-body">
            <i />
            <i />
            <i />
            <b />
          </div>
          <div className="parcel p1" />
          <div className="parcel p2" />
          <div className="orbit">
            <ArrowLeftRight size={27} />
          </div>
        </div>
      </div>
      <div className="metrics">
        {cards
          .filter((c) => c.allowed)
          .map((c) => (
            <Link key={c.label} to={c.path} className="metric panel">
              <div className="metric-top">
                <span>{c.label}</span>
                <c.icon size={19} />
              </div>
              <strong>
                {c.query.isLoading ? '…' : c.query.error ? '—' : (c.query.data?.meta.total ?? '—')}
              </strong>
              <small>{c.note}</small>
            </Link>
          ))}
      </div>
      <ErrorNotice error={products.error ?? stores.error ?? pending.error ?? balances.error} />
      <div className="overview-columns">
        <section className="panel">
          <div className="panel-heading">
            <h2>Recent movements</h2>
            {can('inventory.view') && (
              <Link className="text-link" to="/ledger">
                View ledger <ArrowRight size={14} />
              </Link>
            )}
          </div>
          <ErrorNotice error={recent.error} retry={() => void recent.refetch()} />
          {!can('inventory.view') ? (
            <Empty title="Inventory access is restricted" />
          ) : recent.isLoading ? (
            <Loading />
          ) : recent.data?.data.length ? (
            <div className="movement-list">
              {recent.data.data.map((row) => (
                <div key={row.id} className="movement">
                  <span
                    className={
                      'movement-icon ' + (row.quantity_delta.startsWith('-') ? 'outgoing' : '')
                    }
                  >
                    {row.quantity_delta.startsWith('-') ? (
                      <ArrowUpRight size={18} />
                    ) : (
                      <ArrowDownLeft size={18} />
                    )}
                  </span>
                  <div>
                    <strong>
                      {lookups.products.find((p) => p.id === row.product_id)?.name ??
                        'Product #' + row.product_id}
                    </strong>
                    <small>
                      {lookups.stores.find((s) => s.id === row.store_id)?.name ??
                        'Store #' + row.store_id}{' '}
                      · {row.movement_type.replaceAll('_', ' ')}
                    </small>
                  </div>
                  <div className="movement-number">
                    <strong>
                      {!row.quantity_delta.startsWith('-') && '+'}
                      {displayQuantity(row.quantity_delta)}
                    </strong>
                    <small>{displayDate(row.posted_at)}</small>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <Empty
              title="Your ledger starts here"
              text="Posted receipts, issues and transfers will appear here."
            />
          )}
        </section>
        <aside className="panel quick-actions">
          <h2>Keep things moving</h2>
          <p className="muted">Choose your next task.</p>
          {(
            [
              [
                'inventory.receive',
                '/receipts',
                'Receive stock',
                'Record an incoming delivery',
                ArrowDownLeft,
              ],
              [
                'inventory.issue',
                '/issues',
                'Issue stock',
                'Record stock leaving a store',
                ArrowUpRight,
              ],
              [
                'transfers.approve',
                '/approvals',
                'Review approvals',
                'Approve eligible store transfers',
                ArrowLeftRight,
              ],
            ] as const
          ).map(
            ([permission, path, title, subtitle, Icon]) =>
              can(permission as string) && (
                <Link key={path as string} to={path as string}>
                  <span className="action-icon">
                    <Icon size={20} />
                  </span>
                  <span>
                    <strong>{title as string}</strong>
                    <small>{subtitle as string}</small>
                  </span>
                  <ArrowRight size={16} />
                </Link>
              ),
          )}
          <div className="quiet-note">
            Counts reflect your current access. Open Reports for low-stock positions and exact
            movement totals.
          </div>
        </aside>
      </div>
    </>
  )
}
function PlusLabel() {
  return <span aria-hidden="true">+</span>
}
export function Inventory({ ledger = false }: { ledger?: boolean }) {
  const { user } = useSession()
  const lookups = useLookups()
  const filters = useFilters()
  const allowed = user.permissions.includes('inventory.view')
  const query = useList<Ledger | Balance>(
    ledger ? 'inventory/ledger' : 'inventory/balances',
    filters.params.toString(),
    allowed,
  )
  if (!allowed) return <Forbidden />
  return (
    <>
      <PageTitle
        title={ledger ? 'Stock ledger' : 'Inventory balances'}
        subtitle={
          ledger
            ? 'A permanent record of every posted stock movement.'
            : 'Available quantities by store and product, in their base units.'
        }
      />
      <section className="panel">
        <div className="toolbar">
          <select
            aria-label="Store filter"
            value={filters.params.get('store_id') ?? ''}
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
            value={filters.params.get('product_id') ?? ''}
            onChange={(e) => filters.update('product_id', e.target.value)}
          >
            <option value="">All products</option>
            {lookups.products.map((p) => (
              <option key={p.id} value={p.id}>
                {p.sku} · {p.name}
              </option>
            ))}
          </select>
          {ledger && (
            <>
              <label className="date-filter">
                From
                <input
                  type="date"
                  aria-label="From date"
                  value={filters.params.get('date_from') ?? ''}
                  onChange={(e) => filters.update('date_from', e.target.value)}
                />
              </label>
              <label className="date-filter">
                To
                <input
                  type="date"
                  aria-label="To date"
                  value={filters.params.get('date_to') ?? ''}
                  onChange={(e) => filters.update('date_to', e.target.value)}
                />
              </label>
            </>
          )}
        </div>
        <ErrorNotice error={query.error ?? lookups.error} retry={() => void query.refetch()} />
        {query.isLoading ? (
          <Loading />
        ) : query.data?.data.length ? (
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Store</th>
                  {ledger ? (
                    <>
                      <th>Movement</th>
                      <th>Source</th>
                      <th className="numeric">Change</th>
                      <th className="numeric">Balance after</th>
                      <th>Posted</th>
                    </>
                  ) : (
                    <>
                      <th>Unit</th>
                      <th className="numeric">Available</th>
                      <th>Stock level</th>
                    </>
                  )}
                </tr>
              </thead>
              <tbody>
                {query.data.data.map((row) => {
                  const product = lookups.products.find((p) => p.id === row.product_id)
                  return (
                    <tr key={row.id}>
                      <td data-label="Product">
                        <strong>{product?.name ?? 'Product #' + row.product_id}</strong>
                        <small>{product?.sku}</small>
                      </td>
                      <td data-label="Store">
                        {lookups.stores.find((s) => s.id === row.store_id)?.name ??
                          'Store #' + row.store_id}
                      </td>
                      {'movement_type' in row ? (
                        <>
                          <td data-label="Movement">
                            <Badge value={row.movement_type} />
                          </td>
                          <td data-label="Source">
                            <Link
                              className="text-link"
                              to={
                                '/' +
                                (row.source_type === 'transfer'
                                  ? 'transfers'
                                  : row.movement_type === 'receipt'
                                    ? 'receipts'
                                    : 'issues') +
                                '/' +
                                row.source_id
                              }
                            >
                              {row.source_type} #{row.source_id}
                            </Link>
                          </td>
                          <td className="numeric" data-label="Change">
                            {displayQuantity(row.quantity_delta)}
                          </td>
                          <td className="numeric" data-label="Balance after">
                            {displayQuantity(row.balance_after)}
                          </td>
                          <td data-label="Posted">{displayDate(row.posted_at)}</td>
                        </>
                      ) : (
                        <>
                          <td data-label="Unit">{product?.base_uom ?? '—'}</td>
                          <td className="numeric" data-label="Available">
                            {displayQuantity(row.quantity)}
                          </td>
                          <td data-label="Stock level">
                            <Badge
                              value={
                                product &&
                                quantityUnits(row.quantity) < quantityUnits(product.reorder_level)
                                  ? 'low_stock'
                                  : 'in_stock'
                              }
                            />
                          </td>
                        </>
                      )}
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        ) : (
          !query.error && (
            <Empty
              title="No matching stock records"
              text="Try another filter. Records appear after stock is posted."
            />
          )
        )}
        {query.data && (
          <Pagination meta={query.data.meta} onPage={(p) => filters.update('page', p)} />
        )}
        {!ledger && (
          <div className="panel-footnote">
            Only recorded balances appear here. Products with no balance record have zero stock;
            open Reports for complete zero-stock reporting.
          </div>
        )}
      </section>
    </>
  )
}
