import { Navigate } from "react-router-dom";
import { useAuth } from "./auth/useAuth";
import Layout from "./Layout";
export default function AdminRoute({ children }) {
  const { user } = useAuth();

  // ❌ No logueado
  if (!user) {
    return <Navigate to="/login" />;
  }

  // ❌ No es admin
  if (!user.roles?.includes("ROLE_ADMIN")) {
    return <Navigate to="/" />;
  }

  // ✅ OK
  return <Layout>{children}</Layout>;

}