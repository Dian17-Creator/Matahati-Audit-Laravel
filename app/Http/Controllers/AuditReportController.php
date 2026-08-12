<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditCreateRequest;
use App\Http\Requests\AuditDeletePhotoRequest;
use App\Http\Requests\AuditDeleteRequest;
use App\Http\Requests\AuditDetailRequest;
use App\Http\Requests\AuditListRequest;
use App\Http\Requests\AuditSubmitRequest;
use App\Http\Requests\AuditUpdateAnswersRequest;
use App\Http\Requests\AuditUpdatePhotoRequest;
use App\Http\Requests\AuditUploadPhotoRequest;
use App\Models\MauditAudit;
use App\Models\MauditFoto;
use App\Models\MauditResponses;
use App\Services\AuditReportService;
use App\Services\ImageUploadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;

class AuditReportController extends Controller
{
    protected $auditService;
    protected $imageService;

    public function __construct(AuditReportService $auditService, ImageUploadService $imageService)
    {
        $this->auditService = $auditService;
        $this->imageService = $imageService;
    }

    /**
     * Tampilkan list audit
     */
    public function index(AuditListRequest $request)
    {
        try {
            $data = $this->auditService->getList(
                $request->department_id,
                $request->date_from,
                $request->date_to,
                $request->page ? 15 : 1000 // if page is provided paginate by 15
            );

            return response()->json([
                'success' => true,
                'message' => 'Data histori audit berhasil diambil.',
                'data' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tampilkan detail audit beserta hirarki
     */
    public function show(AuditDetailRequest $request)
    {
        try {
            $data = $this->auditService->getDetail($request->id);

            return response()->json([
                'success' => true,
                'message' => 'Detail audit berhasil diambil.',
                'data' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export audit report to printable HTML/PDF
     */
    public function exportPdf(int $id)
    {
        try {
            $data = $this->auditService->getDetail($id);
            $pdf = Pdf::loadView('audit.pdf-report', $data);

            // Render DOMPDF terlebih dahulu sebelum memanipulasi canvas
            $pdf->render();

            // Inject penomoran halaman menggunakan Canvas (Fix bug counter(pages) = 0)
            $canvas = $pdf->getDomPDF()->getCanvas();
            // $canvas->page_text(
            //     530,    // Posisi X (Pojok kanan bawah)
            //     815,    // Posisi Y (Bottom margin)
            //     "{PAGE_NUM} / {PAGE_COUNT}",
            //     null,   // Font default
            //     8,      // Ukuran 8pt
            //     [0.27, 0.27, 0.27] // Warna #444
            // );

            return $pdf->download('audit_' . $data['audit']['document_id'] . '.pdf');
        } catch (Exception $e) {
            return response("Gagal me-render laporan: " . $e->getMessage(), 500);
        }
    }

    /**
     * Mulai audit baru
     */
    public function store(AuditCreateRequest $request)
    {
        try {
            $auditorId = $request->auditor_id ?? (Auth::id() ?? 1);

            $audit = $this->auditService->startAudit($request->department_id, $auditorId);

            return response()->json([
                'success' => true,
                'message' => 'Dokumen Audit berhasil dibuat.',
                'data' => [
                    'id' => $audit->nid,
                    'document_id' => $audit->cdocid,
                    'status' => $audit->cstatus
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update/Sync jawaban secara bulk
     */
    public function updateAnswers(AuditUpdateAnswersRequest $request)
    {
        try {
            $this->auditService->updateAnswers($request->audit_id, $request->answers);

            return response()->json([
                'success' => true,
                'message' => 'Jawaban berhasil disimpan sementara.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Final Submit Audit
     */
    public function submit(AuditSubmitRequest $request)
    {
        try {
            $audit = MauditAudit::findOrFail($request->audit_id);
            $cdocid = $audit->cdocid;

            $photo = $request->file('verification_photo');
            $extension = 'jpg'; // We can enforce jpg or use client extension
            $filename = $cdocid . '_verification.' . $extension;

            $uploadDir = public_path('uploads/' . $cdocid);
            if (!File::isDirectory($uploadDir)) {
                File::makeDirectory($uploadDir, 0775, true, true);
            }

            $absolutePath = $uploadDir . '/' . $filename;
            $relativePath = 'uploads/' . $cdocid . '/' . $filename;

            // Pindahkan file photo asli. (Jika perlu di resize, gunakan ImageUploadService)
            // Sistem lama menggunakan move_uploaded_file tanpa resize untuk verification.
            $photo->move($uploadDir, $filename);

            $result = $this->auditService->submitAudit(
                $request->audit_id,
                $request->auditee_name,
                $relativePath
            );

            return response()->json([
                'success' => true,
                'message' => 'Audit selesai.',
                'data' => [
                    'final_score' => $result->ntotnilai,
                    'percentage' => $result->npersen
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Upload foto temuan
     */
    public function uploadPhoto(AuditUploadPhotoRequest $request)
    {
        try {
            $responseId = $request->response_id;

            return DB::transaction(function () use ($request, $responseId) {
                $responseInfo = MauditResponses::with(['audit', 'question'])->findOrFail($responseId);
                $audit = $responseInfo->audit;
                $question = $responseInfo->question;

                $photoCount = MauditFoto::where('nid_resp', $responseId)->count();
                if ($photoCount >= 10) {
                    throw new Exception("Maksimal 10 foto.");
                }

                $nextSequence = MauditFoto::where('nid_resp', $responseId)->max('nsequence') + 1;

                $uploadDir = public_path('uploads/' . $audit->cdocid);
                if (!File::isDirectory($uploadDir)) {
                    File::makeDirectory($uploadDir, 0775, true, true);
                }

                $filename = $audit->cdocid . '_' . $question->nid_kat . '_' . $question->nid . '_' . $nextSequence . '.jpg';
                $absolutePath = $uploadDir . '/' . $filename;
                $relativePath = 'uploads/' . $audit->cdocid . '/' . $filename;

                // Menggunakan ImageUploadService (resize, fix exif, compress)
                $this->imageService->optimizeAndSave(
                    $request->file('photo')->getRealPath(),
                    $absolutePath,
                    $request->file('photo')->getMimeType()
                );

                $photo = MauditFoto::create([
                    'nid_resp' => $responseId,
                    'nsequence' => $nextSequence,
                    'cket' => $request->cket,
                    'caction' => $request->caction,
                    'cphoto_path' => $relativePath,
                    'uploaded_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Upload berhasil.',
                    'data' => [
                        'id' => $photo->nid,
                        'photo_path' => asset($relativePath)
                    ]
                ]);
            });
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update detail foto temuan (Hasil Pengamatan & Rekomendasi)
     */
    public function updatePhoto(AuditUpdatePhotoRequest $request)
    {
        try {
            $photo = MauditFoto::findOrFail($request->id);

            $photo->update([
                'cket' => $request->observation,
                'caction' => $request->recommendation
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Detail foto berhasil diperbarui.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui detail foto: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Hapus foto temuan
     */
    public function deletePhoto(AuditDeletePhotoRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $photo = MauditFoto::with('response.audit')->findOrFail($request->photo_id);

                if ($photo->response->audit->cstatus === 'Submitted') {
                    throw new Exception("Audit telah di-submit, foto tidak bisa dihapus.");
                }

                $absolutePath = public_path($photo->cphoto_path);
                if (File::exists($absolutePath)) {
                    File::delete($absolutePath);
                }

                $photo->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Foto berhasil dihapus.'
                ]);
            });
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete audit
     */
    public function destroy(AuditDeleteRequest $request)
    {
        try {
            $this->auditService->deleteAudit($request->id);

            return response()->json([
                'success' => true,
                'message' => 'Dokumen audit berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
