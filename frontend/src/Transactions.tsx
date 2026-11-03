import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowLeft, ArrowRight, Plus, Trash2, CheckCircle2 } from 'lucide-react'
import { api } from './api'
import {
  useFilters,
  useList,
  useLookups,
  useSession,
  displayDate,
  displayQuantity,
  postingKey,
} from './state'
import {
  Badge,
  Empty,
  ErrorNotice,
  Field,
  Forbidden,
  Loading,
  Modal,
  PageTitle,
  Pagination,
} from './ui'
import { documentSchema, transferSchema } from './schemas'
import type { DocumentFormValues } from './schemas'
import type { StockDocument, Transfer } from './types'
export type TransactionKind = 'receipts' | 'issues' | 'transfers'
type Record = StockDocument | Transfer
const endpoints = {
  receipts: 'inventory/receipts',
  issues: 'inventory/issues',
  transfers: 'transfers',
}
const labels = { receipts: 'Receipt', issues: 'Issue', transfers: 'Transfer' }
const permission = (kind: TransactionKind) =>
  kind === 'transfers'
    ? 'transfers.create'
    : kind === 'receipts'
      ? 'inventory.receive'
      : 'inventory.issue'
export function Transactions({ kind, inbox = false }: { kind: TransactionKind; inbox?: boolean }) {
  const { user } = useSession()
  const lookups = useLookups()
  const filters = useFilters()
  const navigate = useNavigate()
  const [create, setCreate] = useState(false)
  const canView = user.permissions.includes(
    inbox ? 'transfers.approve' : kind === 'transfers' ? 'transfers.view' : 'inventory.view',
  )
  const params = new URLSearchParams(filters.params)
  if (inbox) params.set('approval_inbox', '1')
  const query = useList<Record>(endpoints[kind], params.toString(), canView)
  if (!canView) return <Forbidden />
  const store = (id: number) => lookups.stores.find((s) => s.id === id)?.name ?? 'Store #' + id
  return (
    <>
      <PageTitle
        title={inbox ? 'Approval inbox' : labels[kind] + 's'}
        subtitle={
          inbox
            ? 'Review submitted transfers that you are eligible to approve.'
            : kind === 'transfers'
              ? 'Move stock between stores with a clear approval trail.'
              : kind === 'receipts'
                ? 'Record incoming inventory, then review and post.'
                : 'Record outgoing inventory with stock availability checks.'
        }
        action={
          !inbox &&
          user.permissions.includes(permission(kind)) && (
            <button onClick={() => setCreate(true)}>
              <Plus size={17} />
              New {labels[kind].toLowerCase()}
            </button>
          )
        }
      />
      {inbox && (
        <div className="info-strip">
          <CheckCircle2 size={18} />
          Only transfers you did not create or submit appear here.
        </div>
      )}
      <section className="panel">
        <div className="toolbar">
          {!inbox && (
            <select
              aria-label="Status filter"
              value={filters.params.get('status') ?? ''}
              onChange={(e) => filters.update('status', e.target.value)}
            >
              <option value="">All statuses</option>
              {(kind === 'transfers'
                ? ['draft', 'submitted', 'approved_posted', 'rejected', 'cancelled']
                : ['draft', 'posted', 'cancelled']
              ).map((s) => (
                <option key={s} value={s}>
                  {s.replaceAll('_', ' ')}
                </option>
              ))}
            </select>
          )}
          <select
            aria-label="Store filter"
            value={filters.params.get(kind === 'transfers' ? 'from_store_id' : 'store_id') ?? ''}
            onChange={(e) =>
              filters.update(kind === 'transfers' ? 'from_store_id' : 'store_id', e.target.value)
            }
          >
            <option value="">All {kind === 'transfers' ? 'source ' : ''}stores</option>
            {lookups.stores.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
          <span className="toolbar-note">{query.data?.meta.total ?? '—'} records</span>
        </div>
        <ErrorNotice error={query.error ?? lookups.error} retry={() => void query.refetch()} />
        {query.isLoading ? (
          <Loading />
        ) : query.data?.data.length ? (
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Reference</th>
                  <th>{kind === 'transfers' ? 'Route' : 'Store'}</th>
                  <th>Lines</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {query.data.data.map((row) => (
                  <tr key={row.id}>
                    <td data-label="Reference">
                      <Link className="text-link" to={'/' + kind + '/' + row.id}>
                        {row.number}
                      </Link>
                      <small>{'reference' in row ? row.reference : 'Inter-store transfer'}</small>
                    </td>
                    <td data-label="Route / store">
                      {'from_store_id' in row ? (
                        <span className="route-label">
                          {store(row.from_store_id)}
                          <ArrowRight size={13} />
                          {store(row.to_store_id)}
                        </span>
                      ) : (
                        store(row.store_id)
                      )}
                    </td>
                    <td data-label="Lines">{row.lines.length}</td>
                    <td data-label="Status">
                      <Badge value={row.status} />
                    </td>
                    <td data-label="Created">{displayDate(row.created_at)}</td>
                    <td data-label="Actions">
                      <Link
                        className="icon-button"
                        aria-label={'Open ' + row.number}
                        to={'/' + kind + '/' + row.id}
                      >
                        <ArrowRight size={16} />
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          !query.error && (
            <Empty
              title={inbox ? 'You’re all caught up' : 'No ' + labels[kind].toLowerCase() + 's yet'}
              text={
                inbox
                  ? 'New eligible submissions will appear here.'
                  : 'Create a draft to get started, or adjust your filters.'
              }
            />
          )
        )}
        {query.data && (
          <Pagination meta={query.data.meta} onPage={(p) => filters.update('page', p)} />
        )}
      </section>
      {create && (
        <DraftEditor
          kind={kind}
          close={() => setCreate(false)}
          saved={(id) => navigate('/' + kind + '/' + id)}
        />
      )}
    </>
  )
}
export function TransactionDetail({ kind }: { kind: TransactionKind }) {
  const { id } = useParams()
  const { user } = useSession()
  const lookups = useLookups()
  const client = useQueryClient()
  const [edit, setEdit] = useState(false)
  const [action, setAction] = useState('')
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const canView = user.permissions.includes(
    kind === 'transfers' ? 'transfers.view' : 'inventory.view',
  )
  const query = useQuery({
    queryKey: ['detail', kind, id],
    queryFn: ({ signal }) => api.get<Record>(endpoints[kind] + '/' + id, signal),
    enabled: canView,
  })
  if (!canView) return <Forbidden />
  if (query.isLoading) return <Loading />
  if (!query.data)
    return (
      <>
        <Link to={'/' + kind} className="back-link">
          <ArrowLeft size={15} />
          Back
        </Link>
        <ErrorNotice error={query.error} retry={() => void query.refetch()} />
      </>
    )
  const row = query.data.data
  const transfer = 'from_store_id' in row ? row : null
  const canEdit =
    row.status === 'draft' &&
    user.permissions.includes(kind === 'transfers' ? 'transfers.update' : permission(kind))
  const eligible =
    transfer &&
    user.permissions.includes('transfers.approve') &&
    transfer.created_by !== user.id &&
    transfer.submitted_by !== user.id
  const store = (storeId: number) =>
    lookups.stores.find((s) => s.id === storeId)?.name ?? 'Store #' + storeId
  async function perform() {
    if (busy || !action) return
    setBusy(true)
    setError(null)
    const path = endpoints[kind] + '/' + id + '/' + action
    const key = ['post', 'approve'].includes(action) ? postingKey(user.id, path) : null
    try {
      await api.mutate(
        path,
        'POST',
        action === 'reject' ? { rejection_reason: reason.trim() } : {},
        key?.key,
      )
      key?.clear()
      setAction('')
      setReason('')
      await client.invalidateQueries()
      window.dispatchEvent(
        new CustomEvent('stockflow:toast', {
          detail:
            action === 'approve' || action === 'post'
              ? 'Stock posted successfully.'
              : 'Transfer updated successfully.',
        }),
      )
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }
  return (
    <>
      <Link className="back-link" to={'/' + kind}>
        <ArrowLeft size={15} />
        Back to {labels[kind].toLowerCase()}s
      </Link>
      <PageTitle
        title={row.number}
        subtitle={labels[kind] + ' details · Created ' + displayDate(row.created_at)}
        action={<Badge value={row.status} />}
      />
      <ErrorNotice error={query.error ?? lookups.error} />
      <div className="detail-grid">
        <section className="panel detail-summary">
          <h2>{transfer ? 'Transfer route' : 'Document overview'}</h2>
          {transfer ? (
            <div className="transfer-route">
              <div>
                <small>FROM</small>
                <strong>{store(transfer.from_store_id)}</strong>
              </div>
              <ArrowRight size={24} />
              <div>
                <small>TO</small>
                <strong>{store(transfer.to_store_id)}</strong>
              </div>
            </div>
          ) : (
            <dl>
              <dt>Store</dt>
              <dd>{store((row as StockDocument).store_id)}</dd>
              <dt>Reference</dt>
              <dd>{(row as StockDocument).reference || '—'}</dd>
              <dt>Remarks</dt>
              <dd>{(row as StockDocument).remarks || '—'}</dd>
            </dl>
          )}
          <div className="detail-actions">
            {canEdit && (
              <button className="secondary" onClick={() => setEdit(true)}>
                Edit draft
              </button>
            )}
            {row.status === 'draft' &&
              user.permissions.includes(
                kind === 'transfers' ? 'transfers.submit' : permission(kind),
              ) && (
                <button
                  onClick={() => {
                    setError(null)
                    setAction(kind === 'transfers' ? 'submit' : 'post')
                  }}
                >
                  {kind === 'transfers'
                    ? 'Submit for approval'
                    : 'Post ' + labels[kind].toLowerCase()}
                </button>
              )}
            {row.status === 'draft' &&
              user.permissions.includes(
                kind === 'transfers' ? 'transfers.cancel' : permission(kind),
              ) && (
                <button
                  className="quiet danger-text"
                  onClick={() => {
                    setError(null)
                    setAction('cancel')
                  }}
                >
                  Cancel draft
                </button>
              )}
            {row.status === 'submitted' && eligible && (
              <button
                onClick={() => {
                  setError(null)
                  setAction('approve')
                }}
              >
                Approve & post
              </button>
            )}
            {row.status === 'submitted' && user.permissions.includes('transfers.reject') && (
              <button
                className="secondary"
                onClick={() => {
                  setError(null)
                  setAction('reject')
                }}
              >
                Reject transfer
              </button>
            )}
          </div>
          {transfer && row.status === 'submitted' && !eligible && (
            <p className="muted">
              {[transfer.created_by, transfer.submitted_by].includes(user.id)
                ? 'A different authorized person must approve this transfer.'
                : 'Your role cannot approve this transfer.'}
            </p>
          )}
        </section>
        <aside className="panel timeline">
          <h2>Activity</h2>
          <div>
            <span className="timeline-dot" />
            <strong>Draft created</strong>
            <small>
              {displayDate(row.created_at)} · User #{row.created_by}
            </small>
          </div>
          {transfer?.submitted_at && (
            <div>
              <span className="timeline-dot" />
              <strong>Submitted for approval</strong>
              <small>
                {displayDate(transfer.submitted_at)} · User #{transfer.submitted_by}
              </small>
            </div>
          )}
          {row.posted_at && (
            <div>
              <span className="timeline-dot" />
              <strong>Stock posted</strong>
              <small>
                {displayDate(row.posted_at)} · User #
                {transfer?.approved_by ?? (row as StockDocument).posted_by}
              </small>
            </div>
          )}
          {transfer?.rejection_reason && (
            <div>
              <strong>Rejected</strong>
              <p>{transfer.rejection_reason}</p>
            </div>
          )}
        </aside>
      </div>
      <section className="panel">
        <div className="panel-heading">
          <h2>Line items</h2>
          <span className="muted">{row.lines.length} products</span>
        </div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Unit</th>
                <th className="numeric">Quantity</th>
              </tr>
            </thead>
            <tbody>
              {row.lines.map((line) => {
                const product = lookups.products.find((p) => p.id === line.product_id)
                return (
                  <tr key={line.id ?? line.product_id}>
                    <td data-label="Product">
                      <strong>{product?.name ?? 'Product #' + line.product_id}</strong>
                    </td>
                    <td data-label="SKU">{product?.sku ?? '—'}</td>
                    <td data-label="Unit">{product?.base_uom ?? '—'}</td>
                    <td className="numeric" data-label="Quantity">
                      {displayQuantity(line.quantity)}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
        <div className="panel-footnote">
          {row.status === 'draft' || row.status === 'submitted'
            ? 'Stock changes only after successful posting. Availability is checked at that time.'
            : 'This record is final and cannot be edited.'}
        </div>
      </section>
      {edit && (
        <DraftEditor
          kind={kind}
          row={row}
          close={() => setEdit(false)}
          saved={() => setEdit(false)}
        />
      )}
      {action && (
        <Modal
          title={
            action === 'approve'
              ? 'Approve and post transfer?'
              : action === 'post'
                ? 'Post this document?'
                : action === 'submit'
                  ? 'Submit for approval?'
                  : action === 'reject'
                    ? 'Reject this transfer?'
                    : 'Cancel this draft?'
          }
          close={() => {
            setAction('')
            setError(null)
          }}
          busy={busy}
        >
          <p>
            {['approve', 'post'].includes(action)
              ? 'This will update stock balances and create permanent ledger entries. Review the lines before confirming.'
              : action === 'submit'
                ? 'The draft becomes read-only. An eligible approver will review it before stock moves.'
                : 'This action is final for this record. No stock will move.'}
          </p>
          <ErrorNotice error={error} />
          {action === 'reject' && (
            <Field label="Rejection reason">
              <textarea
                value={reason}
                maxLength={1000}
                onChange={(e) => setReason(e.target.value)}
              />
            </Field>
          )}
          <div className="dialog-actions">
            <button className="secondary" disabled={busy} onClick={() => setAction('')}>
              Go back
            </button>
            <button
              disabled={busy || (action === 'reject' && !reason.trim())}
              onClick={() => void perform()}
            >
              {busy ? 'Processing…' : error ? 'Retry with same request' : 'Confirm ' + action}
            </button>
          </div>
        </Modal>
      )}
    </>
  )
}
function DraftEditor({
  kind,
  row,
  close,
  saved,
}: {
  kind: TransactionKind
  row?: Record
  close: () => void
  saved: (id: number) => void
}) {
  const lookups = useLookups()
  const client = useQueryClient()
  const [error, setError] = useState<unknown>(null)
  const transfer = row && 'from_store_id' in row ? row : null
  const form = useForm<DocumentFormValues>({
    resolver: zodResolver(kind === 'transfers' ? transferSchema : documentSchema),
    defaultValues: {
      store_id: row && 'store_id' in row ? String(row.store_id) : '',
      from_store_id: transfer ? String(transfer.from_store_id) : '',
      to_store_id: transfer ? String(transfer.to_store_id) : '',
      reference: row && 'reference' in row ? (row.reference ?? '') : '',
      remarks: row && 'remarks' in row ? (row.remarks ?? '') : '',
      lines: row?.lines.map((l) => ({
        product_id: String(l.product_id),
        quantity: l.quantity,
      })) ?? [{ product_id: '', quantity: '1.0000' }],
    },
  })
  const lines = useFieldArray({ control: form.control, name: 'lines' })
  const busy = form.formState.isSubmitting
  async function submit(v: DocumentFormValues) {
    setError(null)
    const data = {
      ...(kind === 'transfers'
        ? { from_store_id: Number(v.from_store_id), to_store_id: Number(v.to_store_id) }
        : {
            store_id: Number(v.store_id),
            reference: v.reference || null,
            remarks: v.remarks || null,
          }),
      lines: v.lines.map((l) => ({ product_id: Number(l.product_id), quantity: l.quantity })),
    }
    try {
      const response = await api.mutate<Record>(
        endpoints[kind] + (row ? '/' + row.id : ''),
        row ? 'PATCH' : 'POST',
        data,
      )
      await client.invalidateQueries()
      saved(response.data.id)
      window.dispatchEvent(
        new CustomEvent('stockflow:toast', { detail: 'Draft saved. Review it before submitting.' }),
      )
    } catch (e) {
      setError(e)
    }
  }
  const storeField = (name: 'store_id' | 'from_store_id' | 'to_store_id', label: string) => (
    <Field label={label} error={form.formState.errors[name]?.message}>
      <select {...form.register(name)}>
        <option value="">Select store</option>
        {lookups.stores
          .filter((s) => s.is_active)
          .map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
      </select>
    </Field>
  )
  return (
    <Modal title={(row ? 'Edit ' : 'New ') + labels[kind].toLowerCase()} close={close} busy={busy}>
      <form onSubmit={form.handleSubmit(submit)}>
        <ErrorNotice error={error ?? lookups.error} />
        <div className="form-grid">
          {kind === 'transfers' ? (
            <>
              {storeField('from_store_id', 'Source store')}
              {storeField('to_store_id', 'Destination store')}
            </>
          ) : (
            <>
              {storeField('store_id', 'Store')}
              <Field label="Reference">
                <input maxLength={255} {...form.register('reference')} />
              </Field>
            </>
          )}
        </div>
        <div className="line-heading">
          <h3>Products</h3>
          <span className="muted">Quantities stay in each product’s base unit.</span>
        </div>
        {lines.fields.map((line, i) => (
          <div className="line-editor" key={line.id}>
            <Field
              label={'Product ' + (i + 1)}
              error={form.formState.errors.lines?.[i]?.product_id?.message}
            >
              <select {...form.register(`lines.${i}.product_id`)}>
                <option value="">Select product</option>
                {lookups.products
                  .filter((p) => p.is_active)
                  .map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.sku} · {p.name} ({p.base_uom})
                    </option>
                  ))}
              </select>
            </Field>
            <Field
              label={'Quantity ' + (i + 1)}
              error={form.formState.errors.lines?.[i]?.quantity?.message}
            >
              <input inputMode="decimal" {...form.register(`lines.${i}.quantity`)} />
            </Field>
            <button
              type="button"
              className="icon-button danger-text"
              aria-label={'Remove line ' + (i + 1)}
              disabled={lines.fields.length <= 1 || busy}
              onClick={() => lines.remove(i)}
            >
              <Trash2 size={17} />
            </button>
          </div>
        ))}
        {(form.formState.errors.lines?.root?.message || form.formState.errors.lines?.message) && (
          <p role="alert" className="field-error">
            {form.formState.errors.lines.root?.message ?? form.formState.errors.lines.message}
          </p>
        )}
        <button
          type="button"
          className="quiet"
          disabled={lines.fields.length >= 100 || busy}
          onClick={() => lines.append({ product_id: '', quantity: '1.0000' })}
        >
          <Plus size={16} />
          Add another product
        </button>
        {kind !== 'transfers' && (
          <Field label="Remarks">
            <textarea maxLength={2000} {...form.register('remarks')} />
          </Field>
        )}
        <div className="dialog-actions">
          <button type="button" className="secondary" disabled={busy} onClick={close}>
            Cancel
          </button>
          <button disabled={busy || lookups.loading}>{busy ? 'Saving…' : 'Save draft'}</button>
        </div>
      </form>
    </Modal>
  )
}
