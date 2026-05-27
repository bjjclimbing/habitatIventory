import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
    CartesianGrid,
  } from "recharts";
  
  export default function BatchChart({ batches }) {
    if (!batches || batches.length === 0) {
      return <p className="text-gray-400">Sin datos de batches</p>;
    }
  
    // 🔥 preparar datos
    const data = [...batches]
      .filter(b => b.expirationDate)
      .sort((a, b) => new Date(a.expirationDate) - new Date(b.expirationDate))
      .map(b => ({
        date: new Date(b.expirationDate).toLocaleDateString(),
        quantity: b.quantity ?? 0,
      }));
  
    return (
      <div className="bg-white rounded-xl shadow p-4 mt-6">
        <h2 className="font-semibold mb-4">
          Evolución de stock por caducidad
        </h2>
  
        <ResponsiveContainer width="100%" height={250}>
          <LineChart data={data}>
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
      </div>
    );
  }