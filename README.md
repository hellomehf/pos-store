# POS System

Full-stack Point of Sale system built with **Laravel 13** (REST API) + **React 19** (Vite SPA).

## Tech Stack

- **Backend:** Laravel 13, Sanctum API auth, MySQL 8
- **Frontend:** React 19, Vite, React Router, Axios, Bootstrap 5, Chart.js
- **Design:** Dark-first UI with custom CSS (Space Grotesk / Inter / IBM Plex Mono)

---

## Prerequisites

- PHP 8.2+
- Composer 2.x
- Node.js 18+
- MySQL 8.x
- npm or yarn

---

## Setup Steps

### 1. Create Database

```sql
CREATE DATABASE pos_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend Setup

```bash
cd backend
composer install
cp .env.example .env          # Already pre-configured for MySQL
php artisan key:generate
php artisan migrate --force
php artisan db:seed
php artisan serve --port=8000
```

The API will run at `http://localhost:8000`

### 3. Frontend Setup

```bash
cd frontend
npm install
npm run dev
```

The frontend will run at `http://localhost:5173`

---

## Default Login Credentials

| Role    | Email              | Password |
|---------|--------------------|----------|
| Admin   | admin@pos.test     | password |
| Cashier | cashier@pos.test   | password |

---

## Features

- **Authentication** - Sanctum token-based login/logout
- **Dashboard** - Sales summary, daily revenue, low-stock alerts, charts
- **POS Screen** - Category tabs, product grid, cart panel, cash keypad, receipt modal
- **Products** - CRUD with SKU, price, stock, category, search
- **Categories** - CRUD with product counts
- **Sales History** - Filterable by date/cashier, receipt view
- **Reports** - Daily revenue bar chart, payment breakdown pie chart, top products
- **Users** - Admin/cashier role management (admin only)

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Authenticate user |
| POST | `/api/logout` | Revoke token |
| GET | `/api/me` | Current user |
| GET | `/api/dashboard` | Dashboard stats |
| GET/POST | `/api/categories` | List/Create categories |
| GET/POST | `/api/products` | List/Create products |
| POST | `/api/products/{id}/restock` | Restock product |
| GET/POST | `/api/sales` | List/Create sales |
| GET | `/api/sales/{id}` | Sale details |
| GET | `/api/reports` | Sales reports |
| GET | `/api/users` | List users (admin) |
| POST | `/api/users` | Create user (admin) |
| PUT | `/api/users/{id}` | Update user (admin) |
| DELETE | `/api/users/{id}` | Delete user (admin) |

---

## Project Structure

```
/backend                  Laravel API
  /app/Http/Controllers   API controllers
  /app/Models             Eloquent models
  /database/migrations    Database schema
  /database/seeders       Sample data
  /routes/api.php         API routes

/frontend                 React SPA
  /src/api                Axios instance
  /src/contexts           Auth, Cart, Toast contexts
  /src/layouts            Sidebar layout
  /src/pages              All screens
  /src/styles             Dark theme CSS
```
