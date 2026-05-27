import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { api } from "./api";

export default function BudgetPage() {

  const { id } = useParams();

  const [products, setProducts] = useState([]);
  const [clients, setClients] = useState([]);

  const [clientId, setClientId] = useState("");

  const [items, setItems] = useState([]);
  const [name, setName] = useState("Nuevo presupuesto");
  const [search, setSearch] = useState("");

  // =========================
  // LOAD PRODUCTS
  // =========================
  useEffect(() => {
    loadProducts();
    loadClients();
  }, []);

  const loadProducts = async (searchValue = "") => {
    try {
      let url = `/products?page=1`;

      if (searchValue) {
        url += `&name=${encodeURIComponent(searchValue)}`;
      }

      const res = await api.get(url);
      setProducts(res.data.data || []);

    } catch (e) {
      console.error("Error loading products", e);
    }
  };

  // =========================
  // LOAD CLIENTS
  // =========================
  const loadClients = async () => {
    try {
      const res = await api.get("/clients");
      setClients(res.data || []);
    } catch (e) {
      console.error("Error loading clients", e);
    }
  };

  // =========================
  // LOAD BUDGET
  // =========================
  useEffect(() => {
    if (id) loadBudget();
  }, [id]);

  const loadBudget = async () => {
    try {
      const res = await api.get(`/budgets/${id}`);
      const budget = res.data;

      setName(budget.name || "");
      setClientId(budget.client?.id || "");

      setItems(
        (budget.items || []).map(i => ({
          productId: i.product.id,
          name: i.product.name,
          sku: i.product.sku,
          quantity: i.quantity,
          unitPrice: i.unitPrice,
          priceModificationReason: i.priceModificationReason || ""
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
        sku: "",
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
      sku: product.sku,
      unitPrice: product.price || 0,
      priceModificationReason: ""
    };

    setItems(updated);
  };

  // =========================
  // UPDATE FIELD
  // =========================
  const updateItem = (index, field, value) => {
    const updated = [...items];
    updated[index][field] = value;
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
        clientId, // 🔥 NUEVO
        items: items.map(i => ({
          productId: i.productId,
          quantity: i.quantity,
          unitPrice: i.unitPrice,
          priceModificationReason: i.priceModificationReason
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

  return (
    <div className="max-w-7xl mx-auto p-6">

      {/* HEADER */}
      <div className="mb-6 space-y-3">

        <input
          value={name}
          onChange={(e) => setName(e.target.value)}
          className="w-full text-2xl font-semibold border-b border-gray-300 focus:outline-none"
        />

        {/* 🔥 CLIENT SELECT */}
        <select
          value={clientId}
          onChange={(e) => setClientId(e.target.value)}
          className="border rounded-lg px-3 py-2"
        >
          <option value="">Seleccionar cliente...</option>

          {clients.map(c => (
            <option key={c.id} value={c.id}>
              {c.name} ({c.rif})
            </option>
          ))}
        </select>

      </div>

      {/* SEARCH */}
      <div className="mb-4">
        <input
          type="text"
          placeholder="🔍 Buscar producto por nombre o SKU..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full border rounded-lg px-4 py-2 shadow-sm"
        />
      </div>

      {/* TABLE */}
      <div className="bg-white rounded-xl shadow overflow-hidden">

        <table className="w-full text-sm table-fixed">

          <thead className="bg-gray-50 text-gray-600">
            <tr>
              <th className="p-4 w-64 text-left">Producto</th>
              <th className="p-4 w-40 text-left">SKU</th>
              <th className="text-center w-24">Cantidad</th>
              <th className="text-right w-32">Precio</th>
              <th className="text-right w-32">Total</th>
              <th className="w-16"></th>
            </tr>
          </thead>

          <tbody>

            {items.map((item, index) => (

              <tr key={index} className="border-t hover:bg-gray-50">

                {/* PRODUCT */}
                <td className="p-4">
                  {item.productId ? (
                    <div className="bg-gray-100 border rounded-lg px-3 py-2 truncate">
                      {item.name}
                    </div>
                  ) : (
                    <select
                      value={item.productId}
                      onChange={(e) =>
                        handleSelectProduct(e.target.value, index)
                      }
                      className="w-full border rounded-lg px-3 py-2"
                    >
                      <option value="">Seleccionar producto...</option>

                      {products.map(p => (
                        <option key={p.id} value={p.id}>
                          {p.sku} — {p.name}
                        </option>
                      ))}
                    </select>
                  )}
                </td>

                {/* SKU */}
                <td className="p-4 font-mono text-gray-700 truncate">
                  {item.sku || "-"}
                </td>

                {/* QUANTITY */}
                <td className="text-center">
                  <input
                    type="number"
                    value={item.quantity}
                    onChange={(e) =>
                      updateItem(index, "quantity", parseInt(e.target.value) || 0)
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
                      updateItem(index, "unitPrice", parseFloat(e.target.value) || 0)
                    }
                    className="w-24 text-right border rounded px-2 py-1"
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