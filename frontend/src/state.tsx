import { createContext, useContext, useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { api } from './api'
import type { User, Product, Store, Category, Envelope } from './types'
export const Session = createContext<{ user: User; logout: () => Promise<void> } | null>(null)
export function useSession() {
  const value = useContext(Session)
  if (!value) throw new Error('Session unavailable')
  return value
}
export function useList<T>(path: string, params = '', enabled = true) {
  return useQuery({
    queryKey: ['list', path, params],
    queryFn: ({ signal }) => api.list<T>(path, params, signal),
    enabled,
  })
}
export function useLookups() {
  const { user } = useSession()
  const load = async <T,>(path: string, signal: AbortSignal): Promise<T[]> => {
    const all: T[] = []
    let page = 1
    let last = 1
    do {
      const result: Envelope<T[]> = await api.list<T>(path, 'per_page=100&page=' + page, signal)
      all.push(...result.data)
      last = result.meta.last_page ?? 1
      page++
    } while (page <= last)
    return all
  }
  const products = useQuery({
    queryKey: ['lookup', 'products'],
    queryFn: ({ signal }) => load<Product>('products', signal),
    enabled: user.permissions.includes('products.view'),
  })
  const stores = useQuery({
    queryKey: ['lookup', 'stores'],
    queryFn: ({ signal }) => load<Store>('stores', signal),
    enabled: user.permissions.includes('stores.view'),
  })
  const categories = useQuery({
    queryKey: ['lookup', 'categories'],
    queryFn: ({ signal }) => load<Category>('categories', signal),
    enabled: user.permissions.includes('categories.view'),
  })
  return {
    products: products.data ?? [],
    stores: stores.data ?? [],
    categories: categories.data ?? [],
    error: products.error ?? stores.error ?? categories.error,
    loading: products.isLoading || stores.isLoading || categories.isLoading,
  }
}
export function useFilters() {
  const [params, setParams] = useSearchParams()
  const update = (key: string, value: string) =>
    setParams(
      (old) => {
        const next = new URLSearchParams(old)
        if (value) next.set(key, value)
        else next.delete(key)
        if (key !== 'page') next.delete('page')
        return next
      },
      { replace: true },
    )
  return { params, update }
}
export function useDebounced(value: string, delay = 300) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(id)
  }, [value, delay])
  return debounced
}
export function quantityUnits(value: string): bigint {
  const [whole, fraction = ''] = value.split('.')
  return BigInt(whole || '0') * 10000n + BigInt(fraction.padEnd(4, '0').slice(0, 4))
}
export function displayQuantity(value: string): string {
  const [whole, fraction = ''] = value.split('.')
  return whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + fraction.padEnd(4, '0')
}
export function displayDate(value: string | null): string {
  return value
    ? new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: import.meta.env.VITE_DISPLAY_TIMEZONE || 'Asia/Colombo',
      }).format(new Date(value))
    : '—'
}
export function postingKey(userId: number, path: string) {
  const name = 'stockflow:posting:' + userId + ':' + path
  let key = sessionStorage.getItem(name)
  if (!key) {
    key = crypto.randomUUID()
    sessionStorage.setItem(name, key)
  }
  return { key, clear: () => sessionStorage.removeItem(name) }
}
