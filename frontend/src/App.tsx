import { lazy } from 'react'
import { createBrowserRouter, Navigate, RouterProvider } from 'react-router'
import { RequireAuth, RequireCompany } from '@/auth/guards'
import { AppLayout } from '@/components/layout/AppLayout'
import { LoginPage } from '@/features/auth/LoginPage'
import { NotFoundPage } from '@/features/NotFoundPage'
import { ShipmentsPage } from '@/features/shipments/ShipmentsPage'

const ShipmentDetailPage = lazy(() => import('@/features/shipments/ShipmentDetailPage').then((m) => ({ default: m.ShipmentDetailPage })))
const LookupPage = lazy(() => import('@/features/lookup/LookupPage').then((m) => ({ default: m.LookupPage })))
const ReportsPage = lazy(() => import('@/features/reports/ReportsPage').then((m) => ({ default: m.ReportsPage })))
const ImportsPage = lazy(() => import('@/features/imports/ImportsPage').then((m) => ({ default: m.ImportsPage })))
const UsersPage = lazy(() => import('@/features/admin/UsersPage').then((m) => ({ default: m.UsersPage })))
const ApiClientsPage = lazy(() => import('@/features/admin/ApiClientsPage').then((m) => ({ default: m.ApiClientsPage })))
const CompaniesPage = lazy(() => import('@/features/admin/CompaniesPage').then((m) => ({ default: m.CompaniesPage })))

const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  {
    element: (
      <RequireAuth>
        <AppLayout />
      </RequireAuth>
    ),
    children: [
      { path: 'admin/companies', element: <CompaniesPage /> },
      { path: 'admin/users', element: <UsersPage /> },
      {
        element: <RequireCompany />,
        children: [
          { index: true, element: <Navigate to="/shipments" replace /> },
          { path: 'shipments', element: <ShipmentsPage /> },
          { path: 'shipments/:id', element: <ShipmentDetailPage /> },
          { path: 'lookup', element: <LookupPage /> },
          { path: 'reports', element: <ReportsPage /> },
          { path: 'imports', element: <ImportsPage /> },
          { path: 'api-clients', element: <ApiClientsPage /> },
        ],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
])

export function App() {
  return <RouterProvider router={router} />
}
