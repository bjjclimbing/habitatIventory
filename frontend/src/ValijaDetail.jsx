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
    const res = await api.get(`/valijas/${id}`);
    setValija(res.data);

    const prodRes = await api.get("/products");
    setProducts(prodRes.data.data);
  };

  const addProduct = async () => {
    await api.post(`/valijas/${id}/products`, {
      productId: newProduct,
      stockMin
    });

    load();
  };

  const sync = async () => {
    await api.post(`/valijas/${id}/sync`);
    alert("Valija sincronizada");
  };

  if (!valija) return "Loading...";

  return (
    <div>

      <h2 className="text-xl font-bold mb-4">
        📦 {valija.name}
      </h2>

      {/* añadir producto */}
      <div className="flex gap-2 mb-4">

        <select onChange={(e) => setNewProduct(e.target.value)}>
          <option>Seleccionar producto</option>
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
        />

        <button onClick={addProduct}>
          Añadir
        </button>

      </div>

      {/* lista */}
      <table className="w-full">
        <thead>
          <tr>
            <th>Producto</th>
            <th>Stock mínimo</th>
          </tr>
        </thead>
        <tbody>
          {valija.products?.map(vp => (
            <tr key={vp.id}>
              <td>{vp.product.name}</td>
              <td>{vp.stockMin}</td>
            </tr>
          ))}
        </tbody>
      </table>

      {/* sync */}
      <button
        onClick={sync}
        className="mt-4 bg-blue-600 text-white px-4 py-2 rounded"
      >
        🔄 Sincronizar
      </button>

    </div>
  );
}