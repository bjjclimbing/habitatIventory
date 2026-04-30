import { useEffect, useState } from "react";
import { api } from "./api";
import { useSearchParams } from "react-router-dom";

export default function AlertsPage() {
    const [alerts, setAlerts] = useState([]);
    const [searchParams] = useSearchParams();

    const type = searchParams.get("type");

    useEffect(() => {
        load();
    }, [type]);

    const load = async () => {
        try {
            const res = await api.get(`/alerts/details?type=${type}`);
            setAlerts(res.data);
        } catch (e) {
            console.error(e);
        }
    };

    const getTitle = () => {
        switch (type) {
            case "valija_critical": return "🔥 Valijas críticas";
            case "valija_low": return "📦 Valijas con bajo stock";
            case "low_stock": return "⚠️ Productos bajo stock";
            case "warning": return "⏳ Próximos a caducar";
            case "expired": return "❌ Productos caducados";
            default: return "Alertas";
        }
    };

    return (
        <div>

            <h2 className="text-xl font-bold mb-4">
                {getTitle()}
            </h2>

            <div className="bg-white rounded-xl shadow overflow-hidden">

                <table className="w-full text-sm">

                    <thead className="bg-gray-50 text-gray-600">
                        <tr>
                            <th className="text-left p-4">Elemento</th>
                            <th className="text-center">Info</th>
                        </tr>
                    </thead>

                    <tbody>

                        {alerts.map((a, i) => (
                            <tr key={i} className="border-t hover:bg-gray-50">

                                <td className="p-4">
                                    {a.product?.name || a.valija?.name}
                                </td>

                                <td className="text-center">

                                    {a.current !== undefined && (
                                        <>
                                            {a.current} / {a.min}
                                        </>
                                    )}

                                    {a.batch && (
                                        <>
                                            {a.batch.expirationDate}
                                        </>
                                    )}
                                    {a.product?.stock !== undefined && (
                                        <>
                                            {a.product.stock} / {a.product.min}
                                        </>
                                    )}
                                </td>

                            </tr>
                        ))}

                    </tbody>

                </table>

            </div>

        </div>
    );
}