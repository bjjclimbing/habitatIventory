import { useState } from "react";
import { api } from "./api";

export default function ImportPage() {
  const [file, setFile] = useState(null);
  const [type, setType] = useState("purchases");
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");
  const [success, setSuccess] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!file) {
      setMessage("Selecciona un fichero");
      setSuccess(false);
      return;
    }

    const formData = new FormData();
    formData.append("file", file);

    setLoading(true);
    setMessage("");
    setSuccess(null);

    try {
      await api.post(`/import/${type}`, formData, {
        headers: { "Content-Type": "multipart/form-data" }
      });

      setMessage("Importación completada correctamente");
      setSuccess(true);
      setFile(null); // 🔥 limpiar file

      // reset input file visual
      document.getElementById("fileInput").value = "";

    } catch (err) {
      console.error(err);
      setMessage("Error en la importación");
      setSuccess(false);
    }

    setLoading(false);
  };

  return (
    <div className="flex justify-center items-center min-h-[60vh]">

      <div className="bg-white p-6 rounded-xl shadow-md w-full max-w-md">

        <h2 className="text-xl font-bold mb-4">
          📤 Importar CSV
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">

          {/* tipo */}
          <select
            value={type}
            onChange={(e) => setType(e.target.value)}
            className="w-full border px-4 py-2 rounded-lg"
          >
            <option value="purchases">Compras</option>
            <option value="sales">Ventas</option>
          </select>

          {/* file */}
          <input
            id="fileInput"
            type="file"
            accept=".csv"
            onChange={(e) => setFile(e.target.files[0])}
            className="w-full text-sm"
          />

          {/* botón */}
          <button
            type="submit"
            disabled={loading}
            className={`w-full text-white py-2 rounded-lg transition ${
              loading
                ? "bg-gray-400"
                : "bg-green-600 hover:bg-green-700"
            }`}
          >
            {loading ? "Subiendo..." : "Importar"}
          </button>

        </form>

        {/* mensaje */}
        {message && (
          <div
            className={`mt-4 text-sm p-3 rounded-lg ${
              success === true
                ? "bg-green-100 text-green-700"
                : success === false
                ? "bg-red-100 text-red-700"
                : "bg-gray-100"
            }`}
          >
            {message}
          </div>
        )}

      </div>

    </div>
  );
}