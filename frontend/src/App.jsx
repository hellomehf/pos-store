import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from './contexts/AuthContext'
import Layout from './layouts/Layout'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import POS from './pages/POS'
import Products from './pages/Products'
import Categories from './pages/Categories'
import SalesHistory from './pages/SalesHistory'
import Reports from './pages/Reports'
import Users from './pages/Users'
import Settings from './pages/Settings'

function PrivateRoute({ children }) {
  const { user, loading } = useAuth()
  if (loading) return <div className="d-flex align-items-center justify-content-center" style={{ height: '100vh', color: 'var(--text-muted)' }}><i className="fas fa-spinner fa-spin fa-2x" /></div>
  return user ? children : <Navigate to="/login" />
}

function AdminRoute({ children }) {
  const { user, loading } = useAuth()
  if (loading) return null
  return user?.role === 'admin' ? children : <Navigate to="/" />
}

export default function App() {
  const { user, loading } = useAuth()

  if (loading) return <div className="d-flex align-items-center justify-content-center" style={{ height: '100vh', color: 'var(--text-muted)' }}><i className="fas fa-spinner fa-spin fa-2x" /></div>

  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate to="/" /> : <Login />} />
      <Route path="/" element={<PrivateRoute><Layout /></PrivateRoute>}>
        <Route index element={<Dashboard />} />
        <Route path="pos" element={<POS />} />
        <Route path="products" element={<AdminRoute><Products /></AdminRoute>} />
        <Route path="categories" element={<AdminRoute><Categories /></AdminRoute>} />
        <Route path="sales" element={<SalesHistory />} />
        <Route path="reports" element={<AdminRoute><Reports /></AdminRoute>} />
        <Route path="users" element={<AdminRoute><Users /></AdminRoute>} />
        <Route path="settings" element={<AdminRoute><Settings /></AdminRoute>} />
      </Route>
      <Route path="*" element={<Navigate to="/" />} />
    </Routes>
  )
}
