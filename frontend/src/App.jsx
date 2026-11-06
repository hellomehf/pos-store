import { lazy } from 'react'
import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from './contexts/AuthContext'
import Layout from './layouts/Layout'
import Login from './pages/Login'

// Route pages are loaded on demand so heavy dependencies (chart.js,
// html5-qrcode, qrcode, ...) only download when their page is opened.
const Dashboard = lazy(() => import('./pages/Dashboard'))
const POS = lazy(() => import('./pages/POS'))
const Products = lazy(() => import('./pages/Products'))
const Categories = lazy(() => import('./pages/Categories'))
const SalesHistory = lazy(() => import('./pages/SalesHistory'))
const Reports = lazy(() => import('./pages/Reports'))
const Users = lazy(() => import('./pages/Users'))
const Settings = lazy(() => import('./pages/Settings'))
const StockReports = lazy(() => import('./pages/StockReports'))

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

function AdminOrStockerRoute({ children }) {
  const { user, loading } = useAuth()
  if (loading) return null
  return user?.role === 'admin' || user?.role === 'stocker' ? children : <Navigate to="/" />
}

function AdminOrCashierRoute({ children }) {
  const { user, loading } = useAuth()
  if (loading) return null
  if (user?.role === 'admin' || user?.role === 'cashier') return children
  if (user?.role === 'stocker') return <Navigate to="/products" />
  return <Navigate to="/" />
}

function IndexRoute() {
  const { user, loading } = useAuth()
  if (loading) return null
  if (user?.role === 'stocker') return <Navigate to="/products" />
  return <Dashboard />
}

export default function App() {
  const { user, loading } = useAuth()

  if (loading) return <div className="d-flex align-items-center justify-content-center" style={{ height: '100vh', color: 'var(--text-muted)' }}><i className="fas fa-spinner fa-spin fa-2x" /></div>

  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate to="/" /> : <Login />} />
      <Route path="/" element={<PrivateRoute><Layout /></PrivateRoute>}>
        <Route index element={<IndexRoute />} />
        <Route path="pos" element={<AdminOrCashierRoute><POS /></AdminOrCashierRoute>} />
        <Route path="products" element={<AdminOrStockerRoute><Products /></AdminOrStockerRoute>} />
        <Route path="categories" element={<AdminOrStockerRoute><Categories /></AdminOrStockerRoute>} />
        <Route path="sales" element={<AdminOrCashierRoute><SalesHistory /></AdminOrCashierRoute>} />
        <Route path="reports" element={<AdminRoute><Reports /></AdminRoute>} />
        <Route path="stock-reports" element={<AdminOrStockerRoute><StockReports /></AdminOrStockerRoute>} />
        <Route path="users" element={<AdminRoute><Users /></AdminRoute>} />
        <Route path="settings" element={<AdminRoute><Settings /></AdminRoute>} />
      </Route>
      <Route path="*" element={<Navigate to="/" />} />
    </Routes>
  )
}
