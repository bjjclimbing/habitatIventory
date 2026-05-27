<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AlertMailer
{
    public function __construct(
        private MailerInterface $mailer
    ) {}

    public function sendAlerts(array $grouped): void
    {
        $critical = $grouped['critical'] ?? [];
        $low = $grouped['low_stock'] ?? [];
        $warning = $grouped['warning'] ?? [];
        $expired = $grouped['expired'] ?? [];

        if (empty($critical) && empty($low) && empty($warning) && empty($expired)) {
            return;
        }

        // ======================
        // 🧠 ORDENACIÓN
        // ======================

        usort($critical, fn($a, $b) => $a->getName() <=> $b->getName());

        usort($low, fn($a, $b) => $a->getStock() <=> $b->getStock());

        usort($warning, fn($a, $b) =>
            $a['batch']->getExpirationDate() <=> $b['batch']->getExpirationDate()
        );

        usort($expired, fn($a, $b) =>
            $a['batch']->getExpirationDate() <=> $b['batch']->getExpirationDate()
        );

        // ======================
        // HTML BASE
        // ======================

        $html = '
        <div style="font-family:Arial, sans-serif;background:#f4f6f9;padding:20px;">
          <div style="max-width:900px;margin:auto;background:white;border-radius:12px;padding:24px;">
          
          <h2>📦 Alertas de Inventario</h2>
        ';

        // ======================
        // 📊 RESUMEN
        // ======================

        $html .= '
        <h3>📊 Resumen</h3>
        <table style="width:100%;text-align:center;margin-bottom:20px;">
            <tr>
                <td style="background:#ffe5e5;padding:12px;border-radius:6px;">
                    🔥<br><b>' . count($critical) . '</b><br>Críticos
                </td>
                <td style="background:#fff3cd;padding:12px;border-radius:6px;">
                    ⚠️<br><b>' . count($low) . '</b><br>Bajo stock
                </td>
                <td style="background:#e7f3ff;padding:12px;border-radius:6px;">
                    ⏳<br><b>' . count($warning) . '</b><br>Por caducar
                </td>
                <td style="background:#f8d7da;padding:12px;border-radius:6px;">
                    ❌<br><b>' . count($expired) . '</b><br>Caducados
                </td>
            </tr>
        </table>
        ';

        // ======================
        // 🔥 CRÍTICOS
        // ======================

        if (!empty($critical)) {
            $html .= '<h3 style="color:#d9534f;">🔥 Críticos (' . count($critical) . ')</h3>';
            $html .= $this->renderTable($critical, true);
        }

        // ======================
        // ⚠️ BAJO STOCK
        // ======================

        if (!empty($low)) {
            $html .= '<h3 style="color:#f0ad4e;">⚠️ Bajo stock (' . count($low) . ')</h3>';
            $html .= $this->renderTable($low);
        }

        // ======================
        // ⏳ POR CADUCAR
        // ======================

        if (!empty($warning)) {
            $html .= '<h3 style="color:#0275d8;">⏳ Próximos a caducar (' . count($warning) . ')</h3>';
            $html .= '<table style="width:100%;font-size:13px;">';

            foreach ($warning as $item) {
                $p = $item['product'];
                $date = $item['batch']->getExpirationDate()?->format('Y-m-d');

                $html .= "
                <tr>
                    <td>{$p->getName()}</td>
                    <td style='text-align:center;'>$date</td>
                </tr>";
            }

            $html .= '</table>';
        }

        // ======================
        // ❌ CADUCADOS
        // ======================

        if (!empty($expired)) {
            $html .= '<h3 style="color:#a94442;">❌ Caducados (' . count($expired) . ')</h3>';
            $html .= '<table style="width:100%;font-size:13px;">';

            foreach ($expired as $item) {
                $p = $item['product'];
                $date = $item['batch']->getExpirationDate()?->format('Y-m-d');

                $html .= "
                <tr>
                    <td>{$p->getName()}</td>
                    <td style='text-align:center;'>$date</td>
                </tr>";
            }

            $html .= '</table>';
        }

        // ======================
        // 🧠 RECOMENDACIONES
        // ======================

        $html .= '
        <hr>
        <h3>🧠 Recomendaciones</h3>
        <ul>
            <li>Revisar productos con stock 0 inmediatamente</li>
            <li>Reponer productos bajo mínimo</li>
            <li>Consumir primero productos próximos a caducar</li>
        </ul>
        ';

        $html .= '</div></div>';

        // ======================
        // ENVÍO
        // ======================

        $email = (new Email())
            ->from('inventariodispromed@gmail.com')
            ->to('luis.canelon.a@gmail.com')
            ->subject('📊 Reporte diario de inventario')
            ->html($html);

        $this->mailer->send($email);
    }

    private function renderTable(array $products, bool $critical = false): string
    {
        $html = '
        <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:13px;">
            <thead>
                <tr style="background:#f1f1f1;">
                    <th style="text-align:left;padding:8px;">Producto</th>
                    <th style="text-align:center;">Stock</th>
                    <th style="text-align:center;">Mínimo</th>
                </tr>
            </thead>
            <tbody>
        ';

        foreach ($products as $p) {
            $color = $critical ? '#d9534f' : '#333';

            $html .= '
            <tr>
                <td style="padding:6px;color:' . $color . ';">' . $p->getName() . '</td>
                <td style="text-align:center;color:' . $color . ';">' . $p->getStock() . '</td>
                <td style="text-align:center;">' . $p->getMinStock() . '</td>
            </tr>
            ';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}