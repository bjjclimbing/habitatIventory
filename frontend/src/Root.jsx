import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { useAuth } from "./auth/useAuth";

import App from "./App"; // 👈 tu dashboard actual
import LoginPage from "./auth/LoginPage";

export default function Root() {
  const { token } = useAuth();

  return (
    <BrowserRouter>
      <Routes>

        <Route path="/login" element={<LoginPage />} />

        <Route
          path="/*"
          element={
            token ? <App /> : <Navigate to="/login" />
          }
        />

      </Routes>
    </BrowserRouter>
  );
}