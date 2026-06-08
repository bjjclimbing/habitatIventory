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
      await load();
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
      case "valija_critical":
        return "🔥 Maletas críticas";

      case "valija_low":
        return "📦 Maletas con bajo stock";

      case "valija_expiring":
        return "⏳ Productos próximos a caducar en maletas";

      case "valija_expired":
        return "☠️ Productos caducados en maletas";

      case "low_stock":
        return "⚠️ Productos bajo stock";

      case "warning":
        return "⏳ Próximos a caducar";

      case "expired":
        return "❌ Productos caducados";

      default:
        return "Alertas";
    }
  };

  // =========================
  // UI
  // =========================
  return (
    <div>

      {/* HEADER */}
      <div className="flex justify-between items-center mb-4">

        <h2 className="text-xl font-bold">
          {getTitle()}
        </h2>

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
              <th className="text-left p-4">SKU</th>
              <th className="text-center">Info</th>
            </tr>
          </thead>

          <tbody>

            {alerts.length === 0 && !loading && (
              <tr>
                <td colSpan="3" className="p-4 text-center text-gray-500">
                  No hay alertas
                </td>
              </tr>
            )}

            {alerts.map((a, i) => (
              <tr key={i} className="border-t hover:bg-gray-50">

                {/* ELEMENTO */}
                <td className="p-4">

                  {a.valija && (
                    <div className="mb-1">
                      <Link
                        to={`/valijas/${a.valija.id}`}
                        className="text-purple-600 hover:underline font-medium"
                      >
                        📦 {a.valija.name}
                      </Link>
                    </div>
                  )}

                  {a.product && (
                    <div>

                      <Link
                        to={`/products/${a.product.id}`}
                        className="text-blue-600 hover:underline font-medium"
                      >
                        {a.product.name}
                      </Link>



                    </div>
                  )}

                </td>
                <td className="p-4 font-mono text-sm text-gray-600">
                  {a.product?.sku || "-"}
                </td>
                {/* INFO */}
                <td className="text-center">

                  {/* VALIJAS STOCK */}
                  {a.current !== undefined && a.min !== undefined && (
                    <div className="font-semibold text-orange-600">
                      {a.current} / {a.min}
                    </div>
                  )}

                  {/* PRODUCTOS STOCK */}
                  {a.product?.stock !== undefined && (
                    <div className="font-semibold text-red-600">
                      {a.product.stock} / {a.product.min}
                    </div>
                  )}

                  {/* FECHA CADUCIDAD */}
                  {a.batch && (
                    <div className="text-blue-600">

                      <div>
                        {a.batch.expirationDate}
                      </div>

                     

                    </div>
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