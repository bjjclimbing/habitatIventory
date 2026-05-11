import { useEffect, useState } from "react";
import { api } from "./api";
import { useParams } from "react-router-dom";

export default function BudgetPage() {

    const { id } = useParams();

    const [products, setProducts] = useState([]);
    const [items, setItems] = useState([]);
    const [name, setName] = useState("Nuevo presupuesto");
    const [loading, setLoading] = useState(false);

    // =========================
    // LOAD PRODUCTS (SEARCH)
    // =========================
    const loadProducts = async (query = "") => {
        try {
            setLoading(true);

            const res = await api.get(`/products?name=${query}&limit=20`);
            setProducts(res.data.data || []);

        } catch (e) {
            console.error("Error loading products", e);
        } finally {
            setLoading(false);
        }
    };

    // =========================
    // LOAD BUDGET (EDIT)
    // =========================
    const loadBudget = async () => {
        try {
            const res = await api.get(`/budgets/${id}`);

            setName(res.data.name);

            setItems(
                res.data.items.map(i => ({
                    productId: i.product.id,
                    name: i.product.name,
                    quantity: i.quantity,
                    unitPrice: i.unitPrice
                }))
            );

        } catch (e) {
            console.error(e);
        }
    };

    useEffect(() => {
        if (id) {
            loadBudget();
        } else {
            loadProducts();
        }
    }, [id]);

    // =========================
    // ADD ITEM
    // =========================
    const addItem = () => {
        setItems([
            ...items,
            {
                productId: "",
                name: "",
                quantity: 1,
                unitPrice: 0
            }
        ]);
    };

    // =========================
    // REMOVE ITEM
    // =========================
    const removeItem = (index) => {
        const updated = [...items];
        updated.splice(index, 1);
        setItems(updated);
    };

    // =========================
    // CHANGE QTY
    // =========================
    const handleQuantityChange = (value, index) => {
        const updated = [...items];
        updated[index].quantity = parseInt(value) || 0;
        setItems(updated);
    };

    // =========================
    // TOTAL
    // =========================
    const total = items.reduce(
        (sum, i) => sum + (i.quantity * i.unitPrice),
        0
    );

    // =========================
    // SAVE
    // =========================
    const saveBudget = async () => {
        try {

            const payload = {
                name,
                items: items.map(i => ({
                    productId: i.productId,
                    quantity: i.quantity
                }))
            };

            if (id) {
                await api.put(`/budgets/${id}`, payload);
            } else {
                await api.post(`/budgets`, payload);
            }

            alert("✅ Guardado");

        } catch (e) {
            console.error(e);
            alert("❌ Error");
        }
    };

    // =========================
    // UI
    // =========================
    return (
        <div style={{ padding: 20 }}>

            <h2>{id ? "Editar Presupuesto" : "Crear Presupuesto"}</h2>

            <input
                value={name}
                onChange={(e) => setName(e.target.value)}
                style={{ marginBottom: 20, width: 300 }}
            />

            <table border="1" cellPadding="10" style={{ width: "100%" }}>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    {items.map((item, index) => (

                        <tr key={index}>

                            {/* 🔥 AUTOCOMPLETE */}
                            <td style={{ position: "relative" }}>

                                <input
                                    placeholder="Buscar producto..."
                                    value={item.name}
                                    onChange={(e) => {
                                        const value = e.target.value;

                                        const updated = [...items];
                                        updated[index].name = value;
                                        updated[index].productId = "";
                                        updated[index].unitPrice = 0;

                                        setItems(updated);
                                        loadProducts(value);
                                    }}
                                    style={{ width: "250px" }}
                                />

                                {/* DROPDOWN */}
                                {products.length > 0 && item.name && (
                                    <div style={{
                                        position: "absolute",
                                        background: "white",
                                        border: "1px solid #ccc",
                                        width: "250px",
                                        zIndex: 10,
                                        maxHeight: "200px",
                                        overflowY: "auto"
                                    }}>
                                        {products.map(p => (
                                            <div
                                                key={p.id}
                                                style={{
                                                    padding: "5px",
                                                    cursor: "pointer"
                                                }}
                                                onClick={() => {

                                                    const updated = [...items];

                                                    updated[index] = {
                                                        ...updated[index],
                                                        productId: p.id,
                                                        name: p.name,
                                                        unitPrice: p.price || 0
                                                    };

                                                    setItems(updated);
                                                    setProducts([]); // cerrar dropdown
                                                }}
                                            >
                                                {p.name} ({p.price ?? 0}€)
                                            </div>
                                        ))}
                                    </div>
                                )}

                            </td>

                            <td>
                                <input
                                    type="number"
                                    value={item.quantity}
                                    onChange={(e) => handleQuantityChange(e.target.value, index)}
                                    style={{ width: 60 }}
                                />
                            </td>

                            <td>
                                {item.unitPrice.toFixed(2)} €
                            </td>

                            <td>
                                {(item.quantity * item.unitPrice).toFixed(2)} €
                            </td>

                            <td>
                                <button onClick={() => removeItem(index)}>❌</button>
                            </td>

                        </tr>
                    ))}
                </tbody>
            </table>

            <br />

            <button onClick={addItem}>➕ Añadir producto</button>

            <h3>Total: {total.toFixed(2)} €</h3>

            <button onClick={saveBudget} style={{ marginTop: 20 }}>
                💾 Guardar
            </button>

        </div>
    );
}