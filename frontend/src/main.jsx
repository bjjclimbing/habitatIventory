import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";

import App from "./App";
import ProductDetail from "./ProductDetail";
import ProviderDetail from "./ProviderDetail";
import Dashboard from "./Dashboard";
import ImportPage from "./ImportPage";
import LoginPage from "./auth/LoginPage";

import Layout from "./Layout";
import { AuthProvider } from "./auth/AuthContext";
import { useAuth } from "./auth/useAuth";
import AlertsPage from "./AlertsPage";
import "./index.css";
import ValijaDetail from "./ValijaDetail";
import ValijasList from "./ValijasList";
import BudgetPage from "./BudgetPage";
import BudgetsList from "./BudgetsList";
import BudgetDetail from "./BudgetDetail";
import UserCreatePage from "./UserCreatePage";
import AdminRoute from "./AdminRoute";
function PrivateLayout({ children }) {
  function AdminRoute({ children }) {
    const { token, user } = useAuth();
  
    if (!token) {
      return <Navigate to="/login" />;
    }
  
    if (!user?.roles?.includes("ROLE_ADMIN")) {
      return <Navigate to="/" />;
    }
  
    return <Layout>{children}</Layout>;
  }
  const { token } = useAuth();

  if (!token) {
    return <Navigate to="/login" />;
  }

  return <Layout>{children}</Layout>;
}

ReactDOM.createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <AuthProvider>
      <BrowserRouter>
        <Routes>

          {/* 🔓 LOGIN (sin layout) */}
          <Route path="/login" element={<LoginPage />} />

          {/* 🔒 TODAS LAS DEMÁS RUTAS */}
          <Route
            path="/"
            element={
              <PrivateLayout>
                <App />
              </PrivateLayout>
            }
          />

          <Route
            path="/products/:id"
            element={
              <PrivateLayout>
                <ProductDetail />
              </PrivateLayout>
            }
          />

          <Route
            path="/providers/:id"
            element={
              <PrivateLayout>
                <ProviderDetail />
              </PrivateLayout>
            }
          />

          <Route
            path="/dashboard"
            element={
              <PrivateLayout>
                <Dashboard />
              </PrivateLayout>
            }
          />

          <Route
            path="/import"
            element={
              <PrivateLayout>
                <ImportPage />
              </PrivateLayout>
            }
          />
          <Route
            path="/valijas"
            element={
              <PrivateLayout>
                <ValijasList />
              </PrivateLayout>
            }
          />
          <Route
            path="/valijas/:id"
            element={
              <PrivateLayout>
                <ValijaDetail />
              </PrivateLayout>
            }
          />
          <Route
            path="/alerts"
            element={
              <PrivateLayout>
                <AlertsPage />
              </PrivateLayout>
            }
          />
          <Route
  path="/budgets"
  element={
    <AdminRoute>
      <BudgetsList />
    </AdminRoute>
  }
/>

<Route
  path="/budgets/new"
  element={
    <AdminRoute>
      <BudgetPage />
    </AdminRoute>
  }
/>

<Route
  path="/budgets/:id"
  element={
    <AdminRoute>
      <BudgetPage />
    </AdminRoute>
  }
/>
<Route
  path="/users/new"
  element={
    <AdminRoute>
      <UserCreatePage />
    </AdminRoute>
  }
/>
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  </React.StrictMode>
);