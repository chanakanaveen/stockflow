import { describe, expect, it } from 'vitest'
import { positiveDecimal, transferSchema } from './schemas'
import { displayQuantity, postingKey, quantityUnits } from './state'
describe('Inventory client invariants', () => {
  it.each([1, '0', '-1', '1e3', '1.00001', '100000000000000'])(
    'rejects invalid quantity %s',
    (value) => expect(positiveDecimal.safeParse(value).success).toBe(false),
  )
  it('keeps exact large quantities and four places', () => {
    expect(displayQuantity('99999999999999.9999')).toBe('99,999,999,999,999.9999')
    expect(quantityUnits('0.1000') + quantityUnits('0.2000')).toBe(3000n)
  })
  it('rejects duplicate product lines', () => {
    const line = { product_id: '1', quantity: '1.0000' }
    expect(
      transferSchema.safeParse({
        store_id: '',
        from_store_id: '1',
        to_store_id: '2',
        reference: '',
        remarks: '',
        lines: [line, line],
      }).success,
    ).toBe(false)
  })
  it('keeps retry keys across remounts but scopes them to the actor', () => {
    sessionStorage.clear()
    const a = postingKey(1, 'transfers/1/approve')
    expect(postingKey(1, 'transfers/1/approve').key).toBe(a.key)
    expect(postingKey(2, 'transfers/1/approve').key).not.toBe(a.key)
    a.clear()
    expect(postingKey(1, 'transfers/1/approve').key).not.toBe(a.key)
  })
})
