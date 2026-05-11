import { useEffect, useState } from "react";
import { api } from "./api";
import { Link } from "react-router-dom";

export default function ValijasList() {
  const [valijas, setValijas] = useState([]);
  const [newValija, setNewValija] = useState("");
  const [loadingCreate, setLoadingCreate] = useState(false);
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

  const createValija = async () => {
    if (!newValija.trim()) return;

    setLoadingCreate(true);

    try {
      await api.post("/valijas", {
        name: newValija
      });

      setNewValija("");
      load(); // recargar lista
    } catch (e) {
      console.error(e);
      alert("Error creando maleta");
    }

    setLoadingCreate(false);
  };

  const deleteValija = async (id) => {
    if (!confirm("¿Eliminar maleta completa?")) return;

    try {
      await api.delete(`/valijas/${id}`);
      load();
    } catch (e) {
      console.error(e);
      alert("Error eliminando maleta");
    }
  };
  return (
    <div className="max-w-5xl mx-auto p-6">

      <h2 className="text-2xl font-bold mb-6">
        📦 Maletas
      </h2>

      <div className="bg-white p-4 rounded-xl shadow mb-6 flex gap-3">

        <input
          placeholder="Nombre de la maleta..."
          value={newValija}
          onChange={(e) => setNewValija(e.target.value)}
          className="border rounded-lg px-4 py-2 flex-1"
        />

        <button
          onClick={createValija}
          disabled={loadingCreate}
          className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
        >
          {loadingCreate ? "Creando..." : "➕ Crear"}
        </button>

      </div>
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
                  No hay maletas
                </td>
              </tr>
            )}

            {valijas.map(v => (
              <tr key={v.id} className="border-t hover:bg-gray-50">

                <td className="p-4 font-medium">
                  {v.name}
                </td>

                <td className="text-center space-x-3">

                  <Link
                    to={`/valijas/${v.id}`}
                    className="text-blue-600 hover:underline"
                  >
                    Configurar
                  </Link>

                  <button
                    onClick={() => deleteValija(v.id)}
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

    </div>
  );
}