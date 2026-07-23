import { useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { useStore } from '../contexts/StoreContext'

const navItems = [
  { to: '/', icon: 'fa-chart-line', label: 'Dashboard' },
  { to: '/pos', icon: 'fa-cash-register', label: 'POS' },
  { to: '/products', icon: 'fa-boxes-stacked', label: 'Products', adminOnly: true },
  { to: '/categories', icon: 'fa-tags', label: 'Categories', adminOnly: true },
  { to: '/sales', icon: 'fa-receipt', label: 'Sales History' },
  { to: '/reports', icon: 'fa-chart-pie', label: 'Reports', adminOnly: true },
  { to: '/users', icon: 'fa-users', label: 'Users', adminOnly: true },
  { to: '/settings', icon: 'fa-cog', label: 'Settings', adminOnly: true },
]

export default function Layout() {
  const { user, logout } = useAuth()
  const { storeName, storeLogo } = useStore()
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const location = useLocation()

  const isPOS = location.pathname === '/pos'

  return (
    <div className="app-layout">
      <div className={`sidebar-overlay ${sidebarOpen ? 'open' : ''}`} onClick={() => setSidebarOpen(false)} />

      <aside className={`sidebar ${sidebarOpen ? 'open' : ''}`}>
        <div className="sidebar-brand">
          <div className="sidebar-brand-logo">
            {storeLogo ? (
              <img src={storeLogo} alt="Store Logo" className="sidebar-brand-logo-img" />
            ) : (
              <span style={{ fontFamily: 'var(--font-heading)', fontWeight: 700, fontSize: 16, color: '#fff', width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'linear-gradient(135deg, var(--primary), var(--secondary))', borderRadius: 8 }}>PS</span>
            )}
          </div>
          <div className="sidebar-brand-text">
            <h5>{storeName}</h5>
            <small>Register #1</small>
          </div>
        </div>

        <nav className="sidebar-nav">
          {navItems
            .filter(item => !item.adminOnly || user?.role === 'admin')
            .map(item => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                className={({ isActive }) => `sidebar-nav-item ${isActive ? 'active' : ''}`}
                onClick={() => setSidebarOpen(false)}
              >
                <i className={`fas ${item.icon}`} />
                {item.label}
              </NavLink>
            ))
          }
        </nav>

        <div className="sidebar-footer">
          <button className="sidebar-nav-item" onClick={logout} style={{ color: 'var(--danger)' }}>
            <i className="fas fa-sign-out-alt" />
            Logout
          </button>
        </div>
      </aside>

      <main className="main-content">
        {!isPOS && (
          <div className="top-bar">
            <div className="top-bar-left">
              <button className="mobile-toggle" onClick={() => setSidebarOpen(true)}>
                <i className="fas fa-bars" />
              </button>
              <h5 style={{ margin: 0, fontSize: 16 }}>
                {navItems.find(n => n.to === location.pathname)?.label || ''}
              </h5>
            </div>
            <div className="top-bar-right">
              <span style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                {user?.name}
              </span>
              <span className={user?.role === 'admin' ? 'badge-admin' : 'badge-cashier'}>
                {user?.role}
              </span>
            </div>
          </div>
        )}

        <div className={isPOS ? 'pos-page-wrapper' : 'page-content'}>
          <Outlet />
        </div>
      </main>
    </div>
  )
}
