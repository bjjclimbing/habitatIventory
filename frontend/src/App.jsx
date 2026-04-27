import { useEffect, useState, useRef } from "react";
import { Link } from "react-router-dom";
import axios from "axios";

export default function App() {
  const [products, setProducts] = useState([]);
  const [providers, setProviders] = useState([]);
  const [providerId, setProviderId] = useState("");
  const [search, setSearch] = useState("");

  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);

  const loading = useRef(false);
  const [showTop, setShowTop] = useState(false);

  // =========================
  // LOAD PROVIDERS
  // =========================
  useEffect(() => {
    axios.get("http://localhost:8000/api/providers")
      .then(res => {
        setProviders(res.data.member || []);
      });
  }, []);

  // =========================
  // RESET cuando cambian filtros
  // =========================
  useEffect(() => {
    setProducts([]);
    setPage(1);
    loadProducts(1, true);
  }, [providerId, search]);

  // =========================
  // LOAD PRODUCTS
  // =========================
  const loadProducts = async (pageToLoad = 1, reset = false) => {
    if (loading.current) return;
    loading.current = true;

    try {
      let url = `http://localhost:8000/api/products?page=${pageToLoad}`;

      if (providerId) url += `&provider=${providerId}`;
      if (search) url += `&name=${search}`;

      const res = await axios.get(url);

      const newData = res.data.data || [];

      setTotal(res.data.total || 0);

      setProducts(prev =>
        reset ? newData : [...prev, ...newData]
      );

      setPage(pageToLoad);

    } catch (e) {
      console.error(e);
    }

    loading.current = false;
  };

  // =========================
  // SCROLL (infinite + back to top)
  // =========================
  useEffect(() => {
    const handleScroll = () => {

      // infinite scroll
      if (
        window.innerHeight + window.scrollY >=
        document.body.offsetHeight - 200
      ) {
        if (products.length < total) {
          loadProducts(page + 1);
        }
      }

      // botón back to top
      setShowTop(window.scrollY > 400);
    };

    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);

  }, [products, total, page]);

  const scrollTop = () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  // =========================
  // UI
  // =========================
  return (
    <div className="min-h-screen bg-gray-100">

      {/* HEADER */}
      <div className="bg-white shadow-sm border-b">
        <div className="max-w-6xl mx-auto px-6 py-4 flex justify-between items-center">

          <div>
            <h1 className="text-2xl font-bold text-gray-800">
              📦 Inventory Dashboard
            </h1>
            <p className="text-sm text-gray-500">
              Gestión de productos y proveedores
            </p>
          </div>

          <Link
            to="/dashboard"
            className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm shadow"
          >
            Dashboard
          </Link>

        </div>
      </div>

      {/* CONTENT */}
      <div className="max-w-6xl mx-auto p-6">

        {/* FILTROS */}
        <div className="mb-6 flex gap-3 items-center">

          <select
            value={providerId}
            onChange={(e) => setProviderId(e.target.value)}
            className="border rounded-lg px-4 py-2 shadow-sm bg-white"
          >
            <option value="">Todos los proveedores</option>

            {providers.map(p => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>

          <input
            placeholder="Buscar producto..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="border rounded-lg px-4 py-2 w-full"
          />

          <div className="text-sm text-gray-500">
            {products.length} / {total}
          </div>

        </div>

        {/* TABLA */}
        <div className="bg-white rounded-xl shadow overflow-hidden">

          <table className="w-full text-sm">

            <thead className="bg-gray-50 text-gray-600">
              <tr>
                <th className="text-left p-4">Producto</th>
                <th className="text-left">Proveedor</th>
                <th className="text-center">Stock</th>
              </tr>
            </thead>

            <tbody>
              {products.map(p => (
                <tr key={p.id} className="border-t hover:bg-gray-50">

                  {/* PRODUCT LINK */}
                  <td className="p-4">
                    <Link
                      to={`/products/${p.id}`}
                      className="text-blue-600 hover:underline font-medium"
                    >
                      {p.name}
                    </Link>
                  </td>

                  {/* PROVIDER LINK */}
                  <td>
                    {p.provider ? (
                      <Link
                        to={`/providers/${p.provider.id}`}
                        className="text-gray-700 hover:text-blue-600 hover:underline"
                      >
                        {p.provider.name}
                      </Link>
                    ) : (
                      "-"
                    )}
                  </td>

                  <td className="text-center font-semibold">
                    {p.stock}
                  </td>

                </tr>
              ))}
            </tbody>

          </table>

        </div>

        {/* LOADING */}
        {products.length < total && (
          <div className="text-center p-4 text-gray-500">
            Cargando más productos...
          </div>
        )}

      </div>

      {/* 🔥 BACK TO TOP */}
      {showTop && (
        <button
          onClick={scrollTop}
          className="fixed bottom-6 right-6 bg-blue-600 text-white px-4 py-2 rounded-full shadow-lg hover:bg-blue-700 transition"
        >
          ↑
        </button>
      )}

    </div>
  );
}