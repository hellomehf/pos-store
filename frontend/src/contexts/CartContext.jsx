import { createContext, useContext, useState, useCallback } from 'react'

const CartContext = createContext(null)

export function CartProvider({ children }) {
  const [items, setItems] = useState([])
  const [paymentMethod, setPaymentMethod] = useState('cash')
  const [taxRate, setTaxRate] = useState(() => {
    const saved = localStorage.getItem('tax_rate')
    return saved ? parseFloat(saved) || 0 : 0
  })

  const addItem = useCallback((product) => {
    setItems(prev => {
      const existing = prev.find(i => i.product_id === product.id)
      if (existing) {
        return prev.map(i =>
          i.product_id === product.id
            ? { ...i, quantity: i.quantity + 1, line_total: (i.quantity + 1) * i.unit_price }
            : i
        )
      }
      return [...prev, {
        product_id: product.id,
        product_name: product.name,
        unit_price: parseFloat(product.price),
        quantity: 1,
        line_total: parseFloat(product.price),
      }]
    })
  }, [])

  const removeItem = useCallback((productId) => {
    setItems(prev => prev.filter(i => i.product_id !== productId))
  }, [])

  const updateQuantity = useCallback((productId, delta) => {
    setItems(prev => prev.map(i => {
      if (i.product_id !== productId) return i
      const newQty = i.quantity + delta
      if (newQty <= 0) return null
      return { ...i, quantity: newQty, line_total: newQty * i.unit_price }
    }).filter(Boolean))
  }, [])

  const voidSale = useCallback(() => {
    setItems([])
    setPaymentMethod('cash')
  }, [])

  const subtotal = items.reduce((sum, i) => sum + i.line_total, 0)
  const taxAmount = parseFloat((subtotal * (taxRate / 100)).toFixed(2))
  const total = parseFloat((subtotal + taxAmount).toFixed(2))
  const itemCount = items.reduce((sum, i) => sum + i.quantity, 0)

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
