import { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import { api } from "./api";

export default function ProviderDetail() {
  const { id } = useParams();

  const [provider, setProvider] = useState(null);
  const [products, setProducts] = useState([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);

  const [loading, setLoading] = useState(false);
  const [showTop, setShowTop] = useState(false);

  // =========================
  // INIT
  // =========================
  useEffect(() => {
    setProducts([]);
    setPage(1);

    loadProvider();
    loadProducts(1);
  }, [id]);

  // =========================
  // SCROLL
  // =========================
  useEffect(() => {
    const handleScroll = () => {
      if (
        window.innerHeight + window.scrollY >=
        document.body.offsetHeight - 200
      ) {
        if (!loading && products.length < total) {
          loadProducts(page + 1);
        }
      }

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
      const res = await api.get(`providers/${id}`);
      setProvider(res.data);
    } catch (e) {
      console.error(e);
    }
  };

  // =========================
  // LOAD PRODUCTS
  // =========================
  const loadProducts = async (pageToLoad) => {
    if (loading) return;

    setLoading(true);

    try {
      const res = await api.get(
        `products?provider=${id}&page=${pageToLoad}`
      );

      console.log("RESPONSE:", res.data);

      const newProducts = res.data.data || [];
      const totalItems = res.data.total || 0;

      setTotal(totalItems);

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
  // KPIs
  // =========================
  const lowStock = products.filter(
    (p) => p.stock > 0 && p.stock < p.minStock
  ).length;

  const noStock = products.filter(
    (p) => p.stock === 0
  ).length;

  // =========================
  // UTIL
  // =========================
  const copyEmail = () => {
    if (provider?.email) {
      navigator.clipboard.writeText(provider.email);
      alert("Email copiado");
    }
  };

  const scrollTop = () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  if (!provider) return <div>Cargando...</div>;

  // =========================
  // RENDER
  // =========================
  return (
    <div className="min-h-screen bg-gray-100 p-6">

      <div className="max-w-5xl mx-auto">

        {/* HEADER */}
        <Link to="/" className="text-blue-600 underline mb-4 inline-block">
          ← Volver
        </Link>

        {/* CARD PROVIDER */}
        <div className="bg-white p-6 rounded shadow mb-6">

          <h2 className="text-2xl font-bold mb-2">
            {provider.name}
          </h2>

          <div className="text-gray-600 space-y-1">
            <div>Email: {provider.email || "-"}</div>
            <div>Teléfono: {provider.phone || "-"}</div>
            <div>Contacto: {provider.contactPerson || "-"}</div>
            <div>Dirección: {provider.address || "-"}</div>
          </div>

          {/* ACTIONS */}
          <div className="mt-4 flex gap-3">

            <button
              onClick={copyEmail}
              className="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300"
            >
              Copiar email
            </button>

            <a
              href={`mailto:${provider.email}`}
              className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
            >
              Enviar email
            </a>

          </div>

        </div>

        {/* KPIs */}
        <div className="flex gap-4 mb-6">

          <div className="bg-white p-4 rounded shadow flex-1 text-center">
            <p>Total productos</p>
            <p className="text-xl font-bold">{total}</p>
          </div>

          <div className="bg-yellow-100 p-4 rounded shadow flex-1 text-center">
            <p>Bajo stock</p>
            <p className="text-xl font-bold">{lowStock}</p>
          </div>

          <div className="bg-red-200 p-4 rounded shadow flex-1 text-center">
            <p>Sin stock</p>
            <p className="text-xl font-bold">{noStock}</p>
          </div>

        </div>

        {/* LISTA */}
        <div className="bg-white rounded shadow">

          {products.map(p => (
            <div
              key={p.id}
              className="flex justify-between p-4 border-b"
            >
              <div>{p.name}</div>
              <div className="font-semibold">{p.stock}</div>
            </div>
          ))}

          {loading && (
            <div className="p-4 text-center text-gray-500">
              Cargando...
            </div>
          )}

        </div>

      </div>

      {/* BACK TO TOP */}
      {showTop && (
        <button
          onClick={scrollTop}
          className="fixed bottom-6 right-6 bg-blue-600 text-white w-10 h-10 rounded-full shadow"
        >
          ↑
        </button>
      )}

    </div>
  );
}