import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { api } from "./api";

export default function BudgetPage() {

  const { id } = useParams();

  const [products, setProducts] = useState([]);
  const [items, setItems] = useState([]);
  const [name, setName] = useState("Nuevo presupuesto");
  const [search, setSearch] = useState("");

  // =========================
  // LOAD PRODUCTS
  // =========================
  useEffect(() => {
    loadProducts();
  }, []);

  const loadProducts = async (searchValue = "") => {

    try {

      let url = `/products?page=1`;

      if (searchValue) {
        url += `&name=${searchValue}`;
      }

      const res = await api.get(url);

      setProducts(res.data.data || []);

    } catch (e) {

      console.error("Error loading products", e);
    }
  };

  // =========================
  // LOAD BUDGET
  // =========================
  useEffect(() => {

    if (id) {
      loadBudget();
    }

  }, [id]);

  const loadBudget = async () => {

    try {

      const res = await api.get(`/budgets/${id}`);

      const budget = res.data;

      setName(budget.name || "");

      setItems(
        (budget.items || []).map(i => ({
          productId: i.product.id,
          name: i.product.name,
          quantity: i.quantity,
          unitPrice: i.unitPrice,
          priceModificationReason:
            i.priceModificationReason || ""
        }))
      );

    } catch (e) {

      console.error("Error loading budget", e);

      alert("❌ Error cargando presupuesto");
    }
  };

  // =========================
  // SEARCH (debounce)
  // =========================
  useEffect(() => {

    const delay = setTimeout(() => {
      loadProducts(search);
    }, 300);

    return () => clearTimeout(delay);

  }, [search]);

  // =========================
  // ADD ITEM
  // =========================
  const addItem = () => {

    setItems([
      ...items,
      {
        productId: "",
        name: "",
        quantity: 1,
        unitPrice: 0,
        priceModificationReason: ""
      }
    ]);
  };

  // =========================
  // REMOVE ITEM
  // =========================
  const removeItem = (index) => {

    const updated = [...items];

    updated.splice(index, 1);

    setItems(updated);
  };

  // =========================
  // SELECT PRODUCT
  // =========================
  const handleSelectProduct = (productId, index) => {

    const product = products.find(
      p => p.id === parseInt(productId)
    );

    if (!product) return;

    const updated = [...items];

    updated[index] = {
      ...updated[index],
      productId: product.id,
      name: product.name,
      unitPrice: product.price || 0,
      priceModificationReason: ""
    };

    setItems(updated);
  };

  // =========================
  // QUANTITY
  // =========================
  const handleQuantityChange = (value, index) => {

    const updated = [...items];

    updated[index].quantity = parseInt(value) || 0;

    setItems(updated);
  };

  // =========================
  // PRICE
  // =========================
  const handlePriceChange = (value, index) => {

    const updated = [...items];

    updated[index].unitPrice = parseFloat(value) || 0;

    setItems(updated);
  };

  // =========================
  // REASON
  // =========================
  const handleReasonChange = (value, index) => {

    const updated = [...items];

    updated[index].priceModificationReason = value;

    setItems(updated);
  };

  // =========================
  // TOTAL
  // =========================
  const total = items.reduce(
    (sum, i) => sum + (i.quantity * i.unitPrice),
    0
  );

  // =========================
  // SAVE
  // =========================
  const saveBudget = async () => {

    try {

      const payload = {
        name,
        items: items.map(i => ({
          productId: i.productId,
          quantity: i.quantity,
          unitPrice: i.unitPrice,
          priceModificationReason:
            i.priceModificationReason
        }))
      };

      if (id) {

        await api.put(`/budgets/${id}`, payload);

        alert("✅ Presupuesto actualizado");

      } else {

        await api.post("/budgets", payload);

        alert("✅ Presupuesto guardado");
      }

    } catch (e) {

      console.error(e);

      alert("❌ Error al guardar");
    }
  };

  // =========================
  // UI
  // =========================
  return (

    <div className="max-w-7xl mx-auto p-6">

      {/* HEADER */}
      <div className="mb-8">

        <label className="block text-sm text-gray-500 mb-1">
          Nombre del presupuesto
        </label>

        <input
          value={name}
          onChange={(e) => setName(e.target.value)}
          className="w-full text-2xl font-semibold border-b border-gray-300 focus:outline-none focus:border-blue-500"
        />

      </div>

      {/* SEARCH */}
      <div className="mb-4">

        <input
          type="text"
          placeholder="🔍 Buscar producto por nombre o SKU..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full border rounded-lg px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
        />

      </div>

      {/* TABLE */}
      <div className="bg-white rounded-xl shadow overflow-hidden">

        <table className="w-full text-sm">

          <thead className="bg-gray-50 text-gray-600">

            <tr>
              <th className="text-left p-4">Producto</th>
              <th className="text-center">Cantidad</th>
              <th className="text-right">Precio</th>
              <th className="text-left">Motivo</th>
              <th className="text-right">Total</th>
              <th></th>
            </tr>

          </thead>

          <tbody>

            {items.map((item, index) => (

              <tr
                key={index}
                className="border-t hover:bg-gray-50"
              >

                {/* PRODUCT */}
                {/* PRODUCT */}
<td className="p-4">

{item.productId ? (

  <div className="bg-gray-100 border rounded-lg px-3 py-2">
    {item.name}
  </div>

) : (

  <select
    value={item.productId}
    onChange={(e) =>
      handleSelectProduct(
        e.target.value,
        index
      )
    }
    className="w-full border rounded-lg px-3 py-2"
  >

    <option value="">
      Seleccionar producto...
    </option>

    {products.map(p => (
      <option key={p.id} value={p.id}>
        {p.sku} — {p.name}
      </option>
    ))}

  </select>

)}

</td>

                {/* QUANTITY */}
                <td className="text-center">

                  <input
                    type="number"
                    value={item.quantity}
                    onChange={(e) =>
                      handleQuantityChange(
                        e.target.value,
                        index
                      )
                    }
                    className="w-16 text-center border rounded"
                  />

                </td>

                {/* PRICE */}
                <td className="text-right pr-4">

                  <input
                    type="number"
                    step="0.01"
                    value={item.unitPrice}
                    onChange={(e) =>
                      handlePriceChange(
                        e.target.value,
                        index
                      )
                    }
                    className="w-24 text-right border rounded px-2 py-1"
                  />

                </td>

                {/* REASON */}
                <td className="px-4">

                  <input
                    type="text"
                    value={item.priceModificationReason || ""}
                    onChange={(e) =>
                      handleReasonChange(
                        e.target.value,
                        index
                      )
                    }
                    placeholder="Motivo opcional..."
                    className="w-full border rounded px-2 py-1 text-sm"
                  />

                </td>

                {/* TOTAL */}
                <td className="text-right pr-4 font-semibold">
                  {(item.quantity * item.unitPrice).toFixed(2)} €
                </td>

                {/* DELETE */}
                <td className="text-center">

                  <button
                    onClick={() => removeItem(index)}
                    className="text-red-500 hover:text-red-700"
                  >
                    ✕
                  </button>

                </td>

              </tr>
            ))}

          </tbody>

        </table>

      </div>

      {/* ACTIONS */}
      <div className="mt-4 flex justify-between items-center">

        <button
          onClick={addItem}
          className="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg"
        >
          ➕ Añadir producto
        </button>

        <div className="text-xl font-semibold">
          Total: {total.toFixed(2)} €
        </div>

      </div>

      {/* SAVE */}
      <button
        onClick={saveBudget}
        className="mt-6 bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700"
      >
        💾 {id ? "Actualizar Presupuesto" : "Guardar Presupuesto"}
      </button>

    </div>
  );
}