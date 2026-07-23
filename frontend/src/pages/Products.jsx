import { useState, useEffect, useRef, useCallback } from 'react'
import { Html5Qrcode } from 'html5-qrcode'
import api from '../api/api'
import { useAuth } from '../contexts/AuthContext'
import { useToast } from '../contexts/ToastContext'

export default function Products() {
  const { user } = useAuth()
  const [products, setProducts] = useState([])
  const [categories, setCategories] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [showModal, setShowModal] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState({ name: '', sku: '', barcode: '', price: '', cost_price: '', stock_qty: '', category_id: '' })
  const [imageFile, setImageFile] = useState(null)
  const [imagePreview, setImagePreview] = useState('')
  const [dragging, setDragging] = useState(false)
  const [restockModal, setRestockModal] = useState(null)
  const [restockQty, setRestockQty] = useState('')
  const [productToDelete, setProductToDelete] = useState(null)
  const [scannerActive, setScannerActive] = useState(false)
  const [previewSku, setPreviewSku] = useState('')
  const scannerRef = useRef(null)
  const toast = useToast()

  const load = () => {
    Promise.all([
      api.get(`/products${search ? `?search=${search}` : ''}`),
      api.get('/categories'),
    ]).then(([p, c]) => {
      setProducts(p.data)
      setCategories(c.data)
    }).finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [search])

  const openAdd = () => {
    setEditing(null)
    setForm({ name: '', sku: '', barcode: '', price: '', cost_price: '', stock_qty: '', category_id: '' })
    setImageFile(null)
    setImagePreview('')
    setPreviewSku('')
    setShowModal(true)
  }

  const fetchPreviewSku = (categoryId) => {
    if (!categoryId) { setPreviewSku(''); return }
    api.get(`/products/preview-sku?category_id=${categoryId}`)
      .then(res => setPreviewSku(res.data.sku))
      .catch(() => setPreviewSku(''))
  }

  const openEdit = (p) => {
    setEditing(p)
    setForm({ name: p.name, sku: p.sku, barcode: p.barcode ?? '', price: p.price, cost_price: p.cost_price ?? '', stock_qty: p.stock_qty, category_id: p.category_id })
    setImageFile(null)
    setImagePreview(p.image ? `/storage/${p.image}` : '')
    setShowModal(true)
  }

  const handleSave = async (e) => {
    e.preventDefault()
    try {
      const fd = new FormData()
      fd.append('name', form.name)
      if (form.sku) fd.append('sku', form.sku)
      if (form.barcode) fd.append('barcode', form.barcode)
      fd.append('price', form.price)
      fd.append('cost_price', form.cost_price)
      fd.append('stock_qty', form.stock_qty)
      fd.append('category_id', form.category_id)
      if (imageFile) fd.append('image', imageFile)

      if (editing) {
        fd.append('_method', 'PUT')
        await api.post(`/products/${editing.id}`, fd)
        toast.success('Product updated')
      } else {
        await api.post('/products', fd)
        toast.success('Product created')
      }
      setShowModal(false)
      stopScanner()
      load()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to save product')
    }
  }

  const handleFile = (file) => {
    if (!file || !file.type.match(/^image\/(png|jpe?g)$/)) {
      toast.error('Only .png, .jpg, .jpeg files are accepted')
      return
    }
    if (file.size > 2 * 1024 * 1024) {
      toast.error('Image must be under 2MB')
      return
    }
    setImageFile(file)
    setImagePreview(URL.createObjectURL(file))
  }

  const handleDrop = (e) => {
    e.preventDefault()
    setDragging(false)
    if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0])
  }

  const confirmDelete = async () => {
    if (!productToDelete) return
    try {
      await api.delete(`/products/${productToDelete.id}`)
      toast.success('Product deleted')
      setProductToDelete(null)
      load()
    } catch { toast.error('Failed to delete') }
  }

  const handleRestock = async (e) => {
    e.preventDefault()
    try {
      await api.post(`/products/${restockModal.id}/restock`, { quantity: parseInt(restockQty) })
      toast.success('Stock updated')
      setRestockModal(null)
      setRestockQty('')
      load()
    } catch { toast.error('Failed to restock') }
  }

  const playBeep = useCallback(() => {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)()
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain)
      gain.connect(ctx.destination)
      osc.frequency.value = 1800
      osc.type = 'sine'
      gain.gain.value = 0.3
      osc.start()
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15)
      osc.stop(ctx.currentTime + 0.15)
    } catch {}
  }, [])

  useEffect(() => {
    if (!scannerActive) return
    let mounted = true
    const timer = setTimeout(async () => {
      if (!mounted || scannerRef.current) return
      const scanner = new Html5Qrcode('barcode-scanner-region')
      scannerRef.current = scanner
      try {
        await scanner.start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: { width: 250, height: 120 }, aspectRatio: 2 },
          (decodedText) => {
            setForm(prev => ({ ...prev, barcode: decodedText }))
            playBeep()
            toast.success(`Scanned: ${decodedText}`)
            ;(async () => {
              if (scannerRef.current) {
                try { const s = scannerRef.current.getState(); if (s === 2) await scannerRef.current.stop() } catch {}
                scannerRef.current = null
              }
              setScannerActive(false)
            })()
            setTimeout(() => {
              const el = document.getElementById('barcode-input-field')
              if (el) el.focus()
            }, 100)
          },
          () => {}
        ).catch((err) => {
          console.error('Camera Start Error Details:', err)
          const msg = err.name === 'NotAllowedError'
            ? 'Camera access denied. Please allow camera permission in your browser settings, or type the barcode manually.'
            : err.name === 'NotFoundError'
            ? 'No camera found on this device. Please type the barcode manually.'
            : err.name === 'NotReadableError'
            ? 'Camera is in use by another app. Please close other camera apps and try again, or type the barcode manually.'
            : 'Camera unavailable. Please type the barcode manually.'
          toast.error(msg)
          scannerRef.current = null
          setScannerActive(false)
        })
      } catch (err) {
        console.error('Camera Start Error Details:', err)
        const msg = err.name === 'NotAllowedError'
          ? 'Camera access denied. Please allow camera permission in your browser settings, or type the barcode manually.'
          : err.name === 'NotFoundError'
          ? 'No camera found on this device. Please type the barcode manually.'
          : 'Camera unavailable. Please type the barcode manually.'
        toast.error(msg)
        scannerRef.current = null
        setScannerActive(false)
      }
    }, 100)
    return () => { mounted = false; clearTimeout(timer) }
  }, [scannerActive, playBeep, toast])

  const stopScanner = useCallback(async () => {
    if (scannerRef.current) {
      try {
        const state = scannerRef.current.getState()
        if (state === 2) await scannerRef.current.stop()
      } catch {}
      scannerRef.current = null
    }
    setScannerActive(false)
  }, [])

  if (loading) return <div className="d-flex justify-content-center py-5"><i className="fas fa-spinner fa-spin fa-2x" style={{ color: 'var(--text-muted)' }} /></div>

  return (
    <div>
      <div className="page-header">
        <h4>Products</h4>
        {user?.role === 'admin' && <button className="btn-primary-custom" onClick={openAdd}><i className="fas fa-plus" /> Add Product</button>}
      </div>

      <div className="filter-bar">
        <input className="form-control-dark" style={{ maxWidth: 300 }} placeholder="Search products..." value={search} onChange={e => setSearch(e.target.value)} />
      </div>

      <div className="table-wrapper">
        <table className="table-dark-custom">
          <thead>
            <tr>
              <th>Name</th>
              <th>SKU</th>
              <th>Barcode</th>
              <th>Category</th>
              <th>Price</th>
              <th>Cost</th>
              <th>Stock</th>
              <th>Date Added</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {products.map(p => (
              <tr key={p.id}>
                <td style={{ fontWeight: 500 }}>{p.name}</td>
                <td><span className="mono" style={{ fontSize: 12 }}>{p.sku}</span></td>
                <td><span className="mono" style={{ fontSize: 12, color: p.barcode ? 'var(--text-secondary)' : 'var(--text-muted)' }}>{p.barcode || '—'}</span></td>
                <td>{p.category?.name}</td>
                <td><span className="mono">${parseFloat(p.price).toFixed(2)}</span></td>
                <td><span className="mono" style={{ color: 'var(--amber)' }}>${parseFloat(p.cost_price).toFixed(2)}</span></td>
                <td>
                  <span className={`mono ${p.stock_qty <= 10 ? 'text-danger' : ''}`} style={{ fontWeight: p.stock_qty <= 10 ? 600 : 400 }}>
                    {p.stock_qty}
                  </span>
                </td>
                <td>
                  <span style={{ fontSize: 12, color: 'var(--text-muted)', whiteSpace: 'nowrap' }}>
                    {new Date(p.created_at).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' })}
                    <br />
                    <span className="mono" style={{ fontSize: 11 }}>
                      {new Date(p.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  </span>
                </td>
                <td>{p.stock_qty <= 10 ? <span className="badge-low-stock">Low Stock</span> : <span className="badge-ok">In Stock</span>}</td>
                <td>
                  {user?.role === 'admin' && (
                    <div style={{ display: 'flex', gap: 6 }}>
                      <button className="btn-outline-custom" style={{ padding: '4px 8px', fontSize: 12 }} onClick={() => setRestockModal(p)}><i className="fas fa-truck" /></button>
                      <button className="btn-outline-custom" style={{ padding: '4px 8px', fontSize: 12 }} onClick={() => openEdit(p)}><i className="fas fa-edit" /></button>
                      <button className="btn-outline-custom" style={{ padding: '4px 8px', fontSize: 12, color: 'var(--danger)', borderColor: 'var(--danger)' }} onClick={() => setProductToDelete(p)}><i className="fas fa-trash" /></button>
                    </div>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {showModal && (
        <div className="receipt-overlay" onClick={() => { stopScanner(); setShowModal(false) }}>
          <div className="product-modal-panel" onClick={e => e.stopPropagation()}>
            <h5 style={{ marginBottom: 20 }}>{editing ? 'Edit Product' : 'Add Product'}</h5>
            <form onSubmit={handleSave}>
              <div className="mb-3">
                <label className="form-label-dark">Name</label>
                <input className="form-control-dark" value={form.name} onChange={e => setForm({...form, name: e.target.value})} required />
              </div>
              <div className="row g-3 mb-3">
                <div className="col-12 col-sm-6">
                  <label className="form-label-dark">Barcode <span style={{ color: 'var(--text-muted)', fontWeight: 400 }}>(optional)</span></label>
                  <div style={{ display: 'flex', gap: 6 }}>
                    <input
                      id="barcode-input-field"
                      autoFocus
                      className="form-control-dark"
                      style={{ flex: 1 }}
                      value={form.barcode}
                      onChange={e => setForm({...form, barcode: e.target.value})}
                      placeholder="Scan or enter barcode"
                      onKeyDown={e => { if (e.key === 'Enter') e.preventDefault() }}
                      autoComplete="off"
                    />
                    <button
                      type="button"
                      className={scannerActive ? 'btn-accent active' : 'btn-outline-custom'}
                      style={{ padding: '6px 10px', fontSize: 12, flexShrink: 0 }}
                      onClick={() => scannerActive ? stopScanner() : setScannerActive(true)}
                      title="Scan via Camera"
                    >
                      <i className={`fas ${scannerActive ? 'fa-camera-retro' : 'fa-camera'}`} />
                    </button>
                  </div>
                  <div style={{
                    marginTop: 8, border: scannerActive ? '1px solid var(--border-color)' : 'none',
                    borderRadius: scannerActive ? 'var(--radius)' : 0, overflow: 'hidden',
                    background: '#000', position: 'relative',
                    display: scannerActive ? 'block' : 'none'
                  }}>
                    <div id="barcode-scanner-region" style={{ width: '100%' }} />
                    <button
                      type="button"
                      onClick={stopScanner}
                      style={{
                        position: 'absolute', top: 4, right: 4, zIndex: 10,
                        background: 'rgba(0,0,0,0.7)', color: '#fff', border: 'none',
                        borderRadius: '50%', width: 24, height: 24, cursor: 'pointer',
                        display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11
                      }}
                    ><i className="fas fa-times" /></button>
                  </div>
                </div>
                <div className="col-12 col-sm-6">
                  <label className="form-label-dark">SKU</label>
                  {editing ? (
                    <input className="form-control-dark" value={form.sku} onChange={e => setForm({...form, sku: e.target.value})} />
                  ) : (
                    <input className="form-control-dark" value={previewSku} placeholder={form.category_id ? 'Loading...' : 'Select category first'} readOnly style={{ opacity: 0.6, cursor: 'default' }} />
                  )}
                </div>
              </div>
              <div className="mb-3">
                <label className="form-label-dark">Category</label>
                <select className="form-control-dark" value={form.category_id} onChange={e => {
                  setForm({...form, category_id: e.target.value})
                  if (!editing) fetchPreviewSku(e.target.value)
                }} required>
                  <option value="">Select...</option>
                  {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </div>
              <div className="row g-3 mb-4">
                <div className="col-12 col-sm-6">
                  <label className="form-label-dark">Selling Price ($)</label>
                  <input type="number" step="0.01" className="form-control-dark" value={form.price} onChange={e => setForm({...form, price: e.target.value})} required />
                </div>
                {user?.role === 'admin' && (
                  <div className="col-12 col-sm-6">
                    <label className="form-label-dark">Cost Price ($)</label>
                    <input type="number" step="0.01" className="form-control-dark" value={form.cost_price} onChange={e => setForm({...form, cost_price: e.target.value})} required />
                  </div>
                )}
              </div>
              <div className="mb-4">
                <label className="form-label-dark">Stock Qty</label>
                <input type="number" className="form-control-dark" value={form.stock_qty} onChange={e => setForm({...form, stock_qty: e.target.value})} required />
              </div>
              <div className="mb-4">
                <label className="form-label-dark">Image <span style={{ color: 'var(--text-muted)', fontWeight: 400 }}>(optional)</span></label>
                {imagePreview ? (
                  <div className="dropzone-preview">
                    <img src={imagePreview} alt="Preview" />
                    <button type="button" className="dropzone-remove" onClick={() => { setImageFile(null); setImagePreview('') }}>
                      <i className="fas fa-times" />
                    </button>
                  </div>
                ) : (
                  <div
                    className={`dropzone ${dragging ? 'dropzone-active' : ''}`}
                    onDragOver={(e) => { e.preventDefault(); setDragging(true) }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={handleDrop}
                    onClick={() => document.getElementById('product-image-input').click()}
                  >
                    <i className="fas fa-cloud-arrow-up" />
                    <p>Click or drag an image here to upload</p>
                    <small>PNG, JPG up to 2MB</small>
                    <input
                      id="product-image-input"
                      type="file"
                      accept=".png,.jpg,.jpeg"
                      style={{ display: 'none' }}
                      onChange={(e) => { if (e.target.files.length) handleFile(e.target.files[0]); e.target.value = '' }}
                    />
                  </div>
                )}
              </div>
              <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', flexWrap: 'wrap' }}>
                <button type="button" className="btn-outline-custom" onClick={() => { stopScanner(); setShowModal(false) }}>Cancel</button>
                <button type="submit" className="btn-primary-custom">{editing ? 'Update' : 'Create'}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {restockModal && (
        <div className="receipt-overlay" onClick={() => setRestockModal(null)}>
          <div style={{ background: 'var(--bg-panel)', border: '1px solid var(--border-color)', borderRadius: 'var(--radius-lg)', width: '100%', maxWidth: 380, padding: 24 }} onClick={e => e.stopPropagation()}>
            <h5 style={{ marginBottom: 8 }}>Restock: {restockModal.name}</h5>
            <p style={{ fontSize: 13, color: 'var(--text-secondary)', marginBottom: 16 }}>Current stock: <span className="mono">{restockModal.stock_qty}</span></p>
            <form onSubmit={handleRestock}>
              <label className="form-label-dark">Quantity to Add</label>
              <input type="number" min="1" className="form-control-dark mb-3" value={restockQty} onChange={e => setRestockQty(e.target.value)} required autoFocus />
              <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
                <button type="button" className="btn-outline-custom" onClick={() => setRestockModal(null)}>Cancel</button>
                <button type="submit" className="btn-primary-custom"><i className="fas fa-truck" /> Restock</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {productToDelete && (
        <div className="receipt-overlay" onClick={() => setProductToDelete(null)}>
          <div className="product-modal-panel" style={{ maxWidth: 380 }} onClick={e => e.stopPropagation()}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <div style={{ width: 40, height: 40, borderRadius: '50%', background: 'rgba(220, 53, 69, 0.12)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                <i className="fas fa-trash" style={{ color: 'var(--danger)', fontSize: 16 }} />
              </div>
              <h5 style={{ margin: 0 }}>Delete Product</h5>
            </div>
            <p style={{ fontSize: 13, color: 'var(--text-secondary)', margin: '16px 0 0' }}>
              Are you sure you want to delete <strong style={{ color: 'var(--text-primary)' }}>{productToDelete.name}</strong>? This action cannot be undone.
            </p>
            <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
              <button className="btn-outline-custom" onClick={() => setProductToDelete(null)}>Cancel</button>
              <button className="btn-danger-custom" onClick={confirmDelete}><i className="fas fa-trash" /> Delete</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
