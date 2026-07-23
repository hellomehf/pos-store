import { useState, useEffect, useCallback, useRef, useReducer } from 'react'
import api from '../api/api'
import { useCart } from '../contexts/CartContext'
import { useAuth } from '../contexts/AuthContext'
import { useStore } from '../contexts/StoreContext'
import { useToast } from '../contexts/ToastContext'
import KhqrModal from '../components/KhqrModal'

const formatKHR = (usd, rate) => Math.round((usd || 0) * (rate || 4100)).toLocaleString()
const formatDual = (usd, rate) => `$${Number(usd || 0).toFixed(2)} / ${formatKHR(usd, rate)}៛`

function ReceiptModal({ sale, onClose, onPrint }) {
  const receiptRef = useRef(null)
  const { storeName: receiptStoreName } = useStore()

  const rateUsed = sale?.exchange_rate || 4100
  const totalVal = Number(sale?.total || 0)
  const totalKhr = sale?.total_khr || Math.round(totalVal * rateUsed)
  const tenderedKhr = sale?.tendered_khr || Math.round(Number(sale?.tendered_amount || 0) * rateUsed)
  const changeKhr = sale?.change_khr || Math.round(Number(sale?.change_amount || 0) * rateUsed)

  const formatPaymentMethod = (method) => {
    if (method === 'khqr') return 'KHQR'
    return method ? method.charAt(0).toUpperCase() + method.slice(1) : ''
  }

  const handlePrint = () => {
    const content = receiptRef.current
    if (!content) return
    const iframe = document.createElement('iframe')
    iframe.style.position = 'fixed'
    iframe.style.width = '0'
    iframe.style.height = '0'
    iframe.style.border = 'none'
    document.body.appendChild(iframe)
    const doc = iframe.contentWindow.document
    doc.open()
    doc.write(`<!DOCTYPE html><html><head><title>Receipt</title><style>
      @page { margin: 8mm; size: 80mm auto; }
      body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; margin: 0; padding: 0; }
      .receipt-header { text-align: center; margin-bottom: 8px; }
      .receipt-header h4 { margin: 0; font-size: 16px; }
      .receipt-header p { margin: 2px 0; font-size: 11px; }
      hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
      .col-header { display: grid; grid-template-columns: 1fr 50px 80px; gap: 2px; margin-bottom: 4px; font-size: 10px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; }
      .col-header span:nth-child(2) { text-align: center; }
      .col-header span:nth-child(3) { text-align: right; }
      .item-row { display: grid; grid-template-columns: 1fr 50px 80px; gap: 2px; margin: 3px 0; font-family: 'Courier New', monospace; }
      .item-row span:nth-child(1) { text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .item-row span:nth-child(2) { text-align: center; }
      .item-row span:nth-child(3) { text-align: right; }
      .summary-row { display: flex; justify-content: space-between; margin: 2px 0; font-family: 'Courier New', monospace; }
      .summary-row.bold { font-weight: bold; font-size: 13px; border-top: 1px dashed #000; padding-top: 6px; margin-top: 6px; }
      .footer { text-align: center; margin-top: 8px; font-size: 11px; }
    </style></head><body>
      <div class="receipt-header">
        <h4>${receiptStoreName || ''}</h4>
        <p>Register #1 &middot; ${sale?.user?.name || ''}</p>
        <p>${sale?.created_at ? new Date(sale.created_at).toLocaleString() : ''}</p>
        <p><strong>${sale?.invoice_number || ''}</strong></p>
      </div>
      <hr />
      <div class="col-header"><span>ITEM</span><span>QTY</span><span>PRICE</span></div>
      <hr style="margin: 2px 0 6px" />
      ${(sale?.items || []).map(item => `<div class="item-row"><span>${item.product_name || ''}</span><span>${item.quantity || 0}</span><span>$${Number(item.line_total || 0).toFixed(2)}</span></div>`).join('')}
      <hr />
      <div class="summary-row"><span>Subtotal</span><span>$${Number(sale?.subtotal || 0).toFixed(2)}</span></div>
      <div class="summary-row"><span>Tax (${Number(sale?.tax_rate || 0).toFixed(0)}%)</span><span>$${Number(sale?.tax_amount || 0).toFixed(2)}</span></div>
      <div class="summary-row bold"><span>TOTAL</span><span>$${totalVal.toFixed(2)} / ${totalKhr.toLocaleString()}៛</span></div>
      <div class="summary-row"><span>Payment</span><span>${formatPaymentMethod(sale?.payment_method)}</span></div>
      ${sale?.payment_method === 'cash' ? `<div class="summary-row"><span>Tendered</span><span>$${Number(sale?.tendered_amount || 0).toFixed(2)} / ${tenderedKhr.toLocaleString()}៛</span></div><div class="summary-row"><span>Change</span><span>$${Number(sale?.change_amount || 0).toFixed(2)} / ${changeKhr.toLocaleString()}៛</span></div>` : ''}
      <div class="summary-row"><span>Rate</span><span>$1.00 = ${rateUsed.toLocaleString()}៛</span></div>
      <hr />
      <div class="footer">Thank you for shopping with us!</div>
    </body></html>`)
    doc.close()
    iframe.contentWindow.focus()
    iframe.contentWindow.print()
    setTimeout(() => document.body.removeChild(iframe), 1000)
  }

  return (
    <div className="receipt-overlay" onClick={onClose}>
      <div className="receipt-card" onClick={e => e.stopPropagation()}>
        <div ref={receiptRef}>
          <div className="receipt-header">
            <h4>{receiptStoreName}</h4>
            <small>Register #1 &middot; {sale?.user?.name || ''}</small>
            <br />
            <small>{sale?.created_at ? new Date(sale.created_at).toLocaleString() : ''}</small>
            <br />
            <small><strong>{sale?.invoice_number || ''}</strong></small>
          </div>

          <hr className="receipt-divider" />

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 50px 80px', gap: 2, marginBottom: 6, fontSize: 11, textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px', fontFamily: 'var(--font-mono)' }}>
            <span style={{ textAlign: 'left' }}>Item</span>
            <span style={{ textAlign: 'center' }}>Qty</span>
            <span style={{ textAlign: 'right' }}>Price</span>
          </div>
          <div style={{ borderTop: '1px dashed var(--border-color)', marginBottom: 8 }} />

          <div className="receipt-items">
            {(sale?.items || []).map((item, i) => (
              <div key={i} style={{ display: 'grid', gridTemplateColumns: '1fr 50px 80px', gap: 2, alignItems: 'center', padding: '3px 0', fontFamily: 'var(--font-mono)', fontSize: 13 }}>
                <span style={{ textAlign: 'left', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.product_name}</span>
                <span style={{ textAlign: 'center' }}>{item.quantity}</span>
                <span style={{ textAlign: 'right' }}>${Number(item.line_total || 0).toFixed(2)}</span>
              </div>
            ))}
          </div>

          <hr className="receipt-divider" />

          <div className="receipt-totals" style={{ fontFamily: 'var(--font-mono)' }}>
            <div className="receipt-total-row"><span>Subtotal</span><span>${Number(sale?.subtotal || 0).toFixed(2)}</span></div>
            <div className="receipt-total-row"><span>Tax ({Number(sale?.tax_rate || 0).toFixed(0)}%)</span><span>${Number(sale?.tax_amount || 0).toFixed(2)}</span></div>
            <div className="receipt-total-row grand"><span>Total</span><span>${totalVal.toFixed(2)} / {totalKhr.toLocaleString()}៛</span></div>
            <div className="receipt-total-row"><span>Payment</span><span>{formatPaymentMethod(sale?.payment_method)}</span></div>
            {sale?.payment_method === 'cash' && (
              <>
                <div className="receipt-total-row"><span>Tendered</span><span>${Number(sale?.tendered_amount || 0).toFixed(2)} / {tenderedKhr.toLocaleString()}៛</span></div>
                <div className="receipt-total-row"><span>Change</span><span>${Number(sale?.change_amount || 0).toFixed(2)} / {changeKhr.toLocaleString()}៛</span></div>
              </>
            )}
            <div className="receipt-total-row"><span>Rate</span><span>$1.00 = {rateUsed.toLocaleString()}៛</span></div>
          </div>

          <hr className="receipt-divider" />
          <div className="receipt-footer">Thank you for shopping with us!</div>
        </div>
        <div className="receipt-actions">
          <button className="btn-close-receipt" onClick={onClose}>Close</button>
          <button className="btn-new-sale" onClick={handlePrint}><i className="fas fa-print" /> Print</button>
        </div>
      </div>
    </div>
  )
}

function CashKeypad({ tendered, setTendered, total, currency, setCurrency, exchangeRate }) {
  const isKHR = currency === 'KHR'
  const tenderedNum = parseFloat(tendered) || 0
  const rate = exchangeRate || 4100
  const tenderedUSD = isKHR ? tenderedNum / rate : tenderedNum
  const changeUSD = Math.max(0, tenderedUSD - (total || 0))

  const presets = isKHR
    ? [{ label: '៛20K', value: 20000 }, { label: '៛40K', value: 40000 }, { label: '៛50K', value: 50000 }, { label: '៛100K', value: 100000 }]
    : [{ label: '$5', value: 5 }, { label: '$10', value: 10 }, { label: '$20', value: 20 }, { label: '$50', value: 50 }]

  const handleKey = (key) => {
    if (key === 'backspace') {
      setTendered(prev => prev.length > 1 ? prev.slice(0, -1) : '0')
    } else if (key === '.') {
      if (!isKHR && !tendered.includes('.')) setTendered(prev => prev + '.')
    } else {
      setTendered(prev => {
        if (prev === '0' && key !== '.') return key
        if (!isKHR && prev.includes('.') && prev.split('.')[1].length >= 2) return prev
        return prev + key
      })
    }
  }

  const switchCurrency = (c) => {
    setCurrency(c)
    setTendered('0')
  }

  return (
    <div className="keypad-area">
      <div className="currency-toggle">
        <button className={`currency-toggle-btn ${!isKHR ? 'active' : ''}`} onClick={() => switchCurrency('USD')}>
          <i className="fas fa-dollar-sign" /> USD ($)
        </button>
        <button className={`currency-toggle-btn ${isKHR ? 'active' : ''}`} onClick={() => switchCurrency('KHR')}>
          <i className="fas fa-money-bill" /> KHR (៛)
        </button>
      </div>

      <div className="keypad-display">
        <div className="keypad-display-label">Tendered</div>
        <div className="keypad-display-value">
          {isKHR ? `${tenderedNum.toLocaleString()}៛` : `$${tenderedNum.toFixed(2)}`}
        </div>
        <div className="keypad-display-equiv">
          {isKHR
            ? `≈ $${(tenderedNum / rate).toFixed(2)}`
            : `≈ ${formatKHR(tenderedNum, rate)}៛`
          }
        </div>
      </div>

      <div className="keypad-presets">
        {presets.map(p => (
          <button key={p.value} className="keypad-preset" onClick={() => setTendered(p.value.toString())}>{p.label}</button>
        ))}
      </div>

      <div className="keypad-grid">
        {['7','8','9','4','5','6','1','2','3'].map(k => (
          <button key={k} className="keypad-btn" onClick={() => handleKey(k)}>{k}</button>
        ))}
        {!isKHR && <button className="keypad-btn" onClick={() => handleKey('.')}>.</button>}
        <button className="keypad-btn" onClick={() => handleKey('0')}>0</button>
        <button className="keypad-btn backspace" onClick={() => handleKey('backspace')}><i className="fas fa-backspace" /></button>
      </div>

      <div className="keypad-change">
        <span>Change Due</span>
        <span className="mono" style={{ color: 'var(--success)' }}>{formatDual(changeUSD, rate)}</span>
      </div>
    </div>
  )
}

export default function POS() {
  const [categories, setCategories] = useState([])
  const [products, setProducts] = useState([])
  const [activeCategory, setActiveCategory] = useState(null)
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [tendered, setTendered] = useState('0')
  const [tenderCurrency, setTenderCurrency] = useState('USD')
  const [exchangeRate, setExchangeRate] = useState(() => {
    const saved = localStorage.getItem('exchange_rate')
    return saved ? parseInt(saved, 10) || 4100 : 4100
  })
  const [completedSale, setCompletedSale] = useState(null)
  const [processing, setProcessing] = useState(false)
  const [showKhqr, setShowKhqr] = useState(false)
  const [khqrCartSnapshot, setKhqrCartSnapshot] = useState(null)
  const [khqrLoading, setKhqrLoading] = useState(false)
  const failedImages = useRef(new Set())
  const [, forceUpdate] = useReducer(x => x + 1, 0)

  const { items = [], addItem, updateQuantity, removeItem, voidSale, paymentMethod, setPaymentMethod, taxRate, setTaxRate, subtotal = 0, taxAmount = 0, total = 0, itemCount = 0 } = useCart()
  const { user } = useAuth()
  const { storeName, storeLogo } = useStore()
  const toast = useToast()

  useEffect(() => {
    Promise.all([
      api.get('/categories'),
      api.get('/products'),
      api.get('/settings/store'),
    ]).then(([catRes, prodRes, settingsRes]) => {
      setCategories(catRes?.data || [])
      setProducts(prodRes?.data || [])

      const s = settingsRes?.data
      if (s?.exchange_rate) {
        setExchangeRate(s.exchange_rate)
        localStorage.setItem('exchange_rate', s.exchange_rate)
      }
      if (s?.tax_rate !== undefined && s?.tax_rate !== null) {
        setTaxRate(s.tax_rate)
        localStorage.setItem('tax_rate', s.tax_rate)
      }
    }).catch(() => toast.error('Failed to load data'))
      .finally(() => setLoading(false))
  }, [])

  const filteredProducts = (products || [])
    .filter(p => !activeCategory || p.category_id === activeCategory)
    .filter(p => !search || p.name?.toLowerCase().includes(search.toLowerCase()) || p.sku?.toLowerCase().includes(search.toLowerCase()))

  const handleCharge = async () => {
    if (items.length === 0) return toast.error('Cart is empty')

    if (paymentMethod === 'khqr') {
      setKhqrLoading(true)
      try {
        await api.post('/pending-payments', {
          expected_amount: total,
          currency: 'USD',
          exchange_rate: exchangeRate,
        })
        setKhqrCartSnapshot({
          items: items.map(i => ({ product_id: i.product_id, quantity: i.quantity })),
          total,
          taxRate,
          exchangeRate,
        })
        setShowKhqr(true)
      } catch (err) {
        toast.error(err.response?.data?.message || 'Failed to create payment session')
      } finally {
        setKhqrLoading(false)
      }
      return
    }

    const tenderedNum = parseFloat(tendered) || 0
    const tenderedUSD = tenderCurrency === 'KHR' ? tenderedNum / exchangeRate : tenderedNum
    if (paymentMethod === 'cash' && tenderedUSD < total) {
      return toast.error('Tendered amount is less than total')
    }

    setProcessing(true)
    try {
      const payload = {
        items: items.map(i => ({ product_id: i.product_id, quantity: i.quantity })),
        payment_method: paymentMethod,
        tendered_amount: paymentMethod === 'cash' ? parseFloat(tenderedUSD.toFixed(2)) : total,
        tax_rate: taxRate,
        exchange_rate: exchangeRate,
      }
      const res = await api.post('/sales', payload)
      setCompletedSale(res.data)
      voidSale()
      setTendered('0')
      setTenderCurrency('USD')
      toast.success('Sale completed!')

      const prodRes = await api.get('/products')
      setProducts(prodRes?.data || [])
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to process sale')
    } finally {
      setProcessing(false)
    }
  }

  const handleKhqrComplete = useCallback(async (snapshot) => {
    setShowKhqr(false)
    setKhqrCartSnapshot(null)
    setProcessing(true)
    try {
      const payload = {
        items: snapshot.items,
        payment_method: 'khqr',
        tendered_amount: snapshot.total,
        tax_rate: snapshot.taxRate,
        exchange_rate: snapshot.exchangeRate,
      }
      const res = await api.post('/sales', payload)
      setCompletedSale(res.data)
      voidSale()
      setTendered('0')
      setTenderCurrency('USD')
      toast.success('Payment completed!')

      const prodRes = await api.get('/products')
      setProducts(prodRes?.data || [])
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to process sale')
    } finally {
      setProcessing(false)
    }
  }, [])

  const handleNewSale = () => {
    setCompletedSale(null)
  }

  const handleVoid = () => {
    if (items.length === 0) return
    if (confirm('Void this sale? All items will be removed.')) {
      voidSale()
      setTendered('0')
      setTenderCurrency('USD')
      toast.info('Sale voided')
    }
  }

  if (loading) return <div className="d-flex justify-content-center py-5"><i className="fas fa-spinner fa-spin fa-2x" style={{ color: 'var(--text-muted)' }} /></div>

  const getInitials = (name) => name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || '?'

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <div className="pos-header">
        <div className="pos-header-left">
          <div className="pos-header-brand-logo">
            {storeLogo ? (
              <img src={storeLogo} alt="Store Logo" className="pos-header-brand-logo-img" />
            ) : (
              <span style={{ fontFamily: 'var(--font-heading)', fontWeight: 700, fontSize: 13, color: '#fff' }}>PS</span>
            )}
          </div>
          <div className="pos-header-info">
            <h5>{storeName}</h5>
            <small>Register #1</small>
          </div>
        </div>
        <div className="pos-header-center">
          <div className="pos-search">
            <i className="fas fa-search pos-search-icon" />
            <input
              type="text"
              className="pos-search-input"
              placeholder="Search products..."
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
            {search && (
              <button className="pos-search-clear" onClick={() => setSearch('')}>
                <i className="fas fa-times" />
              </button>
            )}
          </div>
        </div>
        <div className="pos-header-controls">
          <div className="pos-header-rate">
            <span className="pos-rate-label">$1 =</span>
            <input
              type="number"
              className="pos-rate-input"
              value={exchangeRate}
              disabled={user?.role !== 'admin'}
              onChange={e => {
                const v = parseInt(e.target.value)
                if (v > 0) {
                  setExchangeRate(v)
                  localStorage.setItem('exchange_rate', v)
                  api.post('/settings/store', { exchange_rate: v }).catch(() => {})
                }
              }}
            />
            <span className="pos-rate-suffix">៛</span>
          </div>
          <div className="pos-header-rate">
            <span className="pos-rate-label">Tax:</span>
            <input
              type="number"
              className="pos-rate-input"
              value={taxRate}
              disabled={user?.role !== 'admin'}
              onChange={e => {
                const v = parseFloat(e.target.value)
                if (v >= 0 && v <= 100) {
                  setTaxRate(v)
                  localStorage.setItem('tax_rate', v)
                  api.post('/settings/store', { tax_rate: v }).catch(() => {})
                }
              }}
            />
            <span className="pos-rate-suffix">%</span>
          </div>
        </div>
        <div className="pos-header-right">
          <div className="pos-header-clock">
            <POSHeaderClock />
          </div>
          <div className="pos-header-user">
            <div className="pos-header-avatar" style={{ background: 'var(--amber)' }}>
              {getInitials(user?.name)}
            </div>
            <div className="pos-header-user-info">
              <div className="pos-header-user-name">{user?.name}</div>
              <div className="pos-header-user-role">{user?.role}</div>
            </div>
          </div>
        </div>
      </div>

      <div className="pos-layout">
        <div className="pos-left">
          <div className="category-tabs">
            <button
              className={`category-tab ${!activeCategory ? 'active' : ''}`}
              onClick={() => setActiveCategory(null)}
            >All</button>
            {categories.map(cat => (
              <button
                key={cat.id}
                className={`category-tab ${activeCategory === cat.id ? 'active' : ''}`}
                onClick={() => setActiveCategory(cat.id)}
              >{cat.name}</button>
            ))}
          </div>
          <div className="product-grid">
            {filteredProducts.map(product => (
              <div
                key={product.id}
                className="product-card"
                onClick={() => addItem(product)}
              >
                <div className="product-card-image">
                  {product.image && !failedImages.current.has(product.id) ? (
                    <img
                      src={`/storage/${product.image}`}
                      alt={product.name}
                      onError={() => { failedImages.current.add(product.id); forceUpdate() }}
                    />
                  ) : (
                    <i className="fas fa-box" />
                  )}
                </div>
                <div className="product-card-name">{product.name}</div>
                <div className="product-card-category">{product.category?.name}</div>
                <div className="product-card-footer">
                  <span className="product-card-price">${Number(product.price || 0).toFixed(2)}</span>
                  <span className={`product-card-stock ${product.stock_qty <= 10 ? 'low' : ''}`}>
                    {product.stock_qty}
                  </span>
                </div>
              </div>
            ))}
            {filteredProducts.length === 0 && (
              <div className="empty-state" style={{ gridColumn: '1 / -1' }}>
                <i className="fas fa-box-open" />
                <p>No products found</p>
              </div>
            )}
          </div>
        </div>

        <div className="pos-right">
          <div className="cart-header">
            <div>
              <h6>Current Sale <span className="cart-item-count">{itemCount}</span></h6>
            </div>
            <button className="btn-danger-custom" style={{ padding: '5px 12px', fontSize: 12 }} onClick={handleVoid}>
              <i className="fas fa-ban" /> VOID
            </button>
          </div>

          <div className="cart-body">
            <div className="cart-items">
              {items.length === 0 ? (
                <div className="empty-cart-container">
                  <i className="fas fa-shopping-cart empty-cart-icon" />
                  <p>Click products to add to sale</p>
                </div>
              ) : (
                items.map(item => (
                  <div className="cart-item" key={item.product_id}>
                    <div className="cart-item-info">
                      <div className="cart-item-name">{item.product_name}</div>
                      <div className="cart-item-price">${Number(item.unit_price || 0).toFixed(2)} each</div>
                    </div>
                    <div className="qty-stepper">
                      <button onClick={() => updateQuantity(item.product_id, -1)}><i className="fas fa-minus" /></button>
                      <span>{item.quantity}</span>
                      <button onClick={() => updateQuantity(item.product_id, 1)}><i className="fas fa-plus" /></button>
                    </div>
                    <div className="cart-item-total">${Number(item.line_total || 0).toFixed(2)}</div>
                  </div>
                ))
              )}
            </div>

            <div className="cart-summary">
              <div className="cart-summary-row">
                <span style={{ color: 'var(--text-secondary)' }}>Subtotal</span>
                <span className="mono">${Number(subtotal || 0).toFixed(2)}</span>
              </div>
              <div className="cart-summary-row">
                <span style={{ color: 'var(--text-secondary)' }}>Tax ({taxRate}%)</span>
                <span className="mono">${Number(taxAmount || 0).toFixed(2)}</span>
              </div>
              <div className="cart-summary-row total">
                <span>Total Due</span>
                <span className="mono" style={{ color: 'var(--secondary)', fontSize: 14 }}>{formatDual(total, exchangeRate)}</span>
              </div>
            </div>

            <div className="payment-toggle">
              <button className={`payment-btn ${paymentMethod === 'cash' ? 'active' : ''}`} onClick={() => setPaymentMethod('cash')}>
                <i className="fas fa-money-bill-wave" /> Cash
              </button>
              <button className={`payment-btn ${paymentMethod === 'card' ? 'active' : ''}`} onClick={() => setPaymentMethod('card')}>
                <i className="fas fa-credit-card" /> Card
              </button>
              <button className={`payment-btn ${paymentMethod === 'khqr' ? 'active' : ''}`} onClick={() => setPaymentMethod('khqr')}>
                <i className="fas fa-qrcode" /> KHQR
              </button>
            </div>

            {paymentMethod === 'cash' && (
              <CashKeypad tendered={tendered} setTendered={setTendered} total={total} currency={tenderCurrency} setCurrency={setTenderCurrency} exchangeRate={exchangeRate} />
            )}

            <button
              className="charge-btn"
              disabled={items.length === 0 || processing || khqrLoading || (paymentMethod === 'cash' && ((tenderCurrency === 'KHR' ? (parseFloat(tendered) || 0) / exchangeRate : parseFloat(tendered) || 0)) < total)}
              onClick={handleCharge}
            >
              {processing || khqrLoading ? (
                <><i className="fas fa-spinner fa-spin" /> {khqrLoading ? 'Preparing QR...' : 'Processing...'}</>
              ) : (
                <><i className="fas fa-check-circle" /> Charge {formatDual(total, exchangeRate)}</>
              )}
            </button>
          </div>
        </div>
      </div>

      {completedSale && (
        <ReceiptModal
          sale={completedSale}
          onClose={() => setCompletedSale(null)}
          onPrint={handleNewSale}
        />
      )}

      {showKhqr && khqrCartSnapshot && (
        <KhqrModal
          amount={khqrCartSnapshot.total}
          currency="USD"
          exchangeRate={khqrCartSnapshot.exchangeRate}
          cartSnapshot={khqrCartSnapshot}
          onSuccess={handleKhqrComplete}
          onCancel={() => { setShowKhqr(false); setKhqrCartSnapshot(null) }}
        />
      )}
    </div>
  )
}

function POSHeaderClock() {
  const [time, setTime] = useState(new Date())

  useEffect(() => {
    const interval = setInterval(() => setTime(new Date()), 1000)
    return () => clearInterval(interval)
  }, [])

  return <>{time.toLocaleDateString()} {time.toLocaleTimeString()}</>
}
