import { useState } from 'react'
import { useAuth } from '../contexts/AuthContext'
import { useToast } from '../contexts/ToastContext'

export default function Login() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const { login } = useAuth()
  const toast = useToast()

  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      await login(email, password)
      toast.success('Welcome back!')
    } catch (err) {
      toast.error(err.response?.data?.message || 'Invalid credentials')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{
      minHeight: '100vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: 'linear-gradient(135deg, #3271a8, #7732a8)',
      padding: 20,
    }}>
      <div style={{
        width: '100%',
        maxWidth: 400,
        background: 'var(--bg-panel)',
        border: '1px solid var(--border-color)',
        borderRadius: 'var(--radius-lg)',
        padding: '40px 32px',
      }}>
        <div style={{ textAlign: 'center', marginBottom: 32 }}>
          <div style={{
            width: 56,
            height: 56,
            borderRadius: 12,
            background: 'linear-gradient(135deg, var(--primary), var(--secondary))',
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontFamily: 'var(--font-heading)',
            fontWeight: 700,
            fontSize: 20,
            color: '#fff',
            marginBottom: 16,
          }}>PS</div>
          <h4 style={{ margin: 0, fontFamily: 'var(--font-heading)' }}>POS System</h4>
          <p style={{ color: 'var(--text-muted)', fontSize: 13, marginTop: 4 }}>Sign in to your account</p>
        </div>

        <form onSubmit={handleSubmit}>
          <div style={{ marginBottom: 16 }}>
            <label className="form-label-dark">Email</label>
            <input
              type="email"
              className="form-control-dark"
              value={email}
              onChange={e => setEmail(e.target.value)}
              placeholder="Enter your email"
              required
              autoFocus
            />
          </div>
          <div style={{ marginBottom: 24 }}>
            <label className="form-label-dark">Password</label>
            <input
              type="password"
              className="form-control-dark"
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="Enter your password"
              required
            />
          </div>
          <button
            type="submit"
            className="btn-primary-custom"
            disabled={loading}
            style={{ width: '100%', justifyContent: 'center', padding: '10px 0' }}
          >
            {loading ? <><i className="fas fa-spinner fa-spin" /> Signing in...</> : <><i className="fas fa-sign-in-alt" /> Sign In</>}
          </button>
        </form>
      </div>
    </div>
  )
}
