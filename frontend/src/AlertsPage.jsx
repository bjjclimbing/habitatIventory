import { useEffect, useState } from "react";
import { api } from "./api";
import { useSearchParams, Link } from "react-router-dom";

export default function AlertsPage() {
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loadingSync, setLoadingSync] = useState(false);

  const [searchParams] = useSearchParams();
  const type = searchParams.get("type");

  // =========================
  // LOAD ALERTS
  // =========================
  useEffect(() => {
    load();
  }, [type]);

  const load = async () => {
    setLoading(true);

    try {
      const res = await api.get(`/alerts/details?type=${type}`);
      setAlerts(res.data || []);
    } catch (e) {
      console.error(e);
    }

    setLoading(false);
  };

  // =========================
  // SYNC VALIJAS
  // =========================
  const handleSync = async () => {
    setLoadingSync(true);

    try {
      await api.post("/valijas/sync");
      await load(); // recarga sin refresh
      alert("Maletas sincronizadas");
    } catch (e) {
      console.error(e);
      alert("Error al sincronizar");
    }

    setLoadingSync(false);
  };

  // =========================
  // TITLE
  // =========================
  const getTitle = () => {
    switch (type) {
      case "valija_critical": return "🔥 Maletas críticas";
      case "valija_low": return "📦 Maletas con bajo stock";
      case "low_stock": return "⚠️ Productos bajo stock";
      case "warning": return "⏳ Próximos a caducar";
      case "expired": return "❌ Productos caducados";
      default: return "Alertas";
    }
  };

  // =========================
  // UI
  // =========================
  return (
    <div>

      {/* HEADER + ACTION */}
      <div className="flex justify-between items-center mb-4">

        <h2 className="text-xl font-bold">
          {getTitle()}
        </h2>

        {/* 🔄 BOTÓN SOLO PARA VALIJAS */}
        {(type === "valija_low" || type === "valija_critical") && (
          <button
            onClick={handleSync}
            disabled={loadingSync}
            className="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700"
          >
            {loadingSync ? "Sincronizando..." : "🔄 Reponer maletas"}
          </button>
        )}

      </div>

      {/* LOADING */}
      {loading && (
        <div className="text-gray-500 mb-4">
          Cargando alertas...
        </div>
      )}

      {/* TABLA */}
      <div className="bg-white rounded-xl shadow overflow-hidden">

        <table className="w-full text-sm">

          <thead className="bg-gray-50 text-gray-600">
            <tr>
              <th className="text-left p-4">Elemento</th>
              <th className="text-center">Info</th>
            </tr>
          </thead>

          <tbody>

            {alerts.length === 0 && !loading && (
              <tr>
                <td colSpan="2" className="p-4 text-center text-gray-500">
                  No hay alertas
                </td>
              </tr>
            )}

            {alerts.map((a, i) => (
              <tr key={i} className="border-t hover:bg-gray-50">

                {/* 🔹 NOMBRE (CORREGIDO) */}
                <td className="p-4">

                  {a.valija ? (
                    <Link
                      to={`/valijas/${a.valija.id}`}
                      className="text-purple-600 hover:underline font-medium"
                    >
                      📦 {a.valija.name}
                    </Link>
                  ) : (
                    <span>
                      {a.product?.name}
                    </span>
                  )}

                </td>

                {/* 🔹 INFO */}
                <td className="text-center">

                  {/* VALIJAS */}
                  {a.current !== undefined && (
                    <span className="font-semibold text-orange-600">
                      {a.current} / {a.min}
                    </span>
                  )}

                  {/* PRODUCTOS */}
                  {a.product?.stock !== undefined && (
                    <span className="font-semibold text-red-600">
                      {a.product.stock} / {a.product.min}
                    </span>
                  )}

                  {/* CADUCIDAD */}
                  {a.batch && (
                    <span className="text-blue-600">
                      {a.batch.expirationDate}
                    </span>
                  )}

                </td>

              </tr>
            ))}

          </tbody>

        </table>

      </div>

    </div>
  );
}