import { useEffect, useState } from "react";
import { api } from "./api";

export default function ClientPage() {

  const [clients, setClients] = useState([]);
  const [search, setSearch] = useState("");

  const [form, setForm] = useState({
    name: "",
    rif: "",
    address: "",
    phone: "",
    email: ""
  });

  const [editingId, setEditingId] = useState(null);

  // =========================
  // LOAD
  // =========================
  const load = async (searchValue = "") => {
    try {
      let url = "/clients";

      if (searchValue) {
        url += `?search=${encodeURIComponent(searchValue)}`;
      }

      const res = await api.get(url);
      setClients(res.data);

    } catch (e) {
      console.error("Error loading clients", e);
    }
  };

  useEffect(() => {
    load();
  }, []);

  // =========================
  // SEARCH (debounce)
  // =========================
  useEffect(() => {
    const delay = setTimeout(() => {
      load(search);
    }, 300);

    return () => clearTimeout(delay);
  }, [search]);

  // =========================
  // FORM CHANGE
  // =========================
  const updateForm = (field, value) => {
    setForm({ ...form, [field]: value });
  };

  // =========================
  // CREATE / UPDATE
  // =========================
  const saveClient = async () => {
    try {

      if (!form.name) {
        alert("El nombre es obligatorio");
        return;
      }

      if (editingId) {
        await api.put(`/clients/${editingId}`, form);
      } else {
        await api.post("/clients", form);
      }

      resetForm();
      load();

    } catch (e) {
      console.error(e);
      alert("Error guardando cliente");
    }
  };

  // =========================
  // EDIT
  // =========================
  const editClient = (c) => {
    setForm({
      name: c.name || "",
      rif: c.rif || "",
      address: c.address || "",
      phone: c.phone || "",
      email: c.email || ""
    });

    setEditingId(c.id);
  };

  // =========================
  // DELETE
  // =========================
  const deleteClient = async (id) => {
    if (!confirm("¿Eliminar cliente?")) return;

    try {
      await api.delete(`/clients/${id}`);
      load();
    } catch (e) {
      console.error(e);
      alert("Error eliminando");
    }
  };

  // =========================
  // RESET
  // =========================
  const resetForm = () => {
    setForm({
      name: "",
      rif: "",
      address: "",
      phone: "",
      email: ""
    });

    setEditingId(null);
  };

  return (
    <div className="max-w-7xl mx-auto p-6">

      {/* HEADER */}
      <div className="mb-6">
        <h2 className="text-2xl font-bold text-gray-800">
          🏢 Clientes
        </h2>
        <p className="text-sm text-gray-500">
          Gestión de clientes
        </p>
      </div>

      {/* SEARCH */}
      <div className="mb-4">
        <input
          type="text"
          placeholder="🔍 Buscar cliente..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full border rounded-lg px-4 py-2 shadow-sm"
        />
      </div>

      {/* FORM */}
      <div className="bg-white p-4 rounded-xl shadow mb-6 grid grid-cols-5 gap-3">

        <input
          placeholder="Nombre"
          value={form.name}
          onChange={(e) => updateForm("name", e.target.value)}
          className="border rounded-lg px-3 py-2"
        />

        <input
          placeholder="RIF"
          value={form.rif}
          onChange={(e) => updateForm("rif", e.target.value)}
          className="border rounded-lg px-3 py-2"
        />

        <input
          placeholder="Teléfono"
          value={form.phone}
          onChange={(e) => updateForm("phone", e.target.value)}
          className="border rounded-lg px-3 py-2"
        />

        <input
          placeholder="Email"
          value={form.email}
          onChange={(e) => updateForm("email", e.target.value)}
          className="border rounded-lg px-3 py-2"
        />

        <div className="flex gap-2">
          <button
            onClick={saveClient}
            className="bg-green-600 text-white px-4 py-2 rounded-lg w-full hover:bg-green-700"
          >
            {editingId ? "Actualizar" : "Crear"}
          </button>

          {editingId && (
            <button
              onClick={resetForm}
              className="bg-gray-300 px-3 py-2 rounded-lg"
            >
              ✕
            </button>
          )}
        </div>

        <input
          placeholder="Dirección"
          value={form.address}
          onChange={(e) => updateForm("address", e.target.value)}
          className="border rounded-lg px-3 py-2 col-span-5"
        />

      </div>

      {/* TABLE */}
      <div className="bg-white rounded-xl shadow overflow-hidden">

        <table className="w-full text-sm table-fixed">

          <thead className="bg-gray-50 text-gray-600">
            <tr>
              <th className="p-4 text-left w-48">Nombre</th>
              <th className="p-4 text-left w-32">RIF</th>
              <th className="p-4 text-left">Dirección</th>
              <th className="p-4 text-left w-32">Teléfono</th>
              <th className="p-4 text-left w-48">Email</th>
              <th className="w-24 text-center">Acciones</th>
            </tr>
          </thead>

          <tbody>

            {clients.length === 0 && (
              <tr>
                <td colSpan="6" className="p-4 text-center text-gray-500">
                  No hay clientes
                </td>
              </tr>
            )}

            {clients.map(c => (
              <tr key={c.id} className="border-t hover:bg-gray-50">

                <td className="p-4 font-medium">
                  {c.name}
                </td>

                <td className="p-4 font-mono">
                  {c.rif || "-"}
                </td>

                <td className="p-4 truncate">
                  {c.address || "-"}
                </td>

                <td className="p-4">
                  {c.phone || "-"}
                </td>

                <td className="p-4 truncate">
                  {c.email || "-"}
                </td>

                <td className="text-center">

                  <button
                    onClick={() => editClient(c)}
                    className="text-blue-500 hover:text-blue-700 mr-2"
                  >
                    ✏️
                  </button>

                  <button
                    onClick={() => deleteClient(c.id)}
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