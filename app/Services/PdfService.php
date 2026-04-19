<?php

namespace App\Services;

use Exception;
use TCPDF;

/**
 * PDF generation service.
 * Mirrors the visual design of the original pdfService.js (pdf-lib).
 *
 * Uses TCPDF — well-supported on shared cPanel hosting.
 *
 * Products images are fetched from Cloudinary (or any full URL).
 */
class PdfService
{
    // ── Colour helpers ────────────────────────────────────────────────────────

    private const RED    = [199,  20,  20];
    private const YELLOW = [250, 209,  25];
    private const DARK   = [ 30,  30,  30];
    private const MID    = [107, 107, 107];
    private const LIGHT  = [250, 250, 250];
    private const WHITE  = [255, 255, 255];
    private const BORDER = [212, 212, 212];

    // ── Image resolution ──────────────────────────────────────────────────────

    private function resolveImageUrl(array $item): ?string
    {
        if (!empty($item['imageUrl'])) {
            return $item['imageUrl'];
        }

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $folder    = env('CLOUDINARY_FOLDER', 'spice-products');

        if ($cloudName && !empty($item['imageName'])) {
            $publicId = preg_replace('/\.[^.]+$/', '', $item['imageName']);
            $fullId   = $folder ? "{$folder}/{$publicId}" : $publicId;
            return "https://res.cloudinary.com/{$cloudName}/image/upload/{$fullId}";
        }

        return null;
    }

    private function fetchImageToTempFile(string $url): ?string
    {
        try {
            $context = stream_context_create([
                'http' => ['timeout' => 10, 'ignore_errors' => true],
                'ssl'  => ['verify_peer' => false],
            ]);
            $data = file_get_contents($url, false, $context);

            if ($data === false || strlen($data) < 100) {
                return null;
            }

            // Detect type from magic bytes
            $isPng = str_starts_with($data, "\x89PNG");
            $ext   = $isPng ? 'png' : 'jpg';

            $tmp = sys_get_temp_dir() . '/spice_img_' . uniqid() . '.' . $ext;
            file_put_contents($tmp, $data);
            return $tmp;
        } catch (Exception) {
            return null;
        }
    }

    // ── Quotation PDF ─────────────────────────────────────────────────────────

    /**
     * Generate a quotation PDF.
     *
     * @param  array $opts  See AuthController for shape.
     * @return string       Raw PDF bytes.
     */
    public function generateQuotationPDF(array $opts): string
    {
        [
            'clientName'     => $clientName,
            'clientCity'     => $clientCity,
            'clientEmail'    => $clientEmail,
            'clientAddress'  => $clientAddress,
            'clientPhone'    => $clientPhone,
            'clientId'       => $clientId,
            'createdAt'      => $createdAt,
            'quotationNumber'=> $quotationNumber,
            'quotationItems' => $items,
        ] = $opts;

        $pdf = $this->makePdf('P', 'Cotización ' . $quotationNumber);

        // ── Pre-fetch images ─────────────────────────────────────────────────
        $tmpFiles = [];
        $imgPaths = [];
        foreach ($items as $i => $item) {
            $url = $this->resolveImageUrl($item);
            if ($url) {
                $path = $this->fetchImageToTempFile($url);
                $imgPaths[$i] = $path;
                if ($path) $tmpFiles[] = $path;
            }
        }

        // ── Page 1 ───────────────────────────────────────────────────────────
        $pdf->AddPage();
        $this->drawQuotationHeader($pdf, $quotationNumber, $createdAt);
        $this->drawClientBlock($pdf, $clientName, $clientCity, $clientId, $clientEmail, $clientPhone, $clientAddress);
        $this->drawQuotationTableHeader($pdf);

        foreach ($items as $i => $item) {
            if ($pdf->GetY() > 252) {
                $pdf->AddPage();
                $this->drawQuotationTableHeader($pdf);
            }
            $this->drawQuotationRow($pdf, $item, $imgPaths[$i] ?? null, $i);
        }

        $this->drawQuotationTotals($pdf, $items);
        $this->drawQuotationNotes($pdf);

        // Cleanup temp files
        foreach ($tmpFiles as $f) { @unlink($f); }

        return $pdf->Output('', 'S');
    }

    // ── Bill (Remision) PDF ───────────────────────────────────────────────────

    /**
     * Generate a remision / bill PDF.
     *
     * @param  array $data  See QuotationController for shape.
     * @return string       Raw PDF bytes.
     */
    public function generateBillPDF(array $data): string
    {
        [
            'clientName'       => $clientName,
            'clientCity'       => $clientCity,
            'clientEmail'      => $clientEmail,
            'clientAddress'    => $clientAddress,
            'clientPhone'      => $clientPhone,
            'clientId'         => $clientId,
            'createdAt'        => $createdAt,
            'createdBy'        => $createdBy,
            'remisionNumber'   => $remisionNumber,
            'cashReceipt'      => $cashReceipt,
            'paymentMethod'    => $paymentMethod,
            'billItems'        => $items,
            'subtotal'         => $subtotal,
            'discount'         => $discount,
            'totalIva'         => $totalIva,
            'totalOperation'   => $totalOperation,
        ] = $data;

        $pdf = $this->makePdf('P', 'Remisión ' . $remisionNumber);
        $pdf->AddPage();

        $this->drawBillHeader($pdf, $remisionNumber, $cashReceipt, $createdAt);
        $this->drawBillClientBlock($pdf, $clientName, $clientCity, $clientId, $clientPhone, $clientAddress, $clientEmail, $createdBy, $paymentMethod);
        $this->drawBillTableHeader($pdf);

        foreach ($items as $item) {
            if ($pdf->GetY() > 252) {
                $pdf->AddPage();
                $this->drawBillTableHeader($pdf);
            }
            $this->drawBillRow($pdf, $item);
        }

        $this->drawBillTotals($pdf, $subtotal, $discount, $totalIva, $totalOperation);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(...self::MID);
        $pdf->SetXY(15, 280);
        $pdf->Cell(0, 5, "Generado por: {$createdBy}");

        return $pdf->Output('', 'S');
    }

    // ── TCPDF factory ─────────────────────────────────────────────────────────

    private function makePdf(string $orientation, string $title): TCPDF
    {
        $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Casita del Sabor API');
        $pdf->SetAuthor('Casita del Sabor');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(false);
        return $pdf;
    }

    // ── Quotation drawing helpers ─────────────────────────────────────────────

    private function drawQuotationHeader(TCPDF $pdf, string $qNum, string $date): void
    {
        // Red header bar
        $pdf->SetFillColor(...self::RED);
        $pdf->Rect(0, 0, 210, 26, 'F');

        // Yellow bottom strip
        $pdf->SetFillColor(...self::YELLOW);
        $pdf->Rect(0, 24, 210, 2, 'F');

        // Logo (base64 embedded)
        $logoPath = public_path('images/logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 160, 4, 35, 0, 'PNG', '', '', false, 150, '', false, false, 0);
        }

        // Title
        $pdf->SetTextColor(...self::WHITE);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetXY(15, 6);
        $pdf->Cell(0, 8, 'COTIZACIÓN', 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(15, 15);
        $pdf->Cell(0, 5, 'N°: ' . $qNum, 0, 0, 'L');

        $pdf->SetXY(100, 15);
        $pdf->Cell(0, 5, $this->formatDate($date), 0, 0, 'R');
    }

    private function drawClientBlock(TCPDF $pdf, string $name, string $city, string $id, string $email, string $phone, string $address): void
    {
        $y = 32;

        $pdf->SetTextColor(...self::DARK);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetXY(15, $y);
        $pdf->Cell(0, 7, $name);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(...self::MID);
        $y += 8;
        $pdf->SetXY(15, $y); $pdf->Cell(0, 5, "Ciudad: {$city}");
        $y += 5;
        $pdf->SetXY(15, $y); $pdf->Cell(0, 5, "C.C | NIT: {$id}");
        $y += 5;
        $pdf->SetXY(15, $y);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(13, 5, 'Email:');
        $pdf->SetFont('helvetica', '', 9);  $pdf->Cell(0, 5, $email);
        $pdf->SetXY(110, $y);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(18, 5, 'Teléfono:');
        $pdf->SetFont('helvetica', '', 9);  $pdf->Cell(0, 5, $phone);
        $y += 5;
        $pdf->SetXY(15, $y);
        $pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(20, 5, 'Dirección:');
        $pdf->SetFont('helvetica', '', 9);  $pdf->Cell(0, 5, $address);

        // Divider
        $pdf->SetDrawColor(...self::BORDER);
        $pdf->Line(15, $y + 7, 195, $y + 7);
    }

    private function drawQuotationTableHeader(TCPDF $pdf): void
    {
        $y = $pdf->GetY() + 10;
        $pdf->SetFillColor(...self::YELLOW);
        $pdf->SetTextColor(...self::DARK);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Rect(15, $y, 180, 8, 'F');

        $pdf->SetXY(87, $y + 1);  $pdf->Cell(65, 6, 'Producto');
        $pdf->SetXY(152, $y + 1); $pdf->Cell(22, 6, 'Gramaje');
        $pdf->SetXY(174, $y + 1); $pdf->Cell(22, 6, 'Cantidad');

        // Red rule below header
        $pdf->SetDrawColor(...self::RED);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, $y + 8, 195, $y + 8);
        $pdf->SetLineWidth(0.2);
        $pdf->SetY($y + 8);
    }

    private function drawQuotationRow(TCPDF $pdf, array $item, ?string $imgPath, int $index): void
    {
        $rowH = 22;
        $y    = $pdf->GetY();

        if ($index % 2 === 0) {
            $pdf->SetFillColor(254, 254, 254);
            $pdf->Rect(15, $y, 180, $rowH, 'F');
        }

        // Product image
        if ($imgPath) {
            try {
                $ext = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION));
                $pdf->Image($imgPath, 16, $y + 1, 18, 18, strtoupper($ext) === 'PNG' ? 'PNG' : 'JPEG');
            } catch (Exception) {
                $this->drawImagePlaceholder($pdf, 16, $y + 1, 18);
            }
        } else {
            $this->drawImagePlaceholder($pdf, 16, $y + 1, 18);
        }

        // Product name
        $pdf->SetTextColor(...self::DARK);
        $pdf->SetFont('helvetica', 'B', 9);
        $name = mb_strlen($item['name']) > 36 ? mb_substr($item['name'], 0, 34) . '…' : $item['name'];
        $pdf->SetXY(37, $y + 4);
        $pdf->Cell(112, 5, $name);

        // Reference
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(...self::MID);
        $pdf->SetXY(37, $y + 10);
        $pdf->Cell(112, 5, 'Ref. #' . ($item['productId'] ?? ''));

        // Grammage
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(...self::DARK);
        $pdf->SetXY(152, $y + 8);
        $pdf->Cell(22, 5, $item['grammage'] ?? '');

        // Quantity
        $pdf->SetTextColor(...self::RED);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(174, $y + 8);
        $pdf->Cell(22, 5, ($item['quantity'] ?? '') . ' uds.');

        // Bottom border
        $pdf->SetDrawColor(...self::BORDER);
        $pdf->Line(15, $y + $rowH, 195, $y + $rowH);
        $pdf->SetY($y + $rowH);
    }

    private function drawImagePlaceholder(TCPDF $pdf, float $x, float $y, float $size): void
    {
        $pdf->SetFillColor(249, 243, 204);
        $pdf->Rect($x, $y, $size, $size, 'F');
        $pdf->SetFillColor(...self::RED);
        $pdf->Rect($x, $y, $size, 4, 'F');
        $pdf->SetTextColor(...self::WHITE);
        $pdf->SetFont('helvetica', '', 4.5);
        $pdf->SetXY($x, $y + 0.5);
        $pdf->Cell($size, 3, 'sin imagen', 0, 0, 'C');
    }

    private function drawQuotationTotals(TCPDF $pdf, array $items): void
    {
        $y = $pdf->GetY() + 6;
        $totalUnits = array_sum(array_column($items, 'quantity'));

        $pdf->SetFillColor(...self::LIGHT);
        $pdf->Rect(15, $y, 180, 16, 'F');
        $pdf->SetFillColor(...self::YELLOW);
        $pdf->Rect(15, $y, 1.5, 16, 'F');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(...self::DARK);
        $pdf->SetXY(18, $y + 2);
        $pdf->Cell(100, 5, 'Total de referencias:');
        $pdf->SetTextColor(...self::RED);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(174, $y + 2);
        $pdf->Cell(22, 5, count($items));

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(...self::DARK);
        $pdf->SetXY(18, $y + 9);
        $pdf->Cell(100, 5, 'Total de unidades:');
        $pdf->SetTextColor(...self::RED);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(174, $y + 9);
        $pdf->Cell(22, 5, $totalUnits);
    }

    private function drawQuotationNotes(TCPDF $pdf): void
    {
        $y = $pdf->GetY() + 14;
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(...self::DARK);
        $pdf->SetXY(15, $y);
        $pdf->Cell(0, 5, 'Notas:');

        $notes = [
            'Los precios no están incluidos en este documento. Un asesor se comunicará con usted.',
            'Esta cotización tiene una vigencia de 15 días hábiles.',
            'Los gramajes y presentaciones están sujetos a disponibilidad de inventario.',
        ];

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(...self::MID);
        foreach ($notes as $note) {
            $y += 5;
            $pdf->SetXY(15, $y);
            $pdf->Cell(0, 5, '- ' . $note);
        }
    }

    // ── Bill drawing helpers ──────────────────────────────────────────────────

    private function drawBillHeader(TCPDF $pdf, string $remision, string $cashReceipt, string $date): void
    {
        $logoPath = public_path('images/logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 5, 30, 0, 'PNG');
        }

        $pdf->SetTextColor(...self::DARK);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(52, 7);
        $pdf->Cell(0, 6, 'LA CASITA DEL SABOR S.A.S');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(130, 7);
        $pdf->Cell(0, 6, "Remisión: {$remision}");
        $pdf->SetXY(130, 13);
        $pdf->Cell(0, 5, "Recibo de caja: {$cashReceipt}");
        $pdf->SetXY(130, 19);
        $pdf->Cell(0, 5, "Fecha: {$date}");
    }

    private function drawBillClientBlock(TCPDF $pdf, ...$fields): void
    {
        [$name, $city, $id, $phone, $address, $email, $createdBy, $paymentMethod] = $fields;

        $y = 30;
        $pdf->SetFillColor(242, 242, 242);
        $pdf->Rect(15, $y, 180, 28, 'F');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(...self::DARK);

        $pairs = [
            ["Cliente: {$name}",         "Ciudad: {$city}"],
            ["NIT | CC: {$id}",          "Dirección: {$address}"],
            ["Teléfono: {$phone}",        "Vendedor: {$createdBy}"],
            ["Email: {$email}",           "Forma de pago: {$paymentMethod}"],
        ];

        foreach ($pairs as $i => [$left, $right]) {
            $pdf->SetXY(17, $y + 4 + ($i * 6));
            $pdf->Cell(80, 5, $left);
            $pdf->SetX(107);
            $pdf->Cell(85, 5, $right);
        }

        $pdf->SetY($y + 32);
    }

    private function drawBillTableHeader(TCPDF $pdf): void
    {
        $y = $pdf->GetY();
        $cols = $this->billCols();
        $headers = ['CÓD.BARRAS','PRODUCTO','GRS.','CANT.','IVA','VR. UNITARIO','DTO.','TOTAL'];

        $pdf->SetFillColor(...self::RED);
        $pdf->SetTextColor(...self::WHITE);
        $pdf->SetFont('helvetica', 'B', 6.5);

        $x = 15;
        foreach ($headers as $i => $h) {
            $pdf->SetXY($x, $y);
            $pdf->Cell($cols[$i], 7, $h, 0, 0, 'L', true);
            $x += $cols[$i];
        }
        $pdf->SetY($y + 7);
    }

    private function drawBillRow(TCPDF $pdf, array $item): void
    {
        $cols = $this->billCols();
        $y    = $pdf->GetY();
        $x    = 15;

        $fmt = fn($n) => '$' . number_format((float) $n, 0, ',', '.');

        $row = [
            $item['code']         ?? '-',
            mb_substr($item['name'] ?? '', 0, 28),
            $item['grammage']     ?? '-',
            (string)($item['quantity'] ?? ''),
            ($item['iva'] ?? '0') . '%',
            $fmt($item['unitaryPrice'] ?? 0),
            number_format((float)($item['discount'] ?? 0), 0) . ' %',
            $fmt($item['subtotal'] ?? 0),
        ];

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(...self::DARK);

        foreach ($row as $i => $val) {
            $pdf->SetXY($x, $y);
            $pdf->Cell($cols[$i], 6, $val, 0, 0, 'L');
            $x += $cols[$i];
        }
        $pdf->SetY($y + 6);
    }

    private function drawBillTotals(TCPDF $pdf, float $subtotal, float $discount, float $totalIva, float $totalOperation): void
    {
        $y   = $pdf->GetY() + 6;
        $fmt = fn($n) => '$' . number_format($n, 0, ',', '.');

        $rows = [
            ['SUBTOTAL',  $fmt($subtotal)],
            ['DESCUENTO', $fmt($discount)],
            ['IVA',       $fmt($totalIva)],
            ['TOTAL',     $fmt($totalOperation)],
        ];

        foreach ($rows as [$label, $value]) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(...self::DARK);
            $pdf->SetXY(130, $y);
            $pdf->Cell(30, 6, $label);

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY(165, $y);
            $pdf->Cell(30, 6, $value);

            $y += 6;
        }
    }

    private function billCols(): array
    {
        return [30, 38, 22, 14, 12, 26, 16, 22];
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    private function formatDate(string $dateStr): string
    {
        try {
            $dt = new \DateTime($dateStr . 'T12:00:00');
            setlocale(LC_TIME, 'es_CO.UTF-8', 'es_CO', 'es');
            return $dt->format('d') . ' de ' . strftime('%B', $dt->getTimestamp()) . ' de ' . $dt->format('Y');
        } catch (Exception) {
            return $dateStr;
        }
    }
}
