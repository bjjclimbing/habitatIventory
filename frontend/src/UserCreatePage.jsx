import { useState } from "react";
import { api } from "./api";

export default function UserCreatePage() {

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [roles, setRoles] = useState(["ROLE_USER"]);

  const handleRoleChange = (role) => {
    if (roles.includes(role)) {
      setRoles(roles.filter(r => r !== role));
    } else {
      setRoles([...roles, role]);
    }
  };

  const saveUser = async () => {
    try {
      await api.post("/users", {
        email,
        password,
        roles
      });

      alert("✅ Usuario creado");

      setEmail("");
      setPassword("");
      setRoles(["ROLE_USER"]);

    } catch (e) {
      console.error(e);
      alert("❌ Error creando usuario");
    }
  };

  return (
    <div className="max-w-xl mx-auto p-6">

      <h2 className="text-2xl font-semibold mb-6">
        Crear Usuario
      </h2>

      {/* EMAIL */}
      <div className="mb-4">
        <label className="block text-sm text-gray-500 mb-1">
          Email
        </label>
        <input
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="w-full border rounded-lg px-4 py-2"
        />
      </div>

      {/* PASSWORD */}
      <div className="mb-4">
        <label className="block text-sm text-gray-500 mb-1">
          Password
        </label>
        <input
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="w-full border rounded-lg px-4 py-2"
        />
      </div>

      {/* ROLES */}
      <div className="mb-6">
        <label className="block text-sm text-gray-500 mb-2">
          Roles
        </label>

        <div className="flex gap-4">

          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              checked={roles.includes("ROLE_USER")}
              onChange={() => handleRoleChange("ROLE_USER")}
            />
            USER
          </label>

          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              checked={roles.includes("ROLE_ADMIN")}
              onChange={() => handleRoleChange("ROLE_ADMIN")}
            />
            ADMIN
          </label>

        </div>
      </div>

      <button
        onClick={saveUser}
        className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700"
      >
        Crear usuario
      </button>

    </div>
  );
}