<?php

namespace App\Services;

use App\Models\MauditInventory;
use App\Models\MauditInvresp;
use App\Models\MauditInvFoto;
use App\Models\mdepartment;
use App\Models\MauditDeptItem;
use App\Models\MauditItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;

class StockReportService
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function getList(
        ?int $departmentId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $auditorId = null,
        ?int $page = null
    ) {
        $query = MauditInventory::with([
            'department',
            'auditor'
        ])
            ->orderBy('started_at', 'desc');

        if ($auditorId) {
            $query->where('nid_auditor', $auditorId);
        }

        if ($departmentId) {
            $query->where('nid_dept', $departmentId);
        }

        if ($dateFrom) {
            $query->whereDate('daudit', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('daudit', '<=', $dateTo);
        }

        if ($page !== null) {
            $histories = $query->paginate(15, ['*'], 'page', $page);
            $items = $histories->getCollection()->map(function ($audit) {
                return $this->formatHistoryItem($audit);
            });

            return [
                'items' => $items->values(),
                'pagination' => [
                    'current_page' => $histories->currentPage(),
                    'last_page' => $histories->lastPage(),
                    'per_page' => $histories->perPage(),
                    'total' => $histories->total(),
                    'from' => $histories->firstItem(),
                    'to' => $histories->lastItem(),
                ]
            ];
        }

        $histories = $query->limit(1000)->get();
        $items = $histories->map(function ($audit) {
            return $this->formatHistoryItem($audit);
        });

        return [
            'items' => $items->values(),
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $items->count(),
                'total' => $items->count(),
                'from' => $items->isNotEmpty() ? 1 : null,
                'to' => $items->isNotEmpty() ? $items->count() : null,
            ]
        ];
    }

    private function formatHistoryItem($audit)
    {
        return [
            'id' => $audit->nid,
            'document_id' => $audit->cdocid,
            'department_id' => $audit->nid_dept,
            'department_name' => $audit->department ? $audit->department->cname : null,
            'auditor_id' => $audit->nid_auditor,
            'auditor_name' => $audit->auditor ? $audit->auditor->cfullname : null,
            'audit_date' => $audit->daudit ? $audit->daudit->format('Y-m-d') : null,
            'status' => $audit->cstatus,
            'auditee_name' => $audit->cauditee,
            'started_at' => $audit->started_at ? $audit->started_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $audit->updated_at ? $audit->updated_at->format('Y-m-d H:i:s') : null,
            'submitted_at' => $audit->submitted_at ? $audit->submitted_at->format('Y-m-d H:i:s') : null,
        ];
    }

    public function startStockOpname(int $departmentId, int $auditorId)
    {
        return DB::transaction(function () use ($departmentId, $auditorId) {
            $existing = MauditInventory::where('nid_dept', $departmentId)
                ->where('nid_auditor', $auditorId)
                ->where('cstatus', '<>', 'Submitted')
                ->orderBy('started_at', 'desc')
                ->first();

            if ($existing) {
                return [
                    'id' => $existing->nid,
                    'document_id' => $existing->cdocid,
                    'status' => $existing->cstatus,
                    'is_existing' => true
                ];
            }

            $department = mdepartment::findOrFail($departmentId);

            $deptName = strtolower(trim($department->cname));
            $deptName = preg_replace("/[^a-z0-9]+/", "_", $deptName);
            $deptName = trim($deptName, "_");

            $docPrefix = "stok_" . $deptName . "_" . date("Ymd");
            $sequence = MauditInventory::where('cdocid', 'LIKE', $docPrefix . '_%')->count() + 1;
            $cdocid = sprintf("%s_%03d", $docPrefix, $sequence);

            $audit = MauditInventory::create([
                'cdocid' => $cdocid,
                'nid_dept' => $departmentId,
                'nid_auditor' => $auditorId,
                'daudit' => Carbon::today(),
                'cstatus' => 'Draft',
                'started_at' => Carbon::now()
            ]);

            $items = MauditDeptItem::where('nid_dept', $departmentId)->pluck('nid_item');

            if ($items->isEmpty()) {
                throw new Exception("Tidak ada daftar barang untuk departemen ini.");
            }

            $responses = [];
            $now = Carbon::now();
            foreach ($items as $itemId) {
                $responses[] = [
                    'nid_audit' => $audit->nid,
                    'nid_item' => $itemId,
                    'nqty_stock' => null,
                    'nqty_real' => null,
                    'ndiff' => 0,
                    'ndiff_under' => 0,
                    'ndiff_over' => 0,
                    'fna' => 0,
                    'cket' => null,
                    'updated_at' => $now
                ];
            }

            MauditInvresp::insert($responses);

            return [
                'id' => $audit->nid,
                'document_id' => $audit->cdocid,
                'status' => $audit->cstatus,
                'is_existing' => false
            ];
        });
    }

    public function getDetail(int $auditId)
    {
        $audit = MauditInventory::with(['department', 'auditor'])
            ->where('nid', $auditId)
            ->firstOrFail();

        $responses = MauditInvresp::with(['item.group'])
            ->where('nid_audit', $audit->nid)
            ->get();

        $photos = MauditInvFoto::whereIn('nid_resp', $responses->pluck('nid'))
            ->get()
            ->groupBy('nid_resp');

        $categoriesMap = [];

        foreach ($responses as $response) {
            $item = $response->item;
            if (!$item) continue;

            $category = $item->group;
            $categoryId = $category ? $category->nid : 0;
            $categoryName = $category ? $category->cnama : 'Uncategorized';

            if (!isset($categoriesMap[$categoryId])) {
                $categoriesMap[$categoryId] = [
                    'id' => $categoryId,
                    'name' => $categoryName,
                    'items' => []
                ];
            }

            $itemPhotos = isset($photos[$response->nid]) ? $photos[$response->nid]->map(function ($photo) {
                $path = $photo->cphoto_path;
                if (!str_starts_with($path, 'uploads/')) {
                    $path = 'uploads/' . ltrim($path, '/');
                }
                return [
                    'id' => $photo->nid,
                    'response_id' => $photo->nid_resp,
                    'sequence' => $photo->nsequence,
                    'photo_path' => asset($path),
                    'remark' => $photo->cket,
                    'action' => $photo->caction,
                    'uploaded_at' => $photo->uploaded_at ? $photo->uploaded_at->format('Y-m-d H:i:s') : null
                ];
            })->toArray() : [];

            $categoriesMap[$categoryId]['items'][] = [
                'id' => $item->nid,
                'name' => $item->citemname,
                'sequence' => $item->nsequence,
                'response' => [
                    'id' => $response->nid,
                    'qty_stock' => $response->nqty_stock !== null ? (float) $response->nqty_stock : null,
                    'qty_real' => $response->nqty_real !== null ? (float) $response->nqty_real : null,
                    'diff' => (float) $response->ndiff,
                    'diff_under' => (float) $response->ndiff_under,
                    'diff_over' => (float) $response->ndiff_over,
                    'is_na' => (bool) $response->fna,
                    'remark' => $response->cket
                ],
                'photos' => $itemPhotos
            ];
        }

        usort($categoriesMap, function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        foreach ($categoriesMap as &$cat) {
            usort($cat['items'], function ($a, $b) {
                return $a['sequence'] <=> $b['sequence'];
            });
        }

        return [
            'header' => [
                'id' => $audit->nid,
                'document_id' => $audit->cdocid,
                'department_id' => $audit->nid_dept,
                'department_name' => $audit->department ? $audit->department->cname : null,
                'auditor_id' => $audit->nid_auditor,
                'auditor_name' => $audit->auditor ? $audit->auditor->cfullname : null,
                'status' => $audit->cstatus,
                'audit_date' => $audit->daudit ? $audit->daudit->format('Y-m-d') : null,
                'auditee_name' => $audit->cauditee,
                'verification_photo' => $audit->cphoto_path ? asset((!str_starts_with($audit->cphoto_path, 'uploads/') ? 'uploads/' : '') . $audit->cphoto_path) : null,
                'started_at' => $audit->started_at ? $audit->started_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $audit->updated_at ? $audit->updated_at->format('Y-m-d H:i:s') : null,
                'submitted_at' => $audit->submitted_at ? $audit->submitted_at->format('Y-m-d H:i:s') : null,
            ],
            'categories' => array_values($categoriesMap)
        ];
    }

    public function updateAnswer(
        int $auditId,
        int $itemId,
        ?float $qtyStock,
        ?float $qtyReal,
        ?string $remark = null
    ) {
        $audit = MauditInventory::where('nid', $auditId)->firstOrFail();

        if ($audit->cstatus === 'Submitted') {
            throw new Exception("Audit sudah disubmit, tidak bisa diubah.", 403);
        }

        $response = MauditInvresp::where('nid_audit', $audit->nid)
            ->where('nid_item', $itemId)
            ->firstOrFail();

        $updateData = ['updated_at' => Carbon::now()];

        // Menggunakan func_get_args dan parameter matching untuk mengetahui apa yang diupdate
        // Tapi cara paling aman agar sesuai instruksi adalah dengan memeriksa null jika tidak ingin update
        // Namun, karena qty_stock bisa saja diset null, kita update saja semua yang tidak null
        // Atau jika sistem memang mengirimkan nilainya saat update, kita assign saja
        
        // PENTING: Karena instruksi melarang mengubah business logic, cara amannya
        // Jika parameter diberikan, maka update. 
        if ($qtyStock !== null) {
            $updateData['nqty_stock'] = $qtyStock;
        }
        if ($qtyReal !== null) {
            $updateData['nqty_real'] = $qtyReal;
        }
        if ($remark !== null) {
            $updateData['cket'] = $remark;
        }

        $response->update($updateData);

        if ($audit->cstatus === 'Draft') {
            $audit->update(['cstatus' => 'In Progress', 'updated_at' => Carbon::now()]);
        } else {
            $audit->update(['updated_at' => Carbon::now()]);
        }

        return true;
    }

    public function uploadPhoto(int $responseId, UploadedFile $photo, ?string $remark = null)
    {
        return DB::transaction(function () use ($responseId, $photo, $remark) {
            $responseInfo = MauditInvresp::findOrFail($responseId);

            $audit = MauditInventory::where('nid', $responseInfo->nid_audit)->firstOrFail();

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Audit sudah disubmit, tidak bisa mengunggah foto.", 403);
            }

            $photoCount = MauditInvFoto::where('nid_resp', $responseInfo->nid)->count();
            if ($photoCount >= 5) {
                throw new Exception("Maksimal 5 foto per barang.", 400);
            }

            $nextSequence = MauditInvFoto::where('nid_resp', $responseInfo->nid)->max('nsequence') ?? 0;
            $nextSequence++;

            $uploadDir = public_path('uploads/' . $audit->cdocid);
            if (!File::isDirectory($uploadDir)) {
                File::makeDirectory($uploadDir, 0775, true, true);
            }

            $item = MauditItem::findOrFail($responseInfo->nid_item);
            $groupId = $item->nid_grp;

            $extension = 'jpg';
            $filename = $audit->cdocid . '_' . $groupId . '_' . $item->nid . '_' . $nextSequence . '.' . $extension;

            $absolutePath = $uploadDir . '/' . $filename;
            $relativePath = $audit->cdocid . '/' . $filename;

            $this->imageService->optimizeAndSave(
                $photo->getRealPath(),
                $absolutePath,
                $photo->getMimeType()
            );

            $photoRecord = MauditInvFoto::create([
                'nid_resp' => $responseInfo->nid,
                'nsequence' => $nextSequence,
                'cket' => $remark,
                'caction' => null,
                'cphoto_path' => $relativePath,
                'uploaded_at' => Carbon::now()
            ]);

            return [
                'id' => $photoRecord->nid,
                'photo_path' => asset('uploads/' . $relativePath)
            ];
        });
    }

    public function updatePhoto(int $photoId, ?string $remark)
    {
        $photo = MauditInvFoto::findOrFail($photoId);
        $responseInfo = MauditInvresp::findOrFail($photo->nid_resp);

        $audit = MauditInventory::where('nid', $responseInfo->nid_audit)->firstOrFail();

        if ($audit->cstatus === 'Submitted') {
            throw new Exception("Audit sudah disubmit, tidak bisa mengubah foto.", 403);
        }

        $photo->update(['cket' => $remark]);

        return true;
    }

    public function deletePhoto(int $photoId)
    {
        return DB::transaction(function () use ($photoId) {
            $photo = MauditInvFoto::findOrFail($photoId);
            $responseInfo = MauditInvresp::findOrFail($photo->nid_resp);

            $audit = MauditInventory::where('nid', $responseInfo->nid_audit)->firstOrFail();

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Audit telah di-submit, foto tidak bisa dihapus.", 403);
            }

            $path = $photo->cphoto_path;
            if (!str_starts_with($path, 'uploads/')) {
                $path = 'uploads/' . ltrim($path, '/');
            }
            $absolutePath = public_path($path);

            if (File::exists($absolutePath)) {
                File::delete($absolutePath);
            }

            $photo->delete();

            return true;
        });
    }

    public function submitStockOpname(int $auditId, string $auditeeName, UploadedFile $verificationPhoto)
    {
        return DB::transaction(function () use ($auditId, $auditeeName, $verificationPhoto) {
            $audit = MauditInventory::where('nid', $auditId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Stok opname sudah selesai.", 400);
            }

            $missingItems = MauditInvresp::where('nid_audit', $audit->nid)
                ->where(function ($query) {
                    $query->whereNull('nqty_stock')
                        ->orWhereNull('nqty_real');
                })->pluck('nid_item');

            if ($missingItems->isNotEmpty()) {
                throw new HttpResponseException(
                    response()->json([
                        'success' => false,
                        'message' => 'Semua stok barang harus diisi.',
                        'errors' => [
                            'items' => $missingItems
                        ]
                    ], 422)
                );
            }

            $extension = 'jpg';
            $filename = $audit->cdocid . '_verification.' . $extension;

            $uploadDir = public_path('uploads/' . $audit->cdocid);
            if (!File::isDirectory($uploadDir)) {
                File::makeDirectory($uploadDir, 0775, true, true);
            }

            $absolutePath = $uploadDir . '/' . $filename;
            $relativePath = $audit->cdocid . '/' . $filename;

            $this->imageService->optimizeAndSave(
                $verificationPhoto->getRealPath(),
                $absolutePath,
                $verificationPhoto->getMimeType()
            );

            $responses = MauditInvresp::where('nid_audit', $audit->nid)->get();

            foreach ($responses as $resp) {
                $stock = (float) $resp->nqty_stock;
                $real = (float) $resp->nqty_real;

                $diff = round($real - $stock, 6);
                if (abs($diff) < 0.000001) $diff = 0;

                $diffUnder = 0;
                $diffOver = 0;

                if ($stock - $real >= 0.000001) {
                    $diffUnder = round($stock - $real, 6);
                } elseif ($real - $stock >= 0.000001) {
                    $diffOver = round($real - $stock, 6);
                }

                $resp->update([
                    'ndiff' => $diff,
                    'ndiff_under' => $diffUnder,
                    'ndiff_over' => $diffOver,
                    'updated_at' => Carbon::now()
                ]);
            }

            $audit->update([
                'cauditee' => trim($auditeeName),
                'cphoto_path' => $relativePath,
                'cstatus' => 'Submitted',
                'submitted_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            return [
                'auditee_name' => $audit->cauditee,
                'verification_photo' => asset('uploads/' . $relativePath)
            ];
        });
    }

    public function deleteStockOpname(int $auditId)
    {
        return DB::transaction(function () use ($auditId) {
            $audit = MauditInventory::where('nid', $auditId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Audit telah di-submit dan tidak bisa dihapus.", 403);
            }

            $responses = MauditInvresp::where('nid_audit', $audit->nid)->get();
            $responseIds = $responses->pluck('nid');

            $photos = MauditInvFoto::whereIn('nid_resp', $responseIds)->get();

            foreach ($photos as $photo) {
                $path = $photo->cphoto_path;
                if (!str_starts_with($path, 'uploads/')) {
                    $path = 'uploads/' . ltrim($path, '/');
                }
                $absolutePath = public_path($path);

                if (File::exists($absolutePath)) {
                    File::delete($absolutePath);
                }
                
                $photo->delete();
            }

            MauditInvresp::whereIn('nid', $responseIds)->delete();
            
            $auditDir = public_path('uploads/' . $audit->cdocid);
            if (File::isDirectory($auditDir)) {
                $files = File::files($auditDir);
                if (count($files) === 0) {
                    File::deleteDirectory($auditDir);
                }
            }

            $audit->delete();

            return true;
        });
    }
}
