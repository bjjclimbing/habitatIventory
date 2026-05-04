import { useEffect, useState } from "react";
import { api } from "./api";
import { useParams } from "react-router-dom";

export default function ValijaDetail() {
  const { id } = useParams();

  const [valija, setValija] = useState(null);
  const [products, setProducts] = useState([]);
  const [newProduct, setNewProduct] = useState("");
  const [stockMin, setStockMin] = useState(10);

  useEffect(() => {
    load();
  }, []);

  const load = async () => {
    try {
      const res = await api.get(`/valijas/${id}`);
      setValija(res.data);

      const prodRes = await api.get("/products");
      setProducts(prodRes.data.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  const addProduct = async () => {
    if (!newProduct) return;

    await api.post(`/valijas/${id}/products`, {
      productId: newProduct,
      stockMin
    });

    setNewProduct("");
    setStockMin(10);

    load();
  };

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

      {/* ADD PRODUCT */}
      <div className="bg-white p-4 rounded-xl shadow mb-6 flex gap-3 items-center">

        <select
          value={newProduct}
          onChange={(e) => setNewProduct(e.target.value)}
          className="border rounded-lg px-4 py-2 flex-1"
        >
          <option value="">Seleccionar producto</option>
          {products.map(p => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </select>

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

      {/* TABLE */}
      <div className="bg-white rounded-xl shadow overflow-hidden">

        <table className="w-full text-sm">

          <thead className="bg-gray-50 text-gray-600">
            <tr>
              <th className="text-left p-4">Producto</th>
              <th className="text-center">Stock mínimo</th>
              <th className="text-center">Acciones</th>
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

                {/* PRODUCTO */}
                <td className="p-4 font-medium text-gray-800">
                  {vp.product.name}
                </td>

                {/* EDITABLE STOCK */}
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

                {/* DELETE */}
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