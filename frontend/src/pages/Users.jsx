import { useState, useEffect } from 'react'
import api from '../api/api'
import { useToast } from '../contexts/ToastContext'

export default function Users() {
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '', role: 'cashier' })
  const [userToDelete, setUserToDelete] = useState(null)
  const toast = useToast()

  const load = () => {
    api.get('/users').then(res => setUsers(res.data)).finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const openAdd = () => { setEditing(null); setForm({ name: '', email: '', password: '', password_confirmation: '', role: 'cashier' }); setShowModal(true) }
  const openEdit = (u) => { setEditing(u); setForm({ name: u.name, email: u.email, password: '', password_confirmation: '', role: u.role }); setShowModal(true) }

  const handleSave = async (e) => {
    e.preventDefault()
    try {
      if (editing) {
        const payload = { ...form }
        if (!payload.password) delete payload.password
        delete payload.password_confirmation
        await api.put(`/users/${editing.id}`, payload)
        toast.success('User updated')
      } else {
        await api.post('/users', form)
        toast.success('User created')
      }
      setShowModal(false)
      load()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to save user')
    }
  }

  const confirmDelete = async () => {
    if (!userToDelete || userToDelete.sales_count > 0) return
    try {
      await api.delete(`/users/${userToDelete.id}`)
      toast.success('User deleted')
      setUserToDelete(null)
      load()
    } catch (err) {
      toast.error(err.response?.data?.message || 'Failed to delete')
    }
  }

  if (loading) return <div className="d-flex justify-content-center py-5"><i className="fas fa-spinner fa-spin fa-2x" style={{ color: 'var(--text-muted)' }} /></div>

  return (
    <div>
      <div className="page-header">
        <h4>Users</h4>
        <button className="btn-primary-custom" onClick={openAdd}><i className="fas fa-plus" /> Add User</button>
      </div>

      <div className="table-wrapper">
        <table className="table-dark-custom">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {users.map(u => (
              <tr key={u.id}>
                <td style={{ fontWeight: 500 }}>{u.name}</td>
                <td style={{ color: 'var(--text-secondary)' }}>{u.email}</td>
                <td>
                  <span className={u.role === 'admin' ? 'badge-admin' : 'badge-cashier'}>{u.role}</span>
                </td>
                <td style={{ fontSize: 13 }}>{new Date(u.created_at).toLocaleDateString()}</td>
                <td>
                  <div style={{ display: 'flex', gap: 6 }}>
                    <button className="btn-outline-custom" style={{ padding: '4px 8px', fontSize: 12 }} onClick={() => openEdit(u)}><i className="fas fa-edit" /></button>
                    <button className="btn-outline-custom" style={{ padding: '4px 8px', fontSize: 12, color: 'var(--danger)', borderColor: 'var(--danger)' }} onClick={() => setUserToDelete(u)}><i className="fas fa-trash" /></button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {showModal && (
        <div className="receipt-overlay" onClick={() => setShowModal(false)}>
          <div style={{ background: 'var(--bg-panel)', border: '1px solid var(--border-color)', borderRadius: 'var(--radius-lg)', width: '100%', maxWidth: 440, padding: 24 }} onClick={e => e.stopPropagation()}>
            <h5 style={{ marginBottom: 20 }}>{editing ? 'Edit User' : 'Add User'}</h5>
            <form onSubmit={handleSave}>
              <div className="mb-3">
                <label className="form-label-dark">Name</label>
                <input className="form-control-dark" value={form.name} onChange={e => setForm({...form, name: e.target.value})} required />
              </div>
              <div className="mb-3">
                <label className="form-label-dark">Email</label>
                <input type="email" className="form-control-dark" value={form.email} onChange={e => setForm({...form, email: e.target.value})} required />
              </div>
              <div className="mb-3">
                <label className="form-label-dark">Role</label>
                <select className="form-control-dark" value={form.role} onChange={e => setForm({...form, role: e.target.value})}>
                  <option value="cashier">Cashier</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div className="mb-3">
                <label className="form-label-dark">Password {editing && '(leave blank to keep current)'}</label>
                <input type="password" className="form-control-dark" value={form.password} onChange={e => setForm({...form, password: e.target.value})} {...(!editing ? { required: true } : {})} />
              </div>
              {form.password && (
                <div className="mb-4">
                  <label className="form-label-dark">Confirm Password</label>
                  <input type="password" className="form-control-dark" value={form.password_confirmation} onChange={e => setForm({...form, password_confirmation: e.target.value})} />
                </div>
              )}
              <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: form.password ? 0 : 16 }}>
                <button type="button" className="btn-outline-custom" onClick={() => setShowModal(false)}>Cancel</button>
                <button type="submit" className="btn-primary-custom">{editing ? 'Update' : 'Create'}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {userToDelete && (
        <div className="receipt-overlay" onClick={() => setUserToDelete(null)}>
          <div className="product-modal-panel" style={{ maxWidth: 380 }} onClick={e => e.stopPropagation()}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <div style={{ width: 40, height: 40, borderRadius: '50%', background: 'rgba(220, 53, 69, 0.12)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                <i className={`fas ${userToDelete.sales_count > 0 ? 'fa-lock' : 'fa-trash'}`} style={{ color: 'var(--danger)', fontSize: 16 }} />
              </div>
              <h5 style={{ margin: 0 }}>Delete User</h5>
            </div>
            {userToDelete.sales_count > 0 ? (
              <div style={{ marginTop: 16 }}>
                <div style={{ padding: '10px 14px', background: 'rgba(220, 53, 69, 0.08)', border: '1px solid rgba(220, 53, 69, 0.2)', borderRadius: 'var(--radius)', fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.5 }}>
                  <i className="fas fa-exclamation-triangle" style={{ color: 'var(--danger)', marginRight: 8 }} />
                  Cannot delete user account. This user is linked to existing sales transactions. Please deactivate the account instead.
                </div>
              </div>
            ) : (
              <p style={{ fontSize: 13, color: 'var(--text-secondary)', margin: '16px 0 0' }}>
                Are you sure you want to delete <strong style={{ color: 'var(--text-primary)' }}>{userToDelete.name}</strong>? This action cannot be undone.
              </p>
            )}
            <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
              <button className="btn-outline-custom" onClick={() => setUserToDelete(null)}>Cancel</button>
              <button className="btn-danger-custom" disabled={userToDelete.sales_count > 0} onClick={confirmDelete} style={userToDelete.sales_count > 0 ? { opacity: 0.4, cursor: 'not-allowed' } : {}}>
                <i className="fas fa-trash" /> Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
