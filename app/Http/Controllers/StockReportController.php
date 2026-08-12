<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use App\Services\StockReportService;

class StockReportController extends Controller
{
    protected $stockService;

    public function __construct(StockReportService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Tampilkan list / histori stok opname
     */
    public function index(Request $request)
    {
        $request->validate([
            'department_id' => 'nullable|integer|exists:mdepartment,nid',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'page' => 'nullable|integer|min:1',
        ]);

        try {
            $auditorId = $request->input('auditor_id') ?? $request->input('nid_auditor');

            if ($auditorId === null) {
                Log::warning('StockReportController@index: auditor_id tidak ditemukan di request.', [
                    'all_input' => $request->all()
                ]);
            }

            $data = $this->stockService->getList(
                $request->input('department_id'),
                $request->input('date_from'),
                $request->input('date_to'),
                $auditorId,
                $request->input('page')
            );

            return response()->json([
                'success' => true,
                'message' => 'Data histori stok opname berhasil diambil.',
                'data' => $data
            ]);
        } catch (Exception $e) {
            Log::error('Gagal mengambil histori stok opname', [
                'user_id' => $request->input('auditor_id'),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data histori stok opname.'
            ], 500);
        }
    }

    /**
     * Start / Get Active Stock Opname
     */
    public function store(Request $request)
    {
        $request->validate([
            'department_id' => 'required|integer|exists:mdepartment,nid'
        ]);

        try {
            // Cek auditor_id atau nid_auditor dari request
            $auditorId = $request->input('auditor_id') ?? $request->input('nid_auditor');

            if ($auditorId === null) {
                $auditorId = \Illuminate\Support\Facades\Auth::id();
            }

            if ($auditorId === null) {
                Log::error('StockReportController@store: auditor_id tidak ditemukan.', [
                    'all_input' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi auditor tidak valid atau ID Auditor tidak terkirim.'
                ], 400);
            }

            $departmentId = $request->department_id;

            $data = $this->stockService->startStockOpname($departmentId, $auditorId);

            $message = $data['is_existing'] ? 'Existing audit found.' : 'Dokumen stok opname berhasil dibuat.';
            $status = $data['is_existing'] ? 200 : 201;

            unset($data['is_existing']);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data
            ], $status);
        } catch (Exception $e) {
            $code = $e->getCode();
            $code = ($code >= 400 && $code < 600) ? $code : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    /**
     * Load Detail Stock Opname
     */
    public function show($id)
    {
        try {
            $data = $this->stockService->getDetail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail stok opname berhasil diambil.',
                'data' => $data
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stok opname tidak ditemukan atau Anda tidak memiliki akses.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export stock opname report to printable HTML/PDF
     */
    public function exportPdf(int $id)
    {
        try {
            $data = $this->stockService->getDetail($id);

            // Render view khusus stok opname, pastikan file 'resources/views/stock/pdf-report.blade.php' sudah disiapkan
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('stock.pdf-report', $data);

            // Render DOMPDF terlebih dahulu sebelum memanipulasi canvas
            $pdf->render();

            // Inject penomoran halaman menggunakan Canvas (Fix bug counter(pages) = 0)
            $canvas = $pdf->getDomPDF()->getCanvas();
            $canvas->page_text(
                530,    // Posisi X (Pojok kanan bawah)
                815,    // Posisi Y (Bottom margin)
                "Halaman {PAGE_NUM} / {PAGE_COUNT}",
                null,   // Font default
                8,      // Ukuran 8pt
                [0.27, 0.27, 0.27] // Warna #ffffffff
            );

            return $pdf->download('stok_opname_' . $data['header']['document_id'] . '.pdf');
        } catch (Exception $e) {
            return response("Gagal me-render laporan: " . $e->getMessage(), 500);
        }
    }

    /**
     * Autosave Stock Item
     */
    public function updateAnswers(Request $request)
    {
        $request->validate([
            'audit_id' => 'required|integer',
            'item_id' => 'required|integer',
            'qty_stock' => 'nullable|numeric|min:0',
            'qty_real' => 'nullable|numeric|min:0',
            'remark' => 'nullable|string|max:255'
        ]);

        try {
            $qtyStock = $request->has('qty_stock') ? $request->qty_stock : null;
            $qtyReal = $request->has('qty_real') ? $request->qty_real : null;
            $remark = $request->has('remark') ? $request->remark : null;

            $this->stockService->updateAnswer(
                $request->audit_id,
                $request->item_id,
                $qtyStock,
                $qtyReal,
                $remark
            );

            return response()->json([
                'success' => true,
                'message' => 'Response updated.'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resource tidak ditemukan atau Anda tidak memiliki akses.'
            ], 404);
        } catch (Exception $e) {
            $code = $e->getCode();
            $code = ($code >= 400 && $code < 600) ? $code : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    /**
     * Upload Photo
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'response_id' => 'required|integer',
            'photo' => 'required|image|max:10240', // max 10MB
            'remark' => 'nullable|string|max:255'
        ]);

        try {
            $data = $this->stockService->uploadPhoto(
                $request->response_id,
                $request->file('photo'),
                $request->remark
            );

            return response()->json([
                'success' => true,
                'message' => 'Upload berhasil.',
                'data' => $data
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resource tidak ditemukan atau Anda tidak memiliki akses.'
            ], 404);
        } catch (Exception $e) {
            $code = $e->getCode();
            $code = ($code >= 400 && $code < 600) ? $code : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    /**
     * Update Photo Remark
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo_id' => 'required|integer',
            'remark' => 'nullable|string|max:255'
        ]);

        try {
            $this->stockService->updatePhoto(
                $request->photo_id,
                $request->remark
            );

            return response()->json([
                'success' => true,
                'message' => 'Detail foto berhasil diperbarui.'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resource tidak ditemukan atau Anda tidak memiliki akses.'
            ], 404);
        } catch (Exception $e) {
            $code = $e->getCode();
            $code = ($code >= 400 && $code < 600) ? $code : 400;
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui detail foto: ' . $e->getMessage()
            ], $code);
        }
    }

    /**
     * Delete Photo
     */
    public function deletePhoto(Request $request)
    {
        $request->validate([
            'photo_id' => 'required|integer'
        ]);

        try {
            $this->stockService->deletePhoto($request->photo_id);

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil dihapus.'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resource tidak ditemukan atau Anda tidak memiliki akses.'
            ], 404);
        } catch (Exception $e) {
            $code = $e->getCode();
            $code = ($code >= 400 && $code < 600) ? $code : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }

    /**
     * Final Submit Stock Opname
     */
    public function submit(Request $request)
    {
        $request->validate([
            'audit_id' => 'required|integer',
            'auditee_name' => 'required|string|max:150',
            'verification_photo' => 'required|image|max:8192' // 8MB
        ]);

        try {
            $data = $this->stockService->submitStockOpname(
                $request->audit_id,
                $request->auditee_name,
                $request->file('verification_photo')
            );

            return response()->json([
                'success' => true,
                'message' => 'Stok opname selesai.',
                'data' => $data
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resource tidak ditemukan atau Anda tidak memiliki akses.'
            ], 404);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (Exception $e) {
            $code = $e->getCode();
            $code = ($code >= 400 && $code < 600) ? $code : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }
}
