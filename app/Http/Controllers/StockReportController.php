<?php

namespace App\Http\Controllers;

use App\Models\MauditInventory;
use App\Models\MauditInvresp;
use App\Models\MauditInvFoto;
use App\Models\mdepartment;
use App\Models\MauditDeptItem;
use App\Models\MauditItem;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;
use Exception;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StockReportController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
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
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            $query = MauditInventory::with([
                'department',
                'auditor'
            ])
                // Histori hanya milik auditor yang sedang login
                ->where('nid_auditor', $user->nid)

                // Terbaru di atas
                ->orderBy('started_at', 'desc');

            /**
             * Filter departemen
             */
            if ($request->filled('department_id')) {
                $query->where('nid_dept', $request->department_id);
            }

            /**
             * Filter tanggal mulai
             */
            if ($request->filled('date_from')) {
                $query->whereDate('daudit', '>=', $request->date_from);
            }

            /**
             * Filter tanggal akhir
             */
            if ($request->filled('date_to')) {
                $query->whereDate('daudit', '<=', $request->date_to);
            }

            /**
             * Jika page dikirim:
             * gunakan pagination 15 data per halaman.
             *
             * Jika page tidak dikirim:
             * ambil maksimal 1000 data seperti pola Audit History.
             */
            if ($request->filled('page')) {
                $histories = $query->paginate(15);

                $items = $histories->getCollection()->map(function ($audit) {
                    return [
                        'id' => $audit->nid,
                        'document_id' => $audit->cdocid,

                        'department_id' => $audit->nid_dept,
                        'department_name' => $audit->department
                            ? $audit->department->cname
                            : null,

                        'auditor_id' => $audit->nid_auditor,
                        'auditor_name' => $audit->auditor
                            ? $audit->auditor->cfullname
                            : null,

                        'audit_date' => $audit->daudit
                            ? $audit->daudit->format('Y-m-d')
                            : null,

                        'status' => $audit->cstatus,

                        'auditee_name' => $audit->cauditre,

                        'started_at' => $audit->started_at
                            ? $audit->started_at->format('Y-m-d H:i:s')
                            : null,

                        'updated_at' => $audit->updated_at
                            ? $audit->updated_at->format('Y-m-d H:i:s')
                            : null,

                        'submitted_at' => $audit->submitted_at
                            ? $audit->submitted_at->format('Y-m-d H:i:s')
                            : null,
                    ];
                });

                return response()->json([
                    'success' => true,
                    'message' => 'Data histori stok opname berhasil diambil.',
                    'data' => [
                        'items' => $items->values(),
                        'pagination' => [
                            'current_page' => $histories->currentPage(),
                            'last_page' => $histories->lastPage(),
                            'per_page' => $histories->perPage(),
                            'total' => $histories->total(),
                            'from' => $histories->firstItem(),
                            'to' => $histories->lastItem(),
                        ]
                    ]
                ]);
            }

            /**
             * Tanpa pagination:
             * maksimal 1000 data.
             */
            $histories = $query
                ->limit(1000)
                ->get();

            $items = $histories->map(function ($audit) {
                return [
                    'id' => $audit->nid,
                    'document_id' => $audit->cdocid,

                    'department_id' => $audit->nid_dept,
                    'department_name' => $audit->department
                        ? $audit->department->cname
                        : null,

                    'auditor_id' => $audit->nid_auditor,
                    'auditor_name' => $audit->auditor
                        ? $audit->auditor->cfullname
                        : null,

                    'audit_date' => $audit->daudit
                        ? $audit->daudit->format('Y-m-d')
                        : null,

                    'status' => $audit->cstatus,

                    'auditee_name' => $audit->cauditre,

                    'started_at' => $audit->started_at
                        ? $audit->started_at->format('Y-m-d H:i:s')
                        : null,

                    'updated_at' => $audit->updated_at
                        ? $audit->updated_at->format('Y-m-d H:i:s')
                        : null,

                    'submitted_at' => $audit->submitted_at
                        ? $audit->submitted_at->format('Y-m-d H:i:s')
                        : null,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data histori stok opname berhasil diambil.',
                'data' => [
                    'items' => $items->values(),
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $items->count(),
                        'total' => $items->count(),
                        'from' => $items->isNotEmpty() ? 1 : null,
                        'to' => $items->isNotEmpty() ? $items->count() : null,
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Gagal mengambil histori stok opname', [
                'user_id' => Auth::id(),
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
            return DB::transaction(function () use ($request) {
                $user = Auth::user();
                if (!$user) {
                    throw new Exception("Unauthorized.", 401);
                }

                $departmentId = $request->department_id;
                $auditorId = $user->nid;

                // Check existing Draft/In Progress audit based on old logic (cstatus <> 'Submitted')
                $existing = MauditInventory::where('nid_dept', $departmentId)
                    ->where('nid_auditor', $auditorId)
                    ->where('cstatus', '<>', 'Submitted')
                    ->orderBy('started_at', 'desc')
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Existing audit found.',
                        'data' => [
                            'id' => $existing->nid,
                            'document_id' => $existing->cdocid,
                            'status' => $existing->cstatus
                        ]
                    ]);
                }

                $department = mdepartment::findOrFail($departmentId);

                // Generate Document ID
                $deptName = strtolower(trim($department->cname));
                $deptName = preg_replace("/[^a-z0-9]+/", "_", $deptName);
                $deptName = trim($deptName, "_");

                $docPrefix = "stok_" . $deptName . "_" . date("Ymd");
                $sequence = MauditInventory::where('cdocid', 'LIKE', $docPrefix . '_%')->count() + 1;
                $cdocid = sprintf("%s_%03d", $docPrefix, $sequence);

                // Create Header
                $audit = MauditInventory::create([
                    'cdocid' => $cdocid,
                    'nid_dept' => $departmentId,
                    'nid_auditor' => $auditorId,
                    'daudit' => Carbon::today(),
                    'cstatus' => 'Draft',
                    'started_at' => Carbon::now()
                ]);

                // Get items mapped to this department
                $items = MauditDeptItem::where('nid_dept', $departmentId)
                    ->pluck('nid_item');

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

                return response()->json([
                    'success' => true,
                    'message' => 'Dokumen stok opname berhasil dibuat.',
                    'data' => [
                        'id' => $audit->nid,
                        'document_id' => $audit->cdocid,
                        'status' => $audit->cstatus
                    ]
                ], 201);
            });
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
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
            }

            // Ownership check via query
            $audit = MauditInventory::with(['department', 'auditor'])
                ->where('nid', $id)
                ->where('nid_auditor', $user->nid)
                ->firstOrFail();

            // Get responses with items and their photos
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

            return response()->json([
                'success' => true,
                'message' => 'Detail stok opname berhasil diambil.',
                'data' => [
                    'header' => [
                        'id' => $audit->nid,
                        'document_id' => $audit->cdocid,
                        'department_id' => $audit->nid_dept,
                        'department_name' => $audit->department ? $audit->department->cname : null,
                        'auditor_id' => $audit->nid_auditor,
                        'auditor_name' => $audit->auditor ? $audit->auditor->cfullname : null,
                        'status' => $audit->cstatus,
                        'audit_date' => $audit->daudit ? $audit->daudit->format('Y-m-d') : null,
                        'auditee_name' => $audit->cauditre,
                        'verification_photo' => $audit->cphoto_path ? asset((!str_starts_with($audit->cphoto_path, 'uploads/') ? 'uploads/' : '') . $audit->cphoto_path) : null,
                        'started_at' => $audit->started_at ? $audit->started_at->format('Y-m-d H:i:s') : null,
                        'updated_at' => $audit->updated_at ? $audit->updated_at->format('Y-m-d H:i:s') : null,
                        'submitted_at' => $audit->submitted_at ? $audit->submitted_at->format('Y-m-d H:i:s') : null,
                    ],
                    'categories' => array_values($categoriesMap)
                ]
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
            $user = Auth::user();
            if (!$user) throw new Exception("Unauthorized.", 401);

            // Ownership check via query
            $audit = MauditInventory::where('nid', $request->audit_id)
                ->where('nid_auditor', $user->nid)
                ->firstOrFail();

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Audit sudah disubmit, tidak bisa diubah.", 403);
            }

            $response = MauditInvresp::where('nid_audit', $audit->nid)
                ->where('nid_item', $request->item_id)
                ->firstOrFail();

            $updateData = ['updated_at' => Carbon::now()];

            if ($request->has('qty_stock')) {
                $updateData['nqty_stock'] = $request->qty_stock;
            }
            if ($request->has('qty_real')) {
                $updateData['nqty_real'] = $request->qty_real;
            }
            if ($request->has('remark')) {
                $updateData['cket'] = $request->remark;
            }

            $response->update($updateData);

            if ($audit->cstatus === 'Draft') {
                $audit->update(['cstatus' => 'In Progress', 'updated_at' => Carbon::now()]);
            } else {
                $audit->update(['updated_at' => Carbon::now()]);
            }

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
            return DB::transaction(function () use ($request) {
                $user = Auth::user();
                if (!$user) throw new Exception("Unauthorized.", 401);

                $responseInfo = MauditInvresp::findOrFail($request->response_id);

                // Ownership check via query on audit
                $audit = MauditInventory::where('nid', $responseInfo->nid_audit)
                    ->where('nid_auditor', $user->nid)
                    ->firstOrFail();

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

                // Optimize and save
                $this->imageService->optimizeAndSave(
                    $request->file('photo')->getRealPath(),
                    $absolutePath,
                    $request->file('photo')->getMimeType()
                );

                $photo = MauditInvFoto::create([
                    'nid_resp' => $responseInfo->nid,
                    'nsequence' => $nextSequence,
                    'cket' => $request->remark,
                    'caction' => null, // Not used in this workflow, explicitly null
                    'cphoto_path' => $relativePath,
                    'uploaded_at' => Carbon::now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Upload berhasil.',
                    'data' => [
                        'id' => $photo->nid,
                        'photo_path' => asset('uploads/' . $relativePath)
                    ]
                ]);
            });
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
            $user = Auth::user();
            if (!$user) throw new Exception("Unauthorized.", 401);

            $photo = MauditInvFoto::findOrFail($request->photo_id);
            $responseInfo = MauditInvresp::findOrFail($photo->nid_resp);

            // Ownership check via query
            $audit = MauditInventory::where('nid', $responseInfo->nid_audit)
                ->where('nid_auditor', $user->nid)
                ->firstOrFail();

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Audit sudah disubmit, tidak bisa mengubah foto.", 403);
            }

            $photo->update([
                'cket' => $request->remark
            ]);

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
            return DB::transaction(function () use ($request) {
                $user = Auth::user();
                if (!$user) throw new Exception("Unauthorized.", 401);

                $photo = MauditInvFoto::findOrFail($request->photo_id);
                $responseInfo = MauditInvresp::findOrFail($photo->nid_resp);

                // Ownership check via query
                $audit = MauditInventory::where('nid', $responseInfo->nid_audit)
                    ->where('nid_auditor', $user->nid)
                    ->firstOrFail();

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

                return response()->json([
                    'success' => true,
                    'message' => 'Foto berhasil dihapus.'
                ]);
            });
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
            return DB::transaction(function () use ($request) {
                $user = Auth::user();
                if (!$user) {
                    throw new HttpResponseException(response()->json(['success' => false, 'message' => 'Unauthorized.'], 401));
                }

                // Ownership check via query + lockForUpdate
                $audit = MauditInventory::where('nid', $request->audit_id)
                    ->where('nid_auditor', $user->nid)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($audit->cstatus === 'Submitted') {
                    throw new Exception("Stok opname sudah selesai.", 400);
                }

                // Check all items have qty_stock and qty_real
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

                // Upload verification photo
                $photo = $request->file('verification_photo');
                $extension = $photo->getClientOriginalExtension() ?: 'jpg';
                $filename = $audit->cdocid . '_verification.' . $extension;

                $uploadDir = public_path('uploads/' . $audit->cdocid);
                if (!File::isDirectory($uploadDir)) {
                    File::makeDirectory($uploadDir, 0775, true, true);
                }

                $relativePath = $audit->cdocid . '/' . $filename;
                $photo->move($uploadDir, $filename);

                // Calculate diffs
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

                // Update audit using cauditre based on database schema
                $audit->update([
                    'cauditre' => trim($request->auditee_name),
                    'cphoto_path' => $relativePath,
                    'cstatus' => 'Submitted',
                    'submitted_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Stok opname selesai.',
                    'data' => [
                        'auditee_name' => $audit->cauditre,
                        'verification_photo' => asset('uploads/' . $relativePath)
                    ]
                ]);
            });
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
