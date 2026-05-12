<?php

class AuditoriaController extends Controller
{
    private function getFilters(): array
    {
        return [
            'tabla' => trim((string) ($_GET['tabla'] ?? '')),
            'accion' => strtoupper(trim((string) ($_GET['accion'] ?? ''))),
            'usuario_id' => trim((string) ($_GET['usuario_id'] ?? '')),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];
    }

    private function eCsv(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return (string) $value;
    }

    private function loadLogs(array $filters, int $limit): array
    {
        $logs = (new AuditLog())->latest($limit);

        return array_values(array_filter($logs, function (array $log) use ($filters): bool {
            if (!empty($filters['tabla']) && strtolower((string) ($log['tabla'] ?? '')) !== strtolower($filters['tabla'])) {
                return false;
            }

            if (!empty($filters['accion']) && strtoupper((string) ($log['accion'] ?? '')) !== strtoupper($filters['accion'])) {
                return false;
            }

            if (!empty($filters['usuario_id']) && (string) ($log['usuario_id'] ?? '') !== (string) ((int) $filters['usuario_id'])) {
                return false;
            }

            $fecha = (string) ($log['fecha'] ?? '');
            if (!empty($filters['fecha_desde']) && $fecha < $filters['fecha_desde'] . ' 00:00:00') {
                return false;
            }

            if (!empty($filters['fecha_hasta']) && $fecha > $filters['fecha_hasta'] . ' 23:59:59') {
                return false;
            }

            return true;
        }));
    }

    public function index(): void
    {
        Auth::requireAuth();
        $filters = $this->getFilters();

        try {
            $logs = $this->loadLogs($filters, 5000);
        } catch (Throwable $e) {
            $logs = [];
        }

        $this->view('auditoria/index', compact('logs', 'filters'), [
            'title' => 'Auditoria del sistema',
        ]);
    }

    public function exportExcel(): void
    {
        Auth::requireAuth();
        $filters = $this->getFilters();
        $logs = $this->loadLogs($filters, 10000);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            set_flash('error', 'No se encontró PhpSpreadsheet para exportar XLSX.');
            redirect('auditoria');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Auditoria');

        $headers = ['Fecha', 'Tabla', 'Accion', 'Usuario', 'IP', 'Registro ID', 'Datos Anteriores', 'Datos Nuevos'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E9ECEF');

        $row = 2;
        foreach ($logs as $log) {
            $sheet->setCellValueExplicit('A' . $row, $this->eCsv($log['fecha'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $row, $this->eCsv($log['tabla'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $row, $this->eCsv($log['accion'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D' . $row, $this->eCsv($log['usuario_id'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E' . $row, $this->eCsv($log['ip'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F' . $row, $this->eCsv($log['registro_id'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('G' . $row, $this->eCsv($log['datos_anteriores'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('H' . $row, $this->eCsv($log['datos_nuevos'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $row++;
        }

        foreach (range('A', 'H') as $columnId) {
            $sheet->getColumnDimension($columnId)->setAutoSize(true);
        }

        $lastDataRow = max(2, $row - 1);
        $sheet->setAutoFilter('A1:H' . $lastDataRow);
        $sheet->freezePane('A2');

        $filename = 'auditoria_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function exportPdf(): void
    {
        Auth::requireAuth();
        $filters = $this->getFilters();
        $logs = $this->loadLogs($filters, 10000);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Auditoria</title>';
        $html .= '<style>body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;padding:14px}';
        $html .= 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:6px;vertical-align:top}';
        $html .= 'th{background:#f5f5f5}h1{font-size:18px;margin:0 0 10px}.muted{color:#666;font-size:10px}</style>';
        $html .= '</head><body>';
        $html .= '<h1>Auditoria del sistema</h1>';
        $html .= '<p class="muted">Generado: ' . htmlspecialchars((string) date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<table><thead><tr><th>Fecha</th><th>Tabla</th><th>Accion</th><th>Usuario</th><th>IP</th><th>Registro</th></tr></thead><tbody>';

        if (empty($logs)) {
            $html .= '<tr><td colspan="6">Sin registros</td></tr>';
        } else {
            foreach ($logs as $log) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars((string) ($log['fecha'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($log['tabla'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($log['accion'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($log['usuario_id'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars((string) ($log['ip'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>#' . htmlspecialchars((string) ($log['registro_id'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></body></html>';

        if (!class_exists('Dompdf\\Dompdf')) {
            header('Content-Type: text/html; charset=UTF-8');
            echo '<h3>No se encontro Dompdf.</h3><p>Instala dependencias con Composer.</p>';
            exit;
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'auditoria_' . date('Ymd_His') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    }
}
