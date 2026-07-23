import { useState, useEffect } from 'react'
import api from '../api/api'
import { useAuth } from '../contexts/AuthContext'
import { useToast } from '../contexts/ToastContext'

export default function Categories() {
  const { user } = useAuth()
  const [categories, setCategories] = useState([])
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState({ name: '', description: '' })
  const [categoryToDelete, setCategoryToDelete] = useState(null)
  const toast = useToast()

  const load = () => {
    api.get('/categories').then(res => setCategories(res.data)).finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const openAdd = () => { setEditing(null); setForm({ name: '', description: '' }); setShowModal(true) }
  const openEdit = (c) => { setEditing(c); setForm({ name: c.name, description: c.description || '' }); setShowModal(true) }

  const handleSave = async (e) => {
    e.preventDefault()
    try {
      if (editing) {
        await api.put(`/categories/${editing.id}`, form)
        toast.success('Category updated')
      } else {
        await api.post('/categories', form)
        toast.success('Category created')
      }
      setShowModal(false)
      load()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to save')
    }
  }

  const confirmDelete = async () => {
    if (!categoryToDelete || categoryToDelete.products_count > 0) return
    try {
      await api.delete(`/categories/${categoryToDelete.id}`)
      toast.success('Category deleted')
      setCategoryToDelete(null)
      load()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to delete')
    }
  }

  if (loading) return <div className="d-flex justify-content-center py-5"><i className="fas fa-spinner fa-spin fa-2x" style={{ color: 'var(--text-muted)' }} /></div>

  return (
    <div>
      <div className="page-header">
        <h4>Categories</h4>
        {user?.role === 'admin' && <button className="btn-primary-custom" onClick={openAdd}><i className="fas fa-plus" /> Add Category</button>}
      </div>

      <div className="table-wrapper">
        <table className="table-dark-custom">
          <thead>
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>Products</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {categories.map(c => (
              <tr key={c.id}>
                <td style={{ fontWeight: 500 }}>{c.name}</td>
                <td style={{ color: 'var(--text-secondary)' }}>{c.description || '—'}</td>
                <td><span className="mono">{c.products_count}</span></td>
                <td>
                  {user?.role === 'admin' && (
                    <div style={{ display: 'flex', gap: 6 }}>
                      <button className="btn-outline-custom" style={{ padding: '4px 8px', fontSize: 12 }} onClick={() => openEdit(c)}><i className="fas fa-edit" /></button>
                      <button className="btn-outline-custom" style={{ padding: '4px 8px', fontSize: 12, color: 'var(--danger)', borderColor: 'var(--danger)' }} onClick={() => setCategoryToDelete(c)}><i className="fas fa-trash" /></button>
                    </div>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {showModal && (
        <div className="receipt-overlay" onClick={() => setShowModal(false)}>
          <div style={{ background: 'var(--bg-panel)', border: '1px solid var(--border-color)', borderRadius: 'var(--radius-lg)', width: '100%', maxWidth: 440, padding: 24 }} onClick={e => e.stopPropagation()}>
            <h5 style={{ marginBottom: 20 }}>{editing ? 'Edit Category' : 'Add Category'}</h5>
            <form onSubmit={handleSave}>
              <div className="mb-3">
                <label className="form-label-dark">Name</label>
                <input className="form-control-dark" value={form.name} onChange={e => setForm({...form, name: e.target.value})} required autoFocus />
              </div>
              <div className="mb-4">
                <label className="form-label-dark">Description</label>
                <input className="form-control-dark" value={form.description} onChange={e => setForm({...form, description: e.target.value})} />
              </div>
              <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
                <button type="button" className="btn-outline-custom" onClick={() => setShowModal(false)}>Cancel</button>
                <button type="submit" className="btn-primary-custom">{editing ? 'Update' : 'Create'}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {categoryToDelete && (
        <div className="receipt-overlay" onClick={() => setCategoryToDelete(null)}>
          <div className="product-modal-panel" style={{ maxWidth: 380 }} onClick={e => e.stopPropagation()}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <div style={{ width: 40, height: 40, borderRadius: '50%', background: 'rgba(220, 53, 69, 0.12)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                <i className={`fas ${categoryToDelete.products_count > 0 ? 'fa-lock' : 'fa-trash'}`} style={{ color: 'var(--danger)', fontSize: 16 }} />
              </div>
              <h5 style={{ margin: 0 }}>Delete Category</h5>
            </div>
            {categoryToDelete.products_count > 0 ? (
              <div style={{ marginTop: 16 }}>
                <div style={{ padding: '10px 14px', background: 'rgba(220, 53, 69, 0.08)', border: '1px solid rgba(220, 53, 69, 0.2)', borderRadius: 'var(--radius)', fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.5 }}>
                  <i className="fas fa-exclamation-triangle" style={{ color: 'var(--danger)', marginRight: 8 }} />
                  Cannot delete category. There are still <strong style={{ color: 'var(--danger)' }}>{categoryToDelete.products_count}</strong> products assigned to this category. Please reassign or delete the products first.
                </div>
              </div>
            ) : (
              <p style={{ fontSize: 13, color: 'var(--text-secondary)', margin: '16px 0 0' }}>
                Are you sure you want to delete <strong style={{ color: 'var(--text-primary)' }}>{categoryToDelete.name}</strong>? This action cannot be undone.
              </p>
            )}
            <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
              <button className="btn-outline-custom" onClick={() => setCategoryToDelete(null)}>Cancel</button>
              <button className="btn-danger-custom" disabled={categoryToDelete.products_count > 0} onClick={confirmDelete} style={categoryToDelete.products_count > 0 ? { opacity: 0.4, cursor: 'not-allowed' } : {}}>
                <i className="fas fa-trash" /> Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
