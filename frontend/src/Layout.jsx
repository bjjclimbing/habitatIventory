import { Link, useNavigate } from "react-router-dom";
import { useEffect, useState } from "react";
import { api } from "./api";
import { useAuth } from "./auth/useAuth";

export default function Layout({ children }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const [alerts, setAlerts] = useState({
    valija_critical: 0,
    valija_low: 0,
    low_stock: 0,
    warning: 0,
    expired: 0
  });

  const [loadingAlerts, setLoadingAlerts] = useState(true);

  // =========================
  // LOAD ALERTS (polling)
  // =========================
  useEffect(() => {
    loadAlerts();

    const interval = setInterval(loadAlerts, 30000); // cada 30s

    return () => clearInterval(interval);
  }, []);

  const loadAlerts = async () => {
    try {
      const res = await api.get("/alerts");
      setAlerts(res.data);
    } catch (e) {
      console.error("Error cargando alertas", e);
    } finally {
      setLoadingAlerts(false);
    }
  };

  // =========================
  // UI
  // =========================
  return (
    <div className="min-h-screen bg-gray-100">

      {/* HEADER */}
      <div className="bg-white shadow-sm border-b">
        <div className="max-w-6xl mx-auto px-6 py-4 flex justify-between items-center">

          {/* LOGO */}
          <div>
            <h1 className="text-2xl font-bold text-gray-800">
              📦 Inventory Dashboard
            </h1>
            <p className="text-sm text-gray-500">
              Gestión de productos y proveedores
            </p>
          </div>

          {/* NAV + ALERTS */}
          <div className="flex items-center gap-3">

            {/* NAV */}
            <Link
              to="/"
              className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700"
            >
              Productos
            </Link>

            <Link
              to="/dashboard"
              className="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700"
            >
              Dashboard
            </Link>

            {user?.roles?.includes("ROLE_ADMIN") && (
              <Link
                to="/import"
                className="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700"
              >
                Import CSV
              </Link>
            )}

            {/* ALERTS */}
            <div className="flex gap-2 ml-4 text-xs">

              {loadingAlerts && (
                <span className="text-gray-400">...</span>
              )}

              {!loadingAlerts && (
                <>
                  {alerts?.valija_critical > 0 && (
                    <span
                      title="Valijas sin stock y sin disponibilidad en inventario"
                      onClick={() => navigate("/alerts?type=valija_critical")}
                      className="bg-red-500 text-white px-2 py-1 rounded cursor-pointer"
                    >
                      🔥 {alerts.valija_critical}
                    </span>
                  )}
                    {/* 🔥 VALIJA LOW */}
{alerts?.valija_low > 0 && (
  <span
    title="Valijas por debajo del stock mínimo"
    onClick={() => navigate("/alerts?type=valija_low")}
    className="bg-orange-500 text-white px-2 py-1 rounded cursor-pointer"
  >
    📦 {alerts.valija_low}
  </span>
)}
                  {alerts?.low_stock > 0 && (
                    <span
                      title="Productos por debajo del stock mínimo"
                      onClick={() => navigate("/alerts?type=low_stock")}
                      className="bg-yellow-500 text-white px-2 py-1 rounded cursor-pointer"
                    >
                      ⚠️ {alerts.low_stock}
                    </span>
                  )}

                  {alerts?.warning > 0 && (
                    <span
                      title="Productos próximos a caducar (<7 días)"
                      onClick={() => navigate("/alerts?type=warning")}
                      className="bg-blue-500 text-white px-2 py-1 rounded cursor-pointer"
                    >
                      ⏳ {alerts.warning}
                    </span>
                  )}

                  {alerts?.expired > 0 && (
                    <span
                      title="Productos caducados"
                      onClick={() => navigate("/alerts?type=expired")}
                      className="bg-gray-800 text-white px-2 py-1 rounded cursor-pointer"
                    >
                      ❌ {alerts.expired}
                    </span>
                  )}
                </>
              )}

            </div>

            {/* USER */}
            {user?.username && (
              <div className="text-sm text-gray-600 ml-3">
                👤 {user.username}
              </div>
            )}

            {/* LOGOUT */}
            <button
              onClick={logout}
              className="bg-red-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-600"
            >
              Logout
            </button>

          </div>

        </div>

        {/* LEYENDA */}
        <div className="max-w-6xl mx-auto px-6 pb-2 flex gap-4 text-xs text-gray-500">

          <div className="flex items-center gap-1">
            <span className="w-3 h-3 bg-red-500 rounded"></span>
            Valija crítica
          </div>

          <div className="flex items-center gap-1">
            <span className="w-3 h-3 bg-yellow-500 rounded"></span>
            Bajo stock
          </div>

          <div className="flex items-center gap-1">
            <span className="w-3 h-3 bg-blue-500 rounded"></span>
            Próximo a caducar
          </div>

          <div className="flex items-center gap-1">
            <span className="w-3 h-3 bg-gray-800 rounded"></span>
            Caducados
          </div>

        </div>

      </div>

      {/* CONTENT */}
      <div className="max-w-6xl mx-auto p-6">
        {children}
      </div>

    </div>
  );
}