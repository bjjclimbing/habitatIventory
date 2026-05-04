import { useEffect, useState } from "react";
import { api } from "./api";
import { Link } from "react-router-dom";

export default function ValijasList() {
  const [valijas, setValijas] = useState([]);

  useEffect(() => {
    load();
  }, []);

  const load = async () => {
    try {
      const res = await api.get("/valijas");
      setValijas(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <div className="max-w-5xl mx-auto p-6">

      <h2 className="text-2xl font-bold mb-6">
        📦 Valijas
      </h2>

      <div className="bg-white rounded-xl shadow overflow-hidden">

        <table className="w-full text-sm">

          <thead className="bg-gray-50 text-gray-600">
            <tr>
              <th className="text-left p-4">Nombre</th>
              <th className="text-center">Acciones</th>
            </tr>
          </thead>

          <tbody>
            {valijas.length === 0 && (
              <tr>
                <td colSpan="2" className="p-4 text-center text-gray-500">
                  No hay valijas
                </td>
              </tr>
            )}

            {valijas.map(v => (
              <tr key={v.id} className="border-t hover:bg-gray-50">

                <td className="p-4 font-medium">
                  {v.name}
                </td>

                <td className="text-center">
                  <Link
                    to={`/valijas/${v.id}`}
                    className="text-blue-600 hover:underline"
                  >
                    Configurar
                  </Link>
                </td>

              </tr>
            ))}
          </tbody>

        </table>

      </div>

    </div>
  );
}