import { useEffect, useState } from "react";
import { api } from "./api";
import { Link } from "react-router-dom";

export default function BudgetsList() {
  const [budgets, setBudgets] = useState([]);
  const [loading, setLoading] = useState(true);

  // 🔥 NUEVOS ESTADOS
  const [search, setSearch] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");

  // =========================
  // DOWNLOAD EXCEL
  // =========================
  const downloadExcel = async (id) => {
    try {
      const res = await api.get(`/budgets/${id}/export/excel`, {
        responseType: "blob"
      });

      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement("a");

      link.href = url;
      link.setAttribute("download", `budget_${id}.xlsx`);

      document.body.appendChild(link);
      link.click();
      link.remove();

    } catch (e) {
      console.error(e);
      alert("Error descargando Excel");
    }
  };

  // =========================
  // LOAD
  // =========================
  const load = async () => {
    try {
      setLoading(true);

      let url = "/budgets";

      const params = [];

      if (search) params.push(`search=${search}`);
      if (from) params.push(`from=${from}`);
      if (to) params.push(`to=${to}`);

      if (params.length) {
        url += "?" + params.join("&");
      }

      const res = await api.get(url);

      console.log("BUDGETS RESPONSE:", res.data);

      // 🔥 normalización segura
      const data =
        res.data?.data ||
        res.data?.budgets ||
        res.data ||
        [];

      setBudgets(Array.isArray(data) ? data : []);

    } catch (e) {
      console.error("Error loading budgets", e);
      setBudgets([]);
    } finally {
      setLoading(false);
    }
  };

  // =========================
  // DEBOUNCE FILTERS
  // =========================
  useEffect(() => {
    const delay = setTimeout(() => {
      load();
    }, 300);

    return () => clearTimeout(delay);
  }, [search, from, to]);

  // =========================
  // RENDER
  // =========================
  return (
    <div className="max-w-6xl mx-auto p-6">

      {/* HEADER */}
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-2xl font-bold text-gray-800">
            🧾 Presupuestos
          </h2>
          <p className="text-sm text-gray-500">
            Gestión de presupuestos
          </p>
        </div>

        <Link
          to="/budgets/new"
          className="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700"
        >
          + Nuevo presupuesto
        </Link>
      </div>

      {/* 🔥 FILTROS NUEVOS */}
      <div className="mb-6 flex gap-3">

        {/* SEARCH */}
        <input
          placeholder="🔍 Buscar presupuesto..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="border rounded-lg px-4 py-2 w-full"
        />

<div className="flex flex-col">
  <label className="text-xs text-gray-500 mb-1">Desde</label>
  <input
    type="date"
    value={from}
    onChange={(e) => setFrom(e.target.value)}
    className="border rounded-lg px-3 py-2"
  />
</div>

<div className="flex flex-col">
  <label className="text-xs text-gray-500 mb-1">Hasta</label>
  <input
    type="date"
    value={to}
    onChange={(e) => setTo(e.target.value)}
    className="border rounded-lg px-3 py-2"
  />
</div>

      </div>

      {/* LOADING */}
      {loading && (
        <div className="text-center text-gray-500 py-10">
          Cargando presupuestos...
        </div>
      )}

      {/* EMPTY */}
      {!loading && budgets.length === 0 && (
        <div className="text-center text-gray-500 py-10 bg-white rounded-xl shadow">
          No hay presupuestos
        </div>
      )}

      {/* TABLE */}
      {!loading && budgets.length > 0 && (
        <div className="bg-white rounded-xl shadow overflow-hidden">

          <table className="w-full text-sm">

            <thead className="bg-gray-50 text-gray-600">
              <tr>
                <th className="text-left p-4">Nombre</th>
                <th className="text-center">Items</th>
                <th className="text-center">Total</th>
                <th className="text-right p-4">Acciones</th>
              </tr>
            </thead>

            <tbody>
              {budgets.map((b) => (
                <tr key={b.id} className="border-t hover:bg-gray-50">

                  {/* NAME */}
                  <td className="p-4 font-medium text-gray-800">
                    {b.name || `Presupuesto #${b.id}`}
                  </td>

                  {/* ITEMS COUNT */}
                  <td className="text-center">
                    {b.itemsCount || 0}
                  </td>

                  {/* TOTAL */}
                  <td className="text-center font-semibold text-green-600">
                    € {Number(b.total || 0).toFixed(2)}
                  </td>

                  {/* ACTIONS */}
                  <td className="p-4 text-right space-x-2">

                    <Link
                      to={`/budgets/${b.id}`}
                      className="text-blue-600 hover:underline"
                    >
                      Ver
                    </Link>

                    <button
                      onClick={() => downloadExcel(b.id)}
                      className="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700"
                    >
                      Excel
                    </button>

                  </td>

                </tr>
              ))}
            </tbody>

          </table>

        </div>
      )}

    </div>
  );
}