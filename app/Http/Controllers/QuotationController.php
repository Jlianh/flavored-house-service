<?php

namespace App\Http\Controllers;

use App\Services\EmailService;
use App\Services\PdfService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles all /api/quotation/* endpoints.
 * Mirrors the original Node.js quotation.js router.
 */
class QuotationController extends Controller
{
    public function __construct(
        private PdfService   $pdfService,
        private EmailService $emailService,
    ) {}

    // ── POST /api/quotation ───────────────────────────────────────────────────

    public function create(Request $request): JsonResponse|Response
    {
        $error = $this->validateQuotation($request->all());
        if ($error) {
            return response()->json(['error' => $error], 400);
        }

        $data            = $request->all();
        $quotationNumber = 'COT-' . now()->valueOf();
        $data['quotationNumber'] = $quotationNumber;
        $data['createdAt'] = $data['createdAt'] ?? now()->toDateString();

        try {
            $pdfBytes = $this->pdfService->generateQuotationPDF($data);
        } catch (Exception $e) {
            return response()->json(['error' => 'PDF generation failed: ' . $e->getMessage()], 500);
        }

        try {
            $this->emailService->sendEmailWithAttachment([
                'to'      => ['vendedor@lacasitadelsabor.com', 'lacasitadelsabor@yahoo.com'],
                'subject' => "Tu cotizacion {$quotationNumber}",
                'html'    => $this->quotationEmailHtml($data['clientName'], count($data['quotationItems']), $quotationNumber),
                'attachments' => [[
                    'filename'    => "{$quotationNumber}.pdf",
                    'content'     => $pdfBytes,
                    'contentType' => 'application/pdf',
                ]],
            ], 'seller');
        } catch (Exception $e) {
            // Log but don't fail the request
            logger()->error('[quotation] Email send failed: ' . $e->getMessage());
        }

        if ($request->query('download') === 'true') {
            return response($pdfBytes, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$quotationNumber}.pdf\"",
            ]);
        }

        return response()->json([
            'message'         => "Cotizacion {$quotationNumber} enviada a {$data['clientEmail']}",
            'quotationNumber' => $quotationNumber,
        ]);
    }

    // ── POST /api/quotation/preview ────────────────────────────────────────────

    public function preview(Request $request): JsonResponse|Response
    {
        $error = $this->validateQuotation($request->all());
        if ($error) {
            return response()->json(['error' => $error], 400);
        }

        $data = $request->all();
        $data['quotationNumber'] = 'PREVIEW-' . now()->valueOf();
        $data['createdAt']       = $data['createdAt'] ?? now()->toDateString();

        $pdfBytes = $this->pdfService->generateQuotationPDF($data);

        return response($pdfBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    // ── POST /api/quotation/bill ───────────────────────────────────────────────

    public function createBill(Request $request): JsonResponse|Response
    {
        $error = $this->validateBill($request->all());
        if ($error) {
            return response()->json(['error' => $error], 400);
        }

        $raw          = $request->all();
        $billNumber   = 'REMISION-' . now()->valueOf();
        $computed     = $this->calculateBill($raw);

        $data = array_merge($raw, [
            'billNumber' => $billNumber,
            'billItems'  => $computed['billItems'],
            'subtotal'   => $computed['subtotal'],
            'discount'   => $computed['discount'],
            'totalIva'   => $computed['totalIva'],
            'totalOperation' => $computed['totalOperation'],
            'createdAt'  => $raw['createdAt'] ?? now()->toDateString(),
        ]);

        try {
            $pdfBytes = $this->pdfService->generateBillPDF($data);
        } catch (Exception $e) {
            return response()->json(['error' => 'PDF generation failed: ' . $e->getMessage()], 500);
        }

        $remisionNumber = $raw['remisionNumber'] ?? $billNumber;

        try {
            $this->emailService->sendEmailWithAttachment([
                'to'      => ['remisiones@lacasitadelsabor.com', 'lacasitadelsabor@yahoo.com'],
                'subject' => "Remision {$remisionNumber}",
                'html'    => "<p>Adjunto encontrará la remision {$remisionNumber}.</p>",
                'attachments' => [[
                    'filename'    => "{$remisionNumber}.pdf",
                    'content'     => $pdfBytes,
                    'contentType' => 'application/pdf',
                ]],
            ], 'remission');
        } catch (Exception $e) {
            logger()->error('[bill] Email send failed: ' . $e->getMessage());
        }

        if ($request->query('download') === 'true') {
            return response($pdfBytes, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$remisionNumber}.pdf\"",
            ]);
        }

        return response()->json([
            'message'        => "Remision {$remisionNumber} enviada a {$raw['clientEmail']}",
            'remisionNumber' => $remisionNumber,
            'totals'         => [
                'subtotal'       => $computed['subtotal'],
                'discount'       => $computed['discount'],
                'totalIva'       => $computed['totalIva'],
                'totalOperation' => $computed['totalOperation'],
            ],
        ]);
    }

    // ── POST /api/quotation/bill/preview ──────────────────────────────────────

    public function previewBill(Request $request): JsonResponse|Response
    {
        $error = $this->validateBill($request->all());
        if ($error) {
            return response()->json(['error' => $error], 400);
        }

        $raw      = $request->all();
        $computed = $this->calculateBill($raw);

        $data = array_merge($raw, [
            'billNumber'     => 'PREVIEW-' . now()->valueOf(),
            'billItems'      => $computed['billItems'],
            'subtotal'       => $computed['subtotal'],
            'discount'       => $computed['discount'],
            'totalIva'       => $computed['totalIva'],
            'totalOperation' => $computed['totalOperation'],
            'createdAt'      => $raw['createdAt'] ?? now()->toDateString(),
        ]);

        $pdfBytes = $this->pdfService->generateBillPDF($data);

        return response($pdfBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview-bill.pdf"',
        ]);
    }

    // ── GET /api/quotation/debug-image ────────────────────────────────────────

    public function debugImage(Request $request): JsonResponse
    {
        $imageName = $request->query('imageName');
        if (!$imageName) {
            return response()->json(['error' => 'imageName query param required'], 400);
        }

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $folder    = env('CLOUDINARY_FOLDER', 'spice-products');

        if (!$cloudName) {
            return response()->json(['error' => 'CLOUDINARY_CLOUD_NAME not set'], 500);
        }

        $url = "https://res.cloudinary.com/{$cloudName}/image/upload/{$folder}/{$imageName}";

        try {
            $context = stream_context_create(['http' => ['timeout' => 10]]);
            $data    = file_get_contents($url, false, $context);
            $ok      = $data !== false;
            $bytes   = $ok ? strlen($data) : 0;

            return response()->json([
                'url'         => $url,
                'ok'          => $ok,
                'bytes'       => $bytes,
                'hint'        => $ok
                    ? 'URL is reachable — image can be embedded'
                    : 'URL returned non-200 — check folder name and file upload',
            ]);
        } catch (Exception $e) {
            return response()->json(['url' => $url, 'error' => $e->getMessage()], 500);
        }
    }

    // ── Validation helpers ────────────────────────────────────────────────────

    private function validateQuotation(array $body): ?string
    {
        foreach (['clientName','clientEmail','clientAddress','clientCity','clientId','clientPhone'] as $field) {
            if (empty($body[$field])) return "{$field} is required";
        }

        if (empty($body['quotationItems']) || !is_array($body['quotationItems'])) {
            return 'quotationItems must be a non-empty array';
        }

        foreach ($body['quotationItems'] as $i => $item) {
            if (empty($item['name']))     return "quotationItems[{$i}].name is required";
            if (empty($item['grammage'])) return "quotationItems[{$i}].grammage is required";
            if (!isset($item['quantity'])) return "quotationItems[{$i}].quantity is required";
        }

        return null;
    }

    private function validateBill(array $body): ?string
    {
        foreach (['clientName','clientEmail','clientId','createdBy'] as $field) {
            if (empty($body[$field])) return "{$field} is required";
        }

        if (empty($body['billItems']) || !is_array($body['billItems'])) {
            return 'billItems must be a non-empty array';
        }

        foreach ($body['billItems'] as $i => $item) {
            if (empty($item['name']))          return "billItems[{$i}].name is required";
            if (!isset($item['quantity']))     return "billItems[{$i}].quantity is required";
            if (!isset($item['unitaryPrice'])) return "billItems[{$i}].unitaryPrice is required";
        }

        return null;
    }

    // ── Bill calculation ──────────────────────────────────────────────────────

    private function calculateBill(array $bill): array
    {
        $items          = $bill['billItems']         ?? [];
        $comercialPct   = (float) ($bill['comercialDiscount'] ?? 0);

        $normalizedItems = array_map(function ($item) {
            $qty      = (float) ($item['quantity']     ?? 0);
            $price    = (float) ($item['unitaryPrice'] ?? 0);
            $discPct  = $this->normalizePct($item['discount'] ?? 0);

            $base         = $qty * $price;
            $itemDiscount = $base * $discPct / 100;
            $subtotal     = $base - $itemDiscount;

            return array_merge($item, [
                'subtotal'     => $subtotal,
                'itemDiscount' => $itemDiscount,
            ]);
        }, $items);

        $subtotalItems = array_sum(array_column($normalizedItems, 'subtotal'));
        $discount      = array_sum(array_column($normalizedItems, 'itemDiscount'));

        $subtotal  = $subtotalItems - $comercialPct;
        $totalIva  = $subtotal * 0.19;
        $totalOp   = $subtotal + $totalIva;

        return [
            'billItems'      => $normalizedItems,
            'subtotal'       => $subtotal,
            'discount'       => $discount,
            'totalIva'       => $totalIva,
            'totalOperation' => $totalOp,
        ];
    }

    private function normalizePct(mixed $value): float
    {
        if ($value === null || $value === '') return 0.0;
        $n = (float) $value;
        return $n <= 1 ? $n : $n / 100;
    }

    // ── Email HTML ────────────────────────────────────────────────────────────

    private function quotationEmailHtml(string $clientName, int $itemCount, string $qNum): string
    {
        return <<<HTML
<div style="font-family:sans-serif;max-width:600px;margin:auto;color:#222;">
  <div style="background:#bc1a18;padding:24px 32px;border-radius:6px 6px 0 0;">
    <h1 style="margin:0;color:#fff;font-size:22px;">Cotizacion {$qNum}</h1>
  </div>
  <div style="padding:24px 32px;border:1px solid #eee;border-top:none;border-radius:0 0 6px 6px;">
    <p>Hola <strong>{$clientName}</strong>,</p>
    <p>Adjunto encontraras tu cotizacion con <strong>{$itemCount}</strong> referencia(s).</p>
    <p>Un asesor se pondra en contacto pronto para confirmar disponibilidad y precios.</p>
    <hr style="border:none;border-top:1px solid #eee;margin:20px 0;"/>
    <p style="font-size:12px;color:#999;">Correo generado automaticamente.</p>
  </div>
</div>
HTML;
    }
}
