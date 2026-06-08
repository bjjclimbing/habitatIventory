<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AlertReportExcelGenerator
{
    public function generate(array $grouped): string
    {
        $spreadsheet = new Spreadsheet();

        // =====================
        // RESUMEN
        // =====================

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Resumen');

        $summarySheet->fromArray([
            ['Tipo', 'Cantidad'],
            ['Productos bajo en stock', count($grouped['low_stock'] ?? [])],
            ['Prouctos por expirtar', count($grouped['warning'] ?? [])],
            ['Productos expirados', count($grouped['expired'] ?? [])],
            ['Valija baja en stock', count($grouped['valija_low'] ?? [])],
            ['Valija sin stock', count($grouped['valija_critical'] ?? [])],
            ['Valija con productos por expirar ', count($grouped['valija_expiring'] ?? [])],
            ['Valija con productos expirados', count($grouped['valija_expired'] ?? [])],
        ]);

        // =====================
        // LOW STOCK
        // =====================

        $lowSheet = $spreadsheet->createSheet();
        $lowSheet->setTitle('Productos bajos en stock');

        $lowSheet->fromArray([
            ['SKU', 'Producto', 'Stock', 'Mínimo']
        ]);

        $row = 2;

        foreach ($grouped['low_stock'] ?? [] as $product) {

            $lowSheet->fromArray([
                [
                    $product->getSku(),
                    $product->getName(),
                    $product->getStock(),
                    $product->getMinStock()
                ]
            ], null, "A{$row}");

            $row++;
        }

        // =====================
        // WARNING
        // =====================

        $warningSheet = $spreadsheet->createSheet();
        $warningSheet->setTitle('Warning');

        $warningSheet->fromArray([
            ['SKU', 'Producto', 'Fecha vencimiento']
        ]);

        $row = 2;

        foreach ($grouped['warning'] ?? [] as $item) {

            $warningSheet->fromArray([
                [
                    $item['product']->getSku(),
                    $item['product']->getName(),
                    $item['batch']->getExpirationDate()?->format('Y-m-d')
                ]
            ], null, "A{$row}");

            $row++;
        }

        // =====================
        // EXPIRED
        // =====================

        $expiredSheet = $spreadsheet->createSheet();
        $expiredSheet->setTitle('Productos expirados');

        $expiredSheet->fromArray([
            ['SKU', 'Producto', 'Fecha vencimiento']
        ]);

        $row = 2;

        foreach ($grouped['expired'] ?? [] as $item) {

            $expiredSheet->fromArray([
                [
                    $item['product']->getSku(),
                    $item['product']->getName(),
                    $item['batch']->getExpirationDate()?->format('Y-m-d')
                ]
            ], null, "A{$row}");

            $row++;
        }

        // =====================
        // VALIJA LOW
        // =====================

        $valijaLowSheet = $spreadsheet->createSheet();
        $valijaLowSheet->setTitle('Valija baja en stock');

        $valijaLowSheet->fromArray([
            ['Valija', 'SKU', 'Producto', 'Actual', 'Mínimo']
        ]);

        $row = 2;

        foreach ($grouped['valija_low'] ?? [] as $item) {

            $valijaLowSheet->fromArray([
                [
                    $item['valija']->getName(),
                    $item['product']->getSku(),
                    $item['product']->getName(),
                    $item['current'],
                    $item['min']
                ]
            ], null, "A{$row}");

            $row++;
        }

        // =====================
        // VALIJA CRITICAL
        // =====================

        $valijaCriticalSheet = $spreadsheet->createSheet();
        $valijaCriticalSheet->setTitle('Valija sin stock');

        $valijaCriticalSheet->fromArray([
            ['Valija', 'SKU', 'Producto', 'Actual', 'Mínimo']
        ]);

        $row = 2;

        foreach ($grouped['valija_critical'] ?? [] as $item) {

            $valijaCriticalSheet->fromArray([
                [
                    $item['valija']->getName(),
                    $item['product']->getSku(),
                    $item['product']->getName(),
                    $item['current'],
                    $item['min']
                ]
            ], null, "A{$row}");

            $row++;
        }

        // =====================
        // VALIJA EXPIRING
        // =====================

        $valijaExpiringSheet = $spreadsheet->createSheet();
        $valijaExpiringSheet->setTitle('Valija con productos a expirar');

        $valijaExpiringSheet->fromArray([
            ['Valija', 'SKU', 'Producto', 'Cantidad', 'Vencimiento', 'Días']
        ]);

        $row = 2;

        foreach ($grouped['valija_expiring'] ?? [] as $item) {

            $valijaExpiringSheet->fromArray([
                [
                    $item['valija']->getName(),
                    $item['product']->getSku(),
                    $item['product']->getName(),
                    $item['current'],
                    $item['batch']->getExpirationDate()?->format('Y-m-d'),
                    $item['days'] ?? null
                ]
            ], null, "A{$row}");

            $row++;
        }

        // =====================
        // VALIJA EXPIRED
        // =====================

        $valijaExpiredSheet = $spreadsheet->createSheet();
        $valijaExpiredSheet->setTitle('Valija con productos expirados');

        $valijaExpiredSheet->fromArray([
            ['Valija', 'SKU', 'Producto', 'Cantidad', 'Vencimiento']
        ]);

        $row = 2;

        foreach ($grouped['valija_expired'] ?? [] as $item) {

            $valijaExpiredSheet->fromArray([
                [
                    $item['valija']->getName(),
                    $item['product']->getSku(),
                    $item['product']->getName(),
                    $item['current'],
                    $item['batch']->getExpirationDate()?->format('Y-m-d')
                ]
            ], null, "A{$row}");

            $row++;
        }

        // =====================
        // AUTOSIZE
        // =====================

        foreach ($spreadsheet->getAllSheets() as $sheet) {

            foreach (range('A', 'H') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        // =====================
        // SAVE
        // =====================

        $filename = sys_get_temp_dir() .
            '/inventory_alerts_' .
            date('Ymd_His') .
            '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($filename);

        return $filename;
    }
}