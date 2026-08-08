<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use App\Models\mdepartment;
use App\Models\MauditItem;
use App\Models\MauditItemgrp;
use App\Models\MauditDeptItem;
use App\Models\MauditInventory;

class StockDepartmentController extends Controller
{
    /**
     * Get all departments.
     *
     * GET /api/stock/departments
     */
    public function index(): JsonResponse
    {
        $departments = mdepartment::select(
            'nid as id',
            'cname as name'
        )
            ->orderBy('cname')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data department berhasil diambil.',
            'data' => $departments,
        ]);
    }

    /**
     * Get department to stock items mapping.
     *
     * GET /api/stock/departments/{id}/mapping
     */
    public function mapping($id): JsonResponse
    {
        $department = mdepartment::find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department tidak ditemukan.',
            ], 404);
        }

        /*
         * Ambil ID barang yang sudah terhubung
         * dengan department tersebut.
         */
        $linkedItemIds = MauditDeptItem::where(
            'nid_dept',
            $department->nid
        )
            ->pluck('nid_item')
            ->toArray();

        /*
         * Ambil semua kelompok/category barang.
         */
        $categories = MauditItemgrp::orderBy('cnama')
            ->get();

        /*
         * Ambil semua barang.
         *
         * Urut berdasarkan sequence kemudian nama.
         */
        $items = MauditItem::orderBy('nsequence')
            ->orderBy('citemname')
            ->get();

        /*
         * Kelompokkan barang berdasarkan nid_grp.
         */
        $groupedItems = $items->groupBy('nid_grp');

        $formattedCategories = [];

        foreach ($categories as $category) {
            $categoryItems = $groupedItems->get(
                $category->nid,
                collect()
            );

            $formattedItems = $categoryItems->map(function ($item) use ($linkedItemIds) {
                return [
                    'id' => $item->nid,
                    'name' => $item->citemname,
                    'sequence' => $item->nsequence,
                    'linked' => in_array(
                        $item->nid,
                        $linkedItemIds
                    ),
                ];
            })->values();

            $formattedCategories[] = [
                'id' => $category->nid,
                'name' => $category->cnama,
                'items' => $formattedItems,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Data mapping barang berhasil diambil.',
            'data' => [
                'department' => [
                    'id' => $department->nid,
                    'name' => $department->cname,
                ],
                'categories' => $formattedCategories,
            ],
        ]);
    }

    /**
     * Store / update department to stock items mapping.
     *
     * POST /api/stock/departments/mapping
     */
    public function storeMapping(Request $request): JsonResponse
    {
        /*
         * Validasi request.
         */
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|integer|exists:mdepartment,nid',

            'item_ids' => 'nullable|array',

            'item_ids.*' => [
                'integer',
                'exists:maudit_items,nid',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $departmentId = (int) $request->department_id;

        /*
         * Hilangkan duplicate item ID.
         */
        $itemIds = collect($request->input('item_ids', []))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        /*
         * =========================================================
         * CEK AUDIT INVENTORY AKTIF
         * =========================================================
         *
         * Mapping tidak boleh diubah apabila department
         * sedang digunakan dalam audit inventory.
         */
        $activeAudit = MauditInventory::where(
            'nid_dept',
            $departmentId
        )
            ->whereIn('cstatus', [
                'Draft',
                'In Progress',
            ])
            ->first();

        if ($activeAudit) {
            return response()->json([
                'success' => false,
                'message' => 'Pemetaan barang tidak dapat diubah karena department sedang digunakan dalam audit inventory.',
                'data' => [
                    'document_id' => $activeAudit->cdocid,
                    'status' => $activeAudit->cstatus,
                ],
            ], 409);
        }

        DB::beginTransaction();

        try {
            /*
             * Pastikan department masih ada.
             */
            $department = mdepartment::find($departmentId);

            if (!$department) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Department tidak ditemukan.',
                ], 404);
            }

            /*
             * Hapus seluruh mapping lama department.
             */
            MauditDeptItem::where(
                'nid_dept',
                $departmentId
            )->delete();

            /*
             * Masukkan mapping baru.
             */
            if (!empty($itemIds)) {
                $mappingData = [];

                foreach ($itemIds as $itemId) {
                    $mappingData[] = [
                        'nid_dept' => $departmentId,
                        'nid_item' => $itemId,
                    ];
                }

                MauditDeptItem::insert($mappingData);
            }

            DB::commit();

            /*
             * Ambil kembali mapping setelah berhasil disimpan.
             */
            $savedItemIds = MauditDeptItem::where(
                'nid_dept',
                $departmentId
            )
                ->pluck('nid_item')
                ->map(fn($id) => (int) $id)
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Pemetaan barang berhasil disimpan.',
                'data' => [
                    'department' => [
                        'id' => $department->nid,
                        'name' => $department->cname,
                    ],
                    'item_ids' => $savedItemIds,
                    'total_items' => count($savedItemIds),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error(
                'Gagal menyimpan pemetaan barang department.',
                [
                    'department_id' => $departmentId,
                    'item_ids' => $itemIds,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan pemetaan barang.',
            ], 500);
        }
    }
}
