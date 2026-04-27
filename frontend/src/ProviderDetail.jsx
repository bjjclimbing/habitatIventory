import { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import axios from "axios";

export default function ProviderDetail() {
  const { id } = useParams();

  const [provider, setProvider] = useState(null);
  const [products, setProducts] = useState([]);

  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);

  const [loading, setLoading] = useState(false);
  const [showTop, setShowTop] = useState(false);

  // =========================
  // RESET
  // =========================
  useEffect(() => {
    setProducts([]);
    setProvider(null);
    setPage(1);
    setTotal(0);

    loadProvider();
    loadProducts(1);
  }, [id]);

  // =========================
  // SCROLL
  // =========================
  useEffect(() => {
    const handleScroll = () => {

      // 🔥 INFINITE SCROLL
      if (
        window.innerHeight + window.scrollY >=
        document.body.offsetHeight - 200
      ) {
        if (!loading && products.length < total) {
          loadProducts(page + 1);
        }
      }

      // 🔝 BACK TO TOP
      setShowTop(window.scrollY > 400);
    };

    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);

  }, [products, total, page, loading]);

  // =========================
  // LOAD PROVIDER
  // =========================
  const loadProvider = async () => {
    try {
      const res = await axios.get(
        `http://localhost:8000/api/providers/${id}`
      );
      setProvider(res.data);
    } catch (e) {
      console.error(e);
    }
  };

  // =========================
  // LOAD PRODUCTS (PAGINADO)
  // =========================
  const loadProducts = async (pageToLoad) => {
    if (loading) return;

    setLoading(true);

    try {
      const res = await axios.get(
        `http://localhost:8000/api/products?provider=${id}&page=${pageToLoad}`
      );

      console.log("RESPONSE:", res.data);

      const newProducts = res.data.data || [];
      const totalItems = res.data.total || 0;

      setTotal(totalItems);

      // evitar duplicados
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
  // KPIs CORRECTOS
  // =========================
  const totalProducts = total; // 🔥 ESTE ES EL BUENO

  const lowStock = products.filter(
    (p) => p.stock > 0 && p.stock < p.minStock
  ).length;

  const noStock = products.filter(
    (p) => p.stock === 0
  ).length;

  const scrollTop = () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  if (!provider) return <div className="p-6">Cargando...</div>;

  return (
    <div className="min-h-screen bg-gray-100 p-6">

      <div className="max-w-6xl mx-auto">

        {/* HEADER */}
        <div className="mb-6 flex justify-between items-center">
          <h1 className="text-2xl font-bold">{provider.name}</h1>

          <Link to="/" className="text-blue-600 underline">
            ← Volver
          </Link>
        </div>

        {/* INFO */}
        <div className="bg-white p-4 rounded shadow mb-6">
          <p>Email: {provider.email || "-"}</p>
          <p>Teléfono: {provider.phone || "-"}</p>
        </div>

        {/* KPIs */}
        <div className="grid grid-cols-3 gap-4 mb-6">

          <div className="bg-white p-4 rounded shadow text-center">
            <p className="text-gray-500">Total productos</p>
            <p className="text-2xl font-bold">{totalProducts}</p>
          </div>

          <div className="bg-yellow-100 p-4 rounded shadow text-center">
            <p>Bajo stock</p>
            <p className="text-2xl font-bold">{lowStock}</p>
          </div>

          <div className="bg-red-100 p-4 rounded shadow text-center">
            <p>Sin stock</p>
            <p className="text-2xl font-bold">{noStock}</p>
          </div>

        </div>

        {/* TABLA */}
        <div className="bg-white rounded shadow overflow-hidden">

          <table className="w-full text-sm">

            <thead className="bg-gray-50">
              <tr>
                <th className="text-left p-4">Producto</th>
                <th className="text-center">Stock</th>
              </tr>
            </thead>

            <tbody>
              {products.map((p) => (
                <tr key={p.id} className="border-t hover:bg-gray-50">

                  <td className="p-4">
                    <Link
                      to={`/products/${p.id}`}
                      className="text-blue-600 hover:underline"
                    >
                      {p.name}
                    </Link>
                  </td>

                  <td className="text-center font-semibold">
                    {p.stock}
                  </td>

                </tr>
              ))}
            </tbody>

          </table>

          {loading && (
            <div className="p-4 text-center">Cargando...</div>
          )}

        </div>

      </div>

      {/* BACK TO TOP */}
      {showTop && (
        <button
          onClick={scrollTop}
          className="fixed bottom-6 right-6 bg-blue-600 text-white w-10 h-10 rounded-full shadow-lg"
        >
          ↑
        </button>
      )}

    </div>
  );
}