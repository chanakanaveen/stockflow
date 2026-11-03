import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Pencil } from 'lucide-react'
import { api } from './api'
import { useDebounced, useFilters, useList, useLookups, useSession } from './state'
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
import { decimal } from './schemas'
type Kind = 'products' | 'categories' | 'stores' | 'users'
interface Row {
  id: number
  name: string
  is_active: boolean
  sku?: string
  code?: string
  email?: string
  category_id?: number
  base_uom?: string
  reorder_level?: string
  location_label?: string | null
  roles?: string[]
  assigned_store_ids?: number[]
}
const labels = {
  products: 'Products',
  categories: 'Categories',
  stores: 'Stores',
  users: 'Team & access',
}
const singular = { products: 'product', categories: 'category', stores: 'store', users: 'user' }
export function Masters({ kind }: { kind: Kind }) {
  const { user } = useSession()
  const filters = useFilters()
  const [editing, setEditing] = useState<Row | 'new' | null>(null)
  const search = filters.params.get('search') ?? ''
  const debounced = useDebounced(search)
  const params = new URLSearchParams(filters.params)
  if (kind !== 'stores') {
    if (debounced) params.set('search', debounced)
    else params.delete('search')
  }
  const canView = user.permissions.includes(kind === 'users' ? 'users.manage' : kind + '.view')
  const canWrite = user.permissions.includes(kind === 'users' ? 'users.manage' : kind + '.create')
  const query = useList<Row>(kind, params.toString(), canView)
  const lookups = useLookups()
  if (!canView) return <Forbidden />
  return (
    <>
      <PageTitle
        title={labels[kind]}
        subtitle={
          kind === 'users'
            ? 'Give each person the right role and store access.'
            : 'Keep your inventory records organized and up to date.'
        }
        action={
          canWrite && (
            <button onClick={() => setEditing('new')}>
              <Plus size={17} />
              Add {singular[kind]}
            </button>
          )
        }
      />
      <section className="panel">
        <div className="toolbar">
          {kind !== 'stores' && (
            <div className="search-field">
              <Search size={17} />
              <input
                aria-label={'Search ' + kind}
                placeholder={'Search ' + kind + '…'}
                value={search}
                onChange={(e) => filters.update('search', e.target.value)}
              />
            </div>
          )}
          {['products', 'categories'].includes(kind) && (
            <select
              aria-label="Activity status"
              value={filters.params.get('is_active') ?? ''}
              onChange={(e) => filters.update('is_active', e.target.value)}
            >
              <option value="">All statuses</option>
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          )}
          <span className="toolbar-note">{query.data?.meta.total ?? '—'} records</span>
        </div>
        <ErrorNotice error={query.error} retry={() => void query.refetch()} />
        {query.isLoading ? (
          <Loading />
        ) : query.data?.data.length ? (
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>{kind === 'users' ? 'Person' : 'Name'}</th>
                  {kind === 'products' && (
                    <>
                      <th>Category</th>
                      <th>Unit</th>
                      <th>Reorder level</th>
                    </>
                  )}
                  {kind === 'stores' && <th>Location</th>}
                  {kind === 'users' && (
                    <>
                      <th>Role</th>
                      <th>Assigned stores</th>
                    </>
                  )}
                  <th>Status</th>
                  {canWrite && (
                    <th>
                      <span className="sr-only">Actions</span>
                    </th>
                  )}
                </tr>
              </thead>
              <tbody>
                {query.data.data.map((row) => (
                  <tr key={row.id}>
                    <td data-label="Name">
                      <strong>{row.name}</strong>
                      <small>{row.sku ?? row.code ?? row.email}</small>
                    </td>
                    {kind === 'products' && (
                      <>
                        <td data-label="Category">
                          {lookups.categories.find((c) => c.id === row.category_id)?.name ??
                            'Category #' + row.category_id}
                        </td>
                        <td data-label="Unit">{row.base_uom}</td>
                        <td className="numeric" data-label="Reorder level">
                          {row.reorder_level}
                        </td>
                      </>
                    )}
                    {kind === 'stores' && (
                      <td data-label="Location">{row.location_label || '—'}</td>
                    )}
                    {kind === 'users' && (
                      <>
                        <td data-label="Role">{row.roles?.join(', ')}</td>
                        <td data-label="Assigned stores">
                          {row.roles?.includes('System Admin')
                            ? 'All stores'
                            : (row.assigned_store_ids?.length ?? 0)}
                        </td>
                      </>
                    )}
                    <td data-label="Status">
                      <Badge value={row.is_active} />
                    </td>
                    {canWrite && (
                      <td data-label="Actions">
                        <button
                          className="icon-button"
                          aria-label={'Edit ' + row.name}
                          onClick={() => setEditing(row)}
                        >
                          <Pencil size={16} />
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          !query.error && (
            <Empty
              title={'No ' + kind + ' found'}
              text="Try a different search or add your first record."
            />
          )
        )}
        {query.data && (
          <Pagination meta={query.data.meta} onPage={(page) => filters.update('page', page)} />
        )}
      </section>
      {editing && (
        <MasterEditor
          kind={kind}
          row={editing === 'new' ? null : editing}
          close={() => setEditing(null)}
        />
      )}
    </>
  )
}
function MasterEditor({ kind, row, close }: { kind: Kind; row: Row | null; close: () => void }) {
  const client = useQueryClient()
  const lookups = useLookups()
  const [error, setError] = useState<unknown>(null)
  const [saved, setSaved] = useState(false)
  const roleQuery = useList<string>('roles', '', kind === 'users')
  const schema = z
    .object({
      name: z.string().trim().min(1, 'Enter a name.').max(255),
      code: z.string(),
      sku: z.string(),
      category_id: z.string(),
      base_uom: z.string(),
      reorder_level: z.string(),
      location_label: z.string().max(255),
      email: z.string(),
      password: z.string(),
      role: z.string(),
      store_ids: z.array(z.string()),
      is_active: z.boolean(),
    })
    .superRefine((v, ctx) => {
      const issue = (path: string, message: string) =>
        ctx.addIssue({ code: 'custom', path: [path], message })
      if (kind === 'products') {
        if (!/^[a-z0-9][a-z0-9._-]{0,63}$/i.test(v.sku))
          issue('sku', 'Use letters, numbers, dots, underscores or dashes.')
        if (!v.category_id) issue('category_id', 'Choose a category.')
        if (!v.base_uom.trim() || v.base_uom.length > 32)
          issue('base_uom', 'Enter a unit up to 32 characters.')
        if (!decimal.safeParse(v.reorder_level).success)
          issue('reorder_level', 'Enter a nonnegative decimal with up to four places.')
      }
      if (kind === 'stores' && !/^[a-z0-9][a-z0-9._-]{0,31}$/i.test(v.code))
        issue('code', 'Enter a code up to 32 characters.')
      if (kind === 'users') {
        if (!z.email().safeParse(v.email).success) issue('email', 'Enter a valid email.')
        if ((!row || v.password) && (v.password.length < 12 || v.password.length > 128))
          issue('password', 'Use 12–128 characters.')
        if (!v.role) issue('role', 'Choose a role.')
      }
    })
  type Values = z.infer<typeof schema>
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: row?.name ?? '',
      sku: row?.sku ?? '',
      code: row?.code ?? '',
      category_id: String(row?.category_id ?? ''),
      base_uom: row?.base_uom ?? '',
      reorder_level: row?.reorder_level ?? '0.0000',
      location_label: row?.location_label ?? '',
      email: row?.email ?? '',
      password: '',
      role: row?.roles?.[0] ?? 'Store Keeper',
      store_ids: row?.assigned_store_ids?.map(String) ?? [],
      is_active: row?.is_active ?? true,
    },
  })
  const busy = form.formState.isSubmitting
  useEffect(() => {
    if (saved) close()
  }, [saved, close])
  async function submit(v: Values) {
    setError(null)
    const data =
      kind === 'products'
        ? {
            name: v.name,
            sku: v.sku,
            category_id: Number(v.category_id),
            base_uom: v.base_uom,
            reorder_level: v.reorder_level,
            is_active: v.is_active,
          }
        : kind === 'stores'
          ? {
              name: v.name,
              code: v.code,
              location_label: v.location_label || null,
              is_active: v.is_active,
            }
          : kind === 'categories'
            ? { name: v.name, is_active: v.is_active }
            : {
                name: v.name,
                email: v.email,
                role: v.role,
                store_ids: v.store_ids.map(Number),
                is_active: v.is_active,
                ...(v.password ? { password: v.password } : {}),
              }
    try {
      await api.mutate(kind + (row ? '/' + row.id : ''), row ? 'PATCH' : 'POST', data)
      await client.invalidateQueries()
      window.dispatchEvent(new CustomEvent('stockflow:toast', { detail: 'Saved successfully.' }))
      if (kind === 'users') window.dispatchEvent(new Event('stockflow:refresh-session'))
      setSaved(true)
    } catch (e) {
      setError(e)
    }
  }
  const input = (name: keyof Values, label: string, type = 'text') => (
    <Field key={name} label={label} error={form.formState.errors[name]?.message}>
      <input
        type={type}
        {...form.register(name)}
        autoComplete={name === 'password' ? 'new-password' : undefined}
      />
    </Field>
  )
  return (
    <Modal title={(row ? 'Edit ' : 'Add ') + singular[kind]} close={close} busy={busy}>
      <form onSubmit={form.handleSubmit(submit)}>
        <ErrorNotice error={error ?? lookups.error ?? roleQuery.error} />
        <div className="form-grid">
          {input('name', 'Name')}
          {kind === 'products' && (
            <>
              {input('sku', 'SKU')}
              <Field label="Category" error={form.formState.errors.category_id?.message}>
                <select {...form.register('category_id')}>
                  <option value="">Select category</option>
                  {lookups.categories
                    .filter((c) => c.is_active || c.id === row?.category_id)
                    .map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                        {!c.is_active && ' (inactive)'}
                      </option>
                    ))}
                </select>
              </Field>
              {input('base_uom', 'Base unit')}
              {input('reorder_level', 'Reorder level')}
            </>
          )}
          {kind === 'stores' && (
            <>
              {input('code', 'Store code')}
              {input('location_label', 'Location')}
            </>
          )}
          {kind === 'users' && (
            <>
              {input('email', 'Email address', 'email')}
              {input(
                'password',
                row ? 'New password (leave blank to keep)' : 'Password',
                'password',
              )}
              <Field label="Role">
                <select {...form.register('role')}>
                  {(
                    roleQuery.data?.data ?? [
                      'System Admin',
                      'Inventory Manager',
                      'Store Keeper',
                      'Auditor',
                    ]
                  ).map((role) => (
                    <option key={role}>{role}</option>
                  ))}
                </select>
              </Field>
            </>
          )}
        </div>
        {kind === 'users' && (
          <fieldset className="checkbox-list">
            <legend>Assigned stores</legend>
            <p className="muted">System Admins can access all stores.</p>
            {lookups.stores.map((store) => (
              <label key={store.id}>
                <input type="checkbox" value={store.id} {...form.register('store_ids')} />
                {store.name}
              </label>
            ))}
          </fieldset>
        )}
        <label className="checkbox">
          <input type="checkbox" {...form.register('is_active')} />
          Active
        </label>
        <div className="dialog-actions">
          <button type="button" className="secondary" disabled={busy} onClick={close}>
            Cancel
          </button>
          <button disabled={busy || lookups.loading || (kind === 'users' && roleQuery.isLoading)}>
            {busy ? 'Saving…' : 'Save changes'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
