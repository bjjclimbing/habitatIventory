import { useEffect, useState } from "react";
import { api } from "./api";
import { useParams } from "react-router-dom";

export default function ValijaDetail() {
  const { id } = useParams();

  const [valija, setValija] = useState(null);

  // 🔥 búsqueda productos
  const [search, setSearch] = useState("");
  const [results, setResults] = useState([]);
  const [selectedProduct, setSelectedProduct] = useState(null);

  const [stockMin, setStockMin] = useState(10);

  useEffect(() => {
    load();
  }, []);

  const load = async () => {
    try {
      const res = await api.get(`/valijas/${id}`);
      setValija(res.data);
    } catch (e) {
      console.error(e);
    }
  };

  // =========================
  // 🔥 BUSCAR PRODUCTOS (igual que Budget)
  // =========================
  useEffect(() => {
    if (search.length < 2) {
      setResults([]);
      return;
    }

    const delay = setTimeout(async () => {
      try {
        const res = await api.get(`/products?name=${encodeURIComponent(search)}`);

        setResults(res.data.data || []);
      } catch (e) {
        console.error(e);
      }
    }, 300);

    return () => clearTimeout(delay);

  }, [search]);

  // =========================
  // ADD PRODUCT
  // =========================
  const addProduct = async () => {
    if (!selectedProduct) return;

    await api.post(`/valijas/${id}/products`, {
      productId: selectedProduct.id,
      stockMin
    });

    setSelectedProduct(null);
    setSearch("");
    setResults([]);
    setStockMin(10);

    load();
  };

  // =========================
  // UPDATE STOCK
  // =========================
  const updateStockMin = async (vpId, newValue, oldValue) => {
    const parsed = parseInt(newValue);

    if (parsed === oldValue || isNaN(parsed)) return;

    try {
      await api.put(`/valijas/products/${vpId}`, {
        stockMin: parsed
      });

      load();
    } catch (e) {
      console.error(e);
      alert("Error actualizando stock mínimo");
    }
  };

  // =========================
  // DELETE
  // =========================
  const deleteProduct = async (vpId) => {
    if (!confirm("¿Eliminar producto de la maleta?")) return;

    try {
      await api.delete(`/valijas/products/${vpId}`);
      load();
    } catch (e) {
      console.error(e);
      alert("Error eliminando producto");
    }
  };

  // =========================
  // SYNC
  // =========================
  const sync = async () => {
    await api.post(`/valijas/${id}/sync`);
    alert("Maleta sincronizada");
    load();
  };

  if (!valija) return <div className="p-6">Loading...</div>;

  return (
    <div className="max-w-5xl mx-auto p-6">

      {/* HEADER */}
      <div className="mb-6">
        <h2 className="text-2xl font-bold text-gray-800">
          📦 {valija.name}
        </h2>
        <p className="text-sm text-gray-500">
          Configuración de productos de la maleta
        </p>
      </div>

      {/* 🔥 ADD PRODUCT (NUEVO UX) */}
      <div className="bg-white p-4 rounded-xl shadow mb-6 space-y-3">

        <input
          placeholder="🔍 Buscar producto por nombre o SKU..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="border rounded-lg px-4 py-2 w-full"
        />

        {/* RESULTADOS */}
        {results.length > 0 && (
          <div className="border rounded-lg max-h-40 overflow-y-auto bg-white">
            {results.map(p => (
              <div
                key={p.id}
                onClick={() => {
                  setSelectedProduct(p);
                  setSearch(p.name);
                  setResults([]);
                }}
                className="px-3 py-2 hover:bg-gray-100 cursor-pointer"
              >
                {p.name} ({p.sku || "-"})
              </div>
            ))}
          </div>
        )}

        {/* SELECCIONADO */}
        {selectedProduct && (
          <div className="text-sm text-green-600">
            ✔ Seleccionado: {selectedProduct.name}
          </div>
        )}

        <div className="flex gap-3">

          <input
            type="number"
            value={stockMin}
            onChange={(e) => setStockMin(e.target.value)}
            className="border rounded-lg px-4 py-2 w-24"
          />

          <button
            onClick={addProduct}
            className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
          >
            Añadir
          </button>

        </div>

      </div>

      {/* TABLE */}
      <div className="bg-white rounded-xl shadow overflow-hidden">

      <table className="w-full text-sm table-fixed">

      <thead className="bg-gray-50 text-gray-600">
  <tr>
    <th className="p-4 w-40 text-left">SKU</th>
    <th className="p-4 text-left">Producto</th>
    <th className="w-32 text-center">Stock mínimo</th>
    <th className="p-4 w-32 text-left">Vencimiento</th>
    <th className="w-24 text-center">Acciones</th>
  </tr>
</thead>

          <tbody>

            {valija.products?.length === 0 && (
              <tr>
                <td colSpan="3" className="p-4 text-center text-gray-500">
                  No hay productos en esta maleta
                </td>
              </tr>
            )}

            {valija.products?.map(vp => (
            
              <tr key={vp.id} className="border-t hover:bg-gray-50">
                {/* SKU */}
      <td className="p-4 text-gray-800 font-mono truncate">
        {vp.product.sku || "-"}
      </td>

                <td className="p-4 font-medium text-gray-800 ">
                  {vp.product.name}
                </td>

                <td className="text-center">
                  <input
                    type="number"
                    defaultValue={vp.stockMin}
                    onBlur={(e) =>
                      updateStockMin(vp.id, e.target.value, vp.stockMin)
                    }
                    className="w-20 text-center border rounded-lg px-2 py-1"
                  />
                </td>
                <td className="p-4 text-gray-700">
  {vp.product.nextExpirationDate
    ? new Date(vp.product.nextExpirationDate).toLocaleDateString("es-ES")
    : "-"}
</td>
                <td className="text-center">
                  <button
                    onClick={() => deleteProduct(vp.id)}
                    className="text-red-500 hover:text-red-700"
                  >
                    🗑️
                  </button>
                </td>

              </tr>
            ))}

          </tbody>

        </table>

      </div>

      {/* ACTIONS */}
      <div className="mt-6 flex justify-end">
        <button
          onClick={sync}
          className="bg-blue-600 text-white px-5 py-2 rounded-lg shadow hover:bg-blue-700"
        >
          🔄 Sincronizar maleta
        </button>
      </div>

    </div>
  );
}