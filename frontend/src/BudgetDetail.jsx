import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { api } from "./api";

export default function BudgetDetail() {
  const { id } = useParams();

  const [budget, setBudget] = useState(null);

  useEffect(() => {
    load();
  }, []);

  const load = async () => {
    const res = await api.get(`/budgets/${id}`);
    setBudget(res.data);
  };

  const exportExcel = async () => {
    window.open(`/api/budgets/${id}/export/excel`, "_blank");
  };

  const exportPdf = async () => {
    window.open(`/api/budgets/${id}/export/pdf`, "_blank");
  };

  if (!budget) return "Loading...";

  return (
    <div className="max-w-5xl mx-auto">

      <div className="flex justify-between mb-6">

        <h2 className="text-2xl font-bold">
          🧾 {budget.name}
        </h2>

        <div className="flex gap-2">
          <button
            onClick={exportExcel}
            className="bg-green-600 text-white px-4 py-2 rounded"
          >
            Excel
          </button>

          <button
            onClick={exportPdf}
            className="bg-red-600 text-white px-4 py-2 rounded"
          >
            PDF
          </button>
        </div>

      </div>

      <div className="bg-white rounded-xl shadow overflow-hidden">

        <table className="w-full text-sm">

          <thead className="bg-gray-50">
            <tr>
              <th className="p-4 text-left">Producto</th>
              <th className="p-4 text-left">Código</th>
              <th className="text-center">Cantidad</th>
              <th className="text-center">Precio</th>
              <th className="text-center">Total</th>
            </tr>
          </thead>

          <tbody>

            {budget.items.map(item => (
              <tr key={item.id} className="border-t">

                <td className="p-4">
                  {item.product.name}
                </td>

                <td className="text-center">
                  {item.quantity}
                </td>

                <td className="text-center">
                  € {item.unitPrice}
                </td>

                <td className="text-center font-semibold">
                  € {item.total}
                </td>

              </tr>
            ))}

          </tbody>

        </table>

      </div>

      <div className="text-right mt-4 text-xl font-bold text-green-600">
        TOTAL: € {budget.total}
      </div>

    </div>
  );
}