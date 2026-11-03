import { cloneElement, isValidElement, useEffect, useId, useRef } from 'react'
import type { ReactNode } from 'react'
import { AlertCircle, ArrowLeft, ArrowRight, X, PackageOpen, RefreshCw } from 'lucide-react'
import { ApiError } from './api'
import type { Envelope } from './types'
export function Badge({ value }: { value: string | boolean }) {
  const text = typeof value === 'boolean' ? (value ? 'Active' : 'Inactive') : value
  return (
    <span className={'badge badge-' + text}>
      <span />
      {text.replaceAll('_', ' ')}
    </span>
  )
}
export function PageTitle({
  title,
  subtitle,
  action,
}: {
  title: string
  subtitle: string
  action?: ReactNode
}) {
  return (
    <div className="page-title">
      <div>
        <h1>{title}</h1>
        <p>{subtitle}</p>
      </div>
      {action}
    </div>
  )
}
export function ErrorNotice({ error, retry }: { error: unknown; retry?: () => void }) {
  if (!error) return null
  return (
    <div className="error" role="alert">
      <AlertCircle size={18} />
      <div>
        <strong>
          {error instanceof Error ? error.message : 'Something went wrong. Please try again.'}
        </strong>
        {error instanceof ApiError && (
          <>
            {error.details.map((d, i) => (
              <p key={i}>
                {d.field && d.field + ': '}
                {d.message}
                {d.available && 'Available: ' + d.available + ' · Required: ' + d.required}
              </p>
            ))}
            {error.requestId && <small>Reference: {error.requestId}</small>}
          </>
        )}
      </div>
      {retry && (
        <button className="secondary" onClick={retry}>
          <RefreshCw size={14} />
          Retry
        </button>
      )}
    </div>
  )
}
export function Loading() {
  return (
    <div className="skeletons" role="status" aria-label="Loading">
      <div />
      <div />
      <div />
      <span className="sr-only">Loading…</span>
    </div>
  )
}
export function Empty({
  title = 'Nothing here yet',
  text = 'Records will appear here when your team adds them.',
  action,
}: {
  title?: string
  text?: string
  action?: ReactNode
}) {
  return (
    <div className="empty">
      <PackageOpen size={34} />
      <h3>{title}</h3>
      <p>{text}</p>
      {action}
    </div>
  )
}
export function Pagination({
  meta,
  onPage,
}: {
  meta: Envelope<unknown>['meta']
  onPage: (page: string) => void
}) {
  const current = meta.current_page ?? 1
  const last = meta.last_page ?? 1
  return (
    <div className="pagination">
      <span>
        {meta.total ?? 0} records · Page {current} of {last}
      </span>
      <div>
        <button
          className="icon-button"
          aria-label="Previous page"
          disabled={current <= 1}
          onClick={() => onPage(String(current - 1))}
        >
          <ArrowLeft size={17} />
        </button>
        <button
          className="icon-button"
          aria-label="Next page"
          disabled={current >= last}
          onClick={() => onPage(String(current + 1))}
        >
          <ArrowRight size={17} />
        </button>
      </div>
    </div>
  )
}
export function Modal({
  title,
  children,
  close,
  busy = false,
}: {
  title: string
  children: ReactNode
  close: () => void
  busy?: boolean
}) {
  const dialog = useRef<HTMLDialogElement>(null)
  useEffect(() => {
    const node = dialog.current
    node?.showModal()
    return () => node?.close()
  }, [])
  return (
    <dialog
      ref={dialog}
      onCancel={(e) => {
        e.preventDefault()
        if (!busy) close()
      }}
      aria-labelledby="dialog-title"
    >
      <div className="dialog-heading">
        <h2 id="dialog-title">{title}</h2>
        <button aria-label="Close dialog" className="icon-button" disabled={busy} onClick={close}>
          <X size={19} />
        </button>
      </div>
      {children}
    </dialog>
  )
}
export function Field({
  label,
  error,
  children,
}: {
  label: string
  error?: string
  children: ReactNode
}) {
  const id = useId()
  return (
    <label className="field" htmlFor={id}>
      <span id={id + '-label'}>{label}</span>
      {isValidElement<{ id?: string; 'aria-labelledby'?: string }>(children)
        ? cloneElement(children, { id, 'aria-labelledby': id + '-label' })
        : children}
      {error && (
        <small className="field-error" role="alert">
          {error}
        </small>
      )}
    </label>
  )
}
export function Forbidden() {
  return (
    <Empty
      title="Access restricted"
      text="Your role does not have permission to view this page. Contact your administrator if you need access."
    />
  )
}
