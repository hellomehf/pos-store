import { createContext, useContext, useState, useCallback } from 'react'

const CartContext = createContext(null)

export function CartProvider({ children }) {
  const [items, setItems] = useState([])
  const [paymentMethod, setPaymentMethod] = useState('cash')
  const [taxRate, setTaxRate] = useState(() => {
    const saved = localStorage.getItem('tax_rate')
    const parsed = parseFloat(saved)
    return !isNaN(parsed) && parsed >= 0 ? parsed : 0
  })

  const addItem = useCallback((product) => {
    if (!product) return

    const price = Number(product.price || 0)

    setItems(prev => {
      const existing = prev.find(i => i.product_id === product.id)
      if (existing) {
        return prev.map(i => {
          if (i.product_id === product.id) {
            const newQty = (i.quantity || 0) + 1
            const unitPrice = Number(i.unit_price || price)
            return {
              ...i,
              quantity: newQty,
              line_total: Number((newQty * unitPrice).toFixed(2))
            }
          }
          return i
        })
      }

      return [...prev, {
        product_id: product.id,
        product_name: product.name || 'Unknown Item',
        unit_price: price,
        quantity: 1,
        line_total: Number(price.toFixed(2)),
      }]
    })
  }, [])

  const removeItem = useCallback((productId) => {
    setItems(prev => prev.filter(i => i.product_id !== productId))
  }, [])

  const updateQuantity = useCallback((productId, delta) => {
    setItems(prev => prev.map(i => {
      if (i.product_id !== productId) return i
      const newQty = (i.quantity || 0) + delta
      if (newQty <= 0) return null

      const unitPrice = Number(i.unit_price || 0)
      return {
        ...i,
        quantity: newQty,
        line_total: Number((newQty * unitPrice).toFixed(2))
      }
    }).filter(Boolean))
  }, [])

  const voidSale = useCallback(() => {
    setItems([])
    setPaymentMethod('cash')
  }, [])

  // Defensive calculations ensuring totals never return NaN
  const subtotal = items.reduce((sum, i) => sum + Number(i?.line_total || 0), 0)
  const currentTaxRate = Number(taxRate || 0)
  const taxAmount = Number((subtotal * (currentTaxRate / 100)).toFixed(2))
  const total = Number((subtotal + taxAmount).toFixed(2))
  const itemCount = items.reduce((sum, i) => sum + Number(i?.quantity || 0), 0)

  return (
    <CartContext.Provider value={{
      items, addItem, removeItem, updateQuantity, voidSale,
      paymentMethod, setPaymentMethod,
      taxRate, setTaxRate,
      subtotal, taxAmount, total, itemCount,
    }}>
      {children}
    </CartContext.Provider>
  )
}

export function useCart() {
  const context = useContext(CartContext)
  if (!context) throw new Error('useCart must be used within CartProvider')
  return context
}
