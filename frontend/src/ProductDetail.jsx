import { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import { api } from "./api";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
  CartesianGrid,
} from "recharts";

export default function ProductDetail() {
  const { id } = useParams();
  const [product, setProduct] = useState(null);
  const [quantity, setQuantity] = useState("");

  // ======================
  // LOAD PRODUCT
  // ======================
  useEffect(() => {
    if (!id) return;

    api.get(`products/${id}`)
      .then(res => setProduct(res.data))
      .catch(console.error);
  }, [id]);

  if (!product) return <div className="p-6">Loading...</div>;

  const stock = product.stock ?? 0;
  const min = product.minStock ?? product.minstock ?? 0;

  // ======================
  // STATUS INTELIGENTE
  // ======================
  const getStatus = () => {
    const hasExpired = product.batches?.some(b =>
      b.expirationDate && new Date(b.expirationDate) < new Date()
    );

    if (stock <= min) return "LOW";
    if (hasExpired) return "EXPIRING";
    return "OK";
  };

  const status = getStatus();

  const getStatusColor = (s) => {
    if (s === "LOW") return "text-red-500";
    if (s === "EXPIRING") return "text-yellow-500";
    return "text-green-500";
  };

  // ======================
  // DAYS HELPER
  // ======================
  const getDays = (date) => {
    if (!date) return "-";

    const diff = Math.ceil(
      (new Date(date) - new Date()) / (1000 * 60 * 60 * 24)
    );

    return `${diff} días`;
  };

  // ======================
  // GRAPH DATA (AGREGADO)
  // ======================
  const chartData = (product.batches || [])
    .filter(b => b.expirationDate)
    .sort((a, b) => new Date(a.expirationDate) - new Date(b.expirationDate))
    .reduce((acc, b) => {
      const date = new Date(b.expirationDate).toLocaleDateString();

      const existing = acc.find(x => x.date === date);

      if (existing) {
        existing.quantity += b.quantity ?? 0;
      } else {
        acc.push({
          date,
          quantity: b.quantity ?? 0,
        });
      }

      return acc;
    }, []);

  // ======================
  // CONSUME (placeholder)
  // ======================
  const handleConsume = async () => {
    if (!quantity) return;

    try {
      await api.post(`products/${id}/consume`, {
        quantity: parseInt(quantity),
      });

      setQuantity("");

      // recargar producto
      const res = await api.get(`products/${id}`);
      setProduct(res.data);

    } catch (e) {
      console.error(e);
    }
  };

  return (
    <div className="min-h-screen bg-gray-100 flex justify-center p-6">
      <div className="w-full max-w-4xl">

        {/* BACK */}
        <Link to="/" className="text-blue-600 mb-4 inline-block">
          ← Volver
        </Link>

        <div className="bg-white rounded-2xl shadow p-6">

          {/* TITLE */}
          <h1 className="text-2xl font-semibold mb-2">
            {product.name}
          </h1>

          {/* STATUS */}
          <div className={`font-semibold mb-4 ${getStatusColor(status)}`}>
            {status}
          </div>

          {/* INFO GRID */}
          <div className="grid grid-cols-2 gap-6 mb-6">

            <div>
              <p className="text-gray-500 text-sm">SKU</p>
              <p>{product.sku ?? "-"}</p>
            </div>

            <div>
              <p className="text-gray-500 text-sm">Proveedor</p>
              <p className="font-medium">
                {product.provider?.name ?? "-"}
              </p>
            </div>

            <div>
              <p className="text-gray-500 text-sm">Stock total</p>
              <p className="text-green-600 font-medium">
                {stock}
              </p>
            </div>

            <div>
              <p className="text-gray-500 text-sm">Stock mínimo</p>
              <p>{min}</p>
            </div>

          </div>

          {/* CONSUME */}
          <div className="flex gap-3 mb-6">
            <input
              type="number"
              placeholder="Cantidad"
              value={quantity}
              onChange={(e) => setQuantity(e.target.value)}
              className="border rounded px-3 py-2 w-40"
            />

            <button
              onClick={handleConsume}
              className="bg-red-500 text-white px-4 py-2 rounded"
            >
              Consumir
            </button>
          </div>

          {/* BATCH TABLE */}
          <div>
            <h2 className="font-semibold mb-3">
              Lotes (FIFO)
            </h2>

            <table className="w-full text-sm">
              <thead>
                <tr className="text-gray-500 border-b">
                  <th className="text-left py-2">Cantidad</th>
                  <th className="text-left py-2">Caducidad</th>
                  <th className="text-left py-2">Días</th>
                  <th className="text-left py-2">Estado</th>
                </tr>
              </thead>

              <tbody>
                {product.batches?.length > 0 ? (
                  [...product.batches]
                    .sort(
                      (a, b) =>
                        new Date(a.expirationDate) -
                        new Date(b.expirationDate)
                    )
                    .map((b, i) => {
                      const expired =
                        b.expirationDate &&
                        new Date(b.expirationDate) < new Date();

                      return (
                        <tr
                          key={b.id || i}
                          className={`border-b ${
                            expired ? "bg-red-50" : "bg-yellow-50"
                          }`}
                        >
                          <td className="py-2">
                            {b.quantity ?? "-"}
                          </td>

                          <td className="py-2">
                            {b.expirationDate
                              ? new Date(
                                  b.expirationDate
                                ).toLocaleDateString()
                              : "-"}
                          </td>

                          <td className="py-2">
                            {getDays(b.expirationDate)}
                          </td>

                          <td
                            className={`py-2 ${
                              expired
                                ? "text-red-500"
                                : "text-green-500"
                            }`}
                          >
                            {expired ? "EXPIRADO" : "OK"}
                          </td>
                        </tr>
                      );
                    })
                ) : (
                  <tr>
                    <td colSpan="4" className="py-2 text-gray-400">
                      Sin lotes
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* CHART */}
          <div className="bg-white rounded-xl shadow p-4 mt-6">
            <h2 className="font-semibold mb-4">
              Evolución de stock por caducidad
            </h2>

            {chartData.length > 0 ? (
              <ResponsiveContainer width="100%" height={250}>
                <LineChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="date" />
                  <YAxis />
                  <Tooltip />

                  <Line
                    type="monotone"
                    dataKey="quantity"
                    stroke="#2563eb"
                    strokeWidth={2}
                  />
                </LineChart>
              </ResponsiveContainer>
            ) : (
              <p className="text-gray-400">
                Sin datos para mostrar
              </p>
            )}
          </div>

        </div>
      </div>
    </div>
  );
}