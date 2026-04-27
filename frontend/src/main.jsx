import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter, Routes, Route } from "react-router-dom";

import App from "./App";
import ProductDetail from "./ProductDetail";
import ProviderDetail from "./ProviderDetail";
import Dashboard from "./Dashboard";

import "./index.css"; // si usas Tailwind

ReactDOM.createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<App />} />
        <Route path="/products/:id" element={<ProductDetail />} />
        <Route path="/providers/:id" element={<ProviderDetail />} />
        <Route path="/dashboard" element={<Dashboard />} />
      </Routes>
    </BrowserRouter>
  </React.StrictMode>
);