import { Link, NavLink } from "react-router-dom";
import { useAuth } from "./auth/useAuth";
import { useEffect, useState } from "react";
import { api } from "./api";
export default function Layout({ children }) {
  const { user } = useAuth();
  const isAdmin = user?.roles?.includes("ROLE_ADMIN");
  const { logout } = useAuth();

  // =========================
  // ALERTAS DINÁMICAS
  // =========================
  const [alerts, setAlerts] = useState({
    low_stock: false,
    warning: false,
    expired: false,
    valija_low: false,
    valija_critical: false,
    valija_expiring: false,
    valija_expired: false,
  });

  const loadAlerts = async () => {
    try {
      const types = [
        "low_stock",
        "warning",
        "expired",
        "valija_low",
        "valija_critical",
        "valija_expiring",
        "valija_expired",
      ];

      const results = await Promise.all(
        types.map((type) =>
          api
            .get(`/alerts/details?type=${type}`)
            .then((res) => ({
              type,
              has: (res.data || []).length > 0,
            }))
            .catch(() => ({ type, has: false }))
        )
      );

      const mapped = {};
      results.forEach((r) => {
        mapped[r.type] = r.has;
      });

      setAlerts(mapped);
    } catch (e) {
      console.warn("Error cargando alertas");
    }
  };

  useEffect(() => {
    loadAlerts();

    // 🔁 refresco automático cada 30s (opcional)
    const interval = setInterval(loadAlerts, 30000);
    return () => clearInterval(interval);
  }, []);

  // =========================
  // NAV STYLE
  // =========================
  const navClass = ({ isActive }) =>
    `flex items-center justify-between px-4 py-2 rounded-lg text-sm font-medium transition
     ${isActive
      ? "bg-blue-600 text-white shadow"
      : "text-gray-600 hover:bg-gray-100"
    }`;

  return (
    <div className="flex min-h-screen bg-gray-100">

      {/* =========================
          SIDEBAR
      ========================= */}
      <aside className="w-64 bg-white shadow-md p-4 flex flex-col sticky top-0 h-screen">

        {/* LOGO */}
        <div className="mb-6">
          <h1 className="text-lg font-bold text-gray-800">
            📦 Inventory
          </h1>
          <p className="text-xs text-gray-500">
            Gestión de stock
          </p>
        </div>

        {/* NAV PRINCIPAL */}
        <nav className="flex flex-col gap-2">

          <NavLink to="/" className={navClass}>
            <span>📦 Productos</span>
          </NavLink>

          <NavLink to="/valijas" className={navClass}>
            <span>🧳 Maletas</span>
          </NavLink>
          {isAdmin && (
            <NavLink to="/budgets" className={navClass}>
              <span>🧾 Presupuestos</span>
            </NavLink>
          )}
          {isAdmin && (
            <NavLink to="/budgets/new" className={navClass}>
              <span>➕ Nuevo presupuesto</span>
            </NavLink>
          )}

          <NavLink to="/dashboard" className={navClass}>
            <span>📊 Dashboard</span>
          </NavLink>

          {isAdmin && (
            <NavLink to="/users/new" className={navClass}>
              <span>👤 Usuarios</span>
            </NavLink>

          )}
          {isAdmin && (
            <NavLink to="/clients" className={navClass}>
              <span>🏢 Clientes</span>
            </NavLink>
          )}
        </nav>

        {/* =========================
            ALERTAS DINÁMICAS
        ========================= */}
        <div className="mt-6">

          <div className="text-xs text-gray-400 mb-2 px-2">
            ALERTAS
          </div>

          <div className="flex flex-col gap-1">

            

            {alerts.low_stock && (
              <Link
                to="/alerts?type=low_stock"
                className="px-3 py-2 rounded-lg text-sm bg-red-50 text-red-700 font-medium"
              >
                🔴 Bajo stock
              </Link>
            )}

            

            {alerts.warning && (
              <Link
                to="/alerts?type=warning"
                className="px-3 py-2 rounded-lg text-sm bg-blue-50 text-blue-700 font-medium"
              >
                ⏳ Próx. caducar
              </Link>
            )}

            {alerts.expired && (
              <Link
                to="/alerts?type=expired"
                className="px-3 py-2 rounded-lg text-sm bg-gray-100 text-gray-700 font-medium"
              >
                ⏳ Productos expirados
              </Link>
            )}
            {alerts.valija_critical && (
              <Link
                to="/alerts?type=valija_critical"
                className="px-3 py-2 rounded-lg text-sm bg-purple-50 text-purple-700 font-medium"
              >
                🔥 Maletas sin stock 
              </Link>
            )}
            {alerts.valija_low && (
              <Link
                to="/alerts?type=valija_low"
                className="px-3 py-2 rounded-lg text-sm bg-orange-50 text-orange-700 font-medium"
              >
                📦 Maletas bajo stock
              </Link>
            )}
            {alerts.valija_expired && (
              <Link
                to="/alerts?type=valija_expired"
                className="px-3 py-2 rounded-lg text-sm bg-red-100 text-red-800 font-medium"
              >
                ☠️ Maletas con productos expirados
              </Link>
            )}

            {alerts.valija_expiring && (
              <Link
                to="/alerts?type=valija_expiring"
                className="px-3 py-2 rounded-lg text-sm bg-yellow-50 text-yellow-700 font-medium"
              >
                ⏳ Maletas con productos próximos a expirar
              </Link>
            )}

            {/* SIN ALERTAS */}
            {!Object.values(alerts).some((v) => v) && (
              <div className="px-3 py-2 text-sm text-gray-400">
                ✔ Sin alertas
              </div>
            )}

          </div>
        </div>

        {/* =========================
            ACTIONS
        ========================= */}
        <div className="mt-auto pt-6 border-t">

          <Link
            to="/import"
            className="block bg-green-600 text-white text-center py-2 rounded-lg text-sm hover:bg-green-700 mb-3"
          >
            Import CSV
          </Link>

          <button
            onClick={logout}
            className="w-full bg-red-500 text-white py-2 rounded-lg text-sm hover:bg-red-600"
          >
            Logout
          </button>

        </div>

      </aside>

      {/* =========================
          MAIN CONTENT
      ========================= */}
      {/* 🔥 SIN overflow para infinite scroll */}
      <main className="flex-1 p-6">

        {/* TOP BAR */}
        <div className="flex justify-end items-center mb-6 text-sm text-gray-600">
          👤  {user?.username || "Usuario"}
        </div>

        {children}

      </main>

    </div>
  );
}