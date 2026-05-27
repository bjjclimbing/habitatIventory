import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "./api";

export default function Dashboard() {

  const [products, setProducts] = useState([]);
  const [page, setPage] = useState(1);

  const [stats, setStats] = useState(null);

  const [loading, setLoading] = useState(false);
  const [showTop, setShowTop] = useState(false);

  // =========================
  // INIT
  // =========================
  useEffect(() => {
    loadProducts(1);
    loadStats();
  }, []);

  // =========================
  // SCROLL
  // =========================
  useEffect(() => {

    const handleScroll = () => {

      // infinite scroll
      if (
        window.innerHeight + window.scrollY >=
        document.body.offsetHeight - 200
      ) {
        if (!loading) {
          loadProducts(page + 1);
        }
      }

      // back to top
      setShowTop(window.scrollY > 400);
    };

    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);

  }, [page, loading]);

  // =========================
  // LOAD PRODUCTS (tabla)
  // =========================
  const loadProducts = async (pageToLoad) => {
    if (loading) return;

    setLoading(true);

    try {
      const res = await api.get(`products?page=${pageToLoad}`);

      const newProducts = res.data.data || [];

      setProducts(prev => {
        const ids = new Set(prev.map(p => p.id));
        const filtered = newProducts.filter(p => !ids.has(p.id));
        return [...prev, ...filtered];
      });

      setPage(pageToLoad);

    } catch (e) {
      console.error(e);
    }

    setLoading(false);
  };

  // =========================
  // LOAD STATS (KPIs reales)
  // =========================
  const loadStats = async () => {
    try {
      const res = await api.get("dashboard");
      setStats(res.data);
    } catch (e) {
      console.error("Error loading stats", e);
    }
  };

  // =========================
  // BACK TO TOP
  // =========================
  const scrollTop = () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  // =========================
  // RENDER
  // =========================
  return (
    <div className="min-h-screen bg-gray-100 p-6">

      <div className="max-w-6xl mx-auto">

        {/* HEADER */}
        <div className="mb-6 flex justify-between items-center">
          <h1 className="text-2xl font-semibold">
            📊 Dashboard
          </h1>

          <Link to="/" className="text-blue-600 underline">
            ← Volver
          </Link>
        </div>

        {/* KPIs (desde backend) */}
        <div className="grid grid-cols-4 gap-4 mb-6">

          <div className="bg-white p-4 rounded shadow text-center">
            <p className="text-gray-500">Total productos</p>
            <p className="text-2xl font-bold">
              {stats?.total ?? "-"}
            </p>
          </div>

          <div className="bg-red-200 p-4 rounded shadow text-center">
            <p className="text-gray-700">Críticos</p>
            <p className="text-2xl font-bold">
              {stats?.critical ?? "-"}
            </p>
          </div>

          <div className="bg-yellow-100 p-4 rounded shadow text-center">
            <p>Bajo stock</p>
            <p className="text-2xl font-bold">
              {stats?.lowStock ?? "-"}
            </p>
          </div>

          <div className="bg-gray-200 p-4 rounded shadow text-center">
            <p>Sin stock</p>
            <p className="text-2xl font-bold">
              {stats?.noStock ?? "-"}
            </p>
          </div>

        </div>

        {/* TABLA */}
        <div className="bg-white rounded shadow p-4">

          <h2 className="mb-4 font-semibold">
            Productos críticos
          </h2>

          <table className="w-full text-sm">

            <thead>
              <tr className="border-b text-gray-500">
                <th className="text-left py-2">Nombre</th>
                <th className="text-left py-2">Stock</th>
                <th className="text-left py-2">Mínimo</th>
              </tr>
            </thead>

            <tbody>
              {products
                .filter(p => p.stock <= p.minStock)
                .map(p => (
                  <tr key={p.id} className="border-b">

                    <td className="py-2">
                      <Link
                        to={`/products/${p.id}`}
                        className="text-blue-600 hover:underline"
                      >
                        {p.name}
                      </Link>
                    </td>

                    <td className="py-2 text-red-500">
                      {p.stock}
                    </td>

                    <td className="py-2">
                      {p.minStock}
                    </td>

                  </tr>
              ))}
            </tbody>

          </table>

          {loading && (
            <div className="p-4 text-center text-gray-500">
              Cargando más...
            </div>
          )}

        </div>

      </div>

      {/* BACK TO TOP */}
      {showTop && (
        <button
          onClick={scrollTop}
          className="fixed bottom-6 right-6 bg-blue-600 text-white w-10 h-10 rounded-full shadow-lg hover:bg-blue-700"
        >
          ↑
        </button>
      )}

    </div>
  );
}