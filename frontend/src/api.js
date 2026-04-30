import axios from "axios";

export const api = axios.create({
  baseURL: "/api", // 👈 importante (funciona en dev con proxy y en prod)
});

// 🔐 REQUEST INTERCEPTOR (añade JWT automáticamente)
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("token");

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
  },
  (error) => Promise.reject(error)
);

// 🚨 RESPONSE INTERCEPTOR (maneja expiración / 401)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      // 🔥 token inválido o expirado
      localStorage.removeItem("token");

      // redirigir a login
      window.location.href = "/login";
    }

    return Promise.reject(error);
  }
);