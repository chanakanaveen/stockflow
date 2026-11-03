import { z } from 'zod'
export const decimal = z
  .string()
  .regex(/^[0-9]{1,14}(\.[0-9]{1,4})?$/, 'Use a decimal with up to four places.')
export const positiveDecimal = decimal.refine(
  (value) => /[1-9]/.test(value),
  'Quantity must be greater than zero.',
)
export const linesSchema = z
  .array(
    z.object({ product_id: z.string().min(1, 'Choose a product.'), quantity: positiveDecimal }),
  )
  .min(1, 'Add at least one line.')
  .max(100, 'Use at most 100 lines.')
  .refine(
    (lines) => new Set(lines.map((line) => line.product_id)).size === lines.length,
    'Each product can appear only once.',
  )
export const documentSchema = z.object({
  store_id: z.string().min(1, 'Choose a store.'),
  from_store_id: z.string(),
  to_store_id: z.string(),
  reference: z.string().max(255),
  remarks: z.string().max(2000),
  lines: linesSchema,
})
export const transferSchema = documentSchema
  .extend({
    store_id: z.string(),
    from_store_id: z.string().min(1, 'Choose a source store.'),
    to_store_id: z.string().min(1, 'Choose a destination store.'),
  })
  .refine((v) => v.from_store_id !== v.to_store_id, {
    message: 'Choose a different destination.',
    path: ['to_store_id'],
  })
export type DocumentFormValues = z.infer<typeof documentSchema>
