<?php

namespace App\Http\Controllers;

use App\Models\MauditItemgrp;
use App\Models\MauditItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class StockCategoryController extends Controller
{
    /**
     * GET /api/stock/categories
     * Mengambil data pohon hierarki (kategori beserta barang di dalamnya)
     */
    public function index()
    {
        try {
            $categories = MauditItemgrp::with(['items' => function ($query) {
                $query->orderBy('nsequence');
            }])
                ->orderBy('nid')
                ->get()
                ->map(function ($cat) {
                    return [
                        'id' => $cat->nid,
                        'name' => $cat->cnama,
                        'description' => $cat->cket,
                        'items' => $cat->items->map(function ($item) {
                            return [
                                'id' => $item->nid,
                                'category_id' => $item->nid_grp,
                                'name' => $item->citemname,
                                'sequence' => $item->nsequence,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data pohon kategori dan barang berhasil diambil.',
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal mengambil pohon kategori dan barang: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
            ], 500);
        }
    }

    /**
     * POST /api/stock/categories
     * Tambah Kategori Baru
     */
    public function storeCategory(Request $request)
    {
        if (empty($request->cnama)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama kategori harus diisi.'
            ], 400);
        }

        try {
            $category = MauditItemgrp::create([
                'cnama' => $request->cnama,
                'cket' => $request->cket,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil ditambahkan.',
                'data' => [
                    'id' => $category->nid,
                    'name' => $category->cnama,
                    'description' => $category->cket,
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Gagal menambahkan kategori: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan kategori.',
            ], 500);
        }
    }

    /**
     * PUT /api/stock/categories/{id?}
     * Edit Kategori
     */
    public function updateCategory(Request $request, $id = null)
    {
        $nid = $id ?? $request->nid;
        $cnama = $request->cnama;

        if (empty($nid) || empty($cnama)) {
            return response()->json([
                'success' => false,
                'message' => 'ID kategori dan Nama kategori harus diisi.'
            ], 400);
        }

        try {
            $category = MauditItemgrp::find($nid);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.'
                ], 404);
            }

            $category->update([
                'cnama' => $cnama,
                'cket' => $request->cket,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil diperbarui.',
                'data' => [
                    'id' => $category->nid,
                    'name' => $category->cnama,
                    'description' => $category->cket,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal memperbarui kategori: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui kategori.',
            ], 500);
        }
    }

    /**
     * DELETE /api/stock/categories/{id?}
     * Hapus Kategori
     */
    public function destroyCategory(Request $request, $id = null)
    {
        $nid = $id ?? $request->nid;

        if (empty($nid)) {
            return response()->json([
                'success' => false,
                'message' => 'ID kategori harus diisi.'
            ], 400);
        }

        try {
            $category = MauditItemgrp::find($nid);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.'
                ], 404);
            }

            // Validasi Transaksi: pastikan tidak ada barang di dalam kategori ini
            // yang sudah pernah digunakan dalam transaksi audit/opname (maudit_invresp)
            $isUsed = DB::table('maudit_items')
                ->join('maudit_invresp', 'maudit_invresp.nid_item', '=', 'maudit_items.nid')
                ->where('maudit_items.nid_grp', $nid)
                ->exists();

            if ($isUsed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak dapat dihapus karena satu atau lebih barang sudah digunakan dalam proses opname.'
                ], 409);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menghapus kategori: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus kategori.',
            ], 500);
        }
    }

    /**
     * GET /api/stock/categories/{categoryId}/items
     * Mengambil semua barang berdasarkan kategori
     */
    public function getItems($categoryId)
    {
        try {
            $category = MauditItemgrp::find($categoryId);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.',
                ], 404);
            }

            $items = MauditItem::where('nid_grp', $categoryId)
                ->orderBy('nsequence')
                ->orderBy('nid')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->nid,
                        'category_id' => $item->nid_grp,
                        'name' => $item->citemname,
                        'sequence' => $item->nsequence,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data barang berhasil diambil.',
                'data' => [
                    'category' => [
                        'id' => $category->nid,
                        'name' => $category->cnama,
                        'description' => $category->cket,
                    ],
                    'items' => $items,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error(
                'Gagal mengambil barang berdasarkan kategori: ' . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data barang.',
            ], 500);
        }
    }

    /**
     * POST /api/stock/items
     * Tambah Barang
     */
    public function storeItem(Request $request)
    {
        $nid_grp = $request->nid_grp;
        $citemname = $request->citemname;

        if (empty($nid_grp) || empty($citemname)) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori dan Nama barang harus diisi.'
            ], 400);
        }

        try {
            $categoryExists = MauditItemgrp::where('nid', $nid_grp)->exists();
            if (!$categoryExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.'
                ], 404);
            }

            DB::beginTransaction();

            $maxSequence = MauditItem::where('nid_grp', $nid_grp)->max('nsequence') ?? 0;
            $nsequence = $maxSequence + 1;

            $item = MauditItem::create([
                'nid_grp' => $nid_grp,
                'citemname' => $citemname,
                'nsequence' => $nsequence,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil ditambahkan.',
                'data' => [
                    'id' => $item->nid,
                    'category_id' => $item->nid_grp,
                    'name' => $item->citemname,
                    'sequence' => $item->nsequence,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menambahkan barang: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan barang.',
            ], 500);
        }
    }

    /**
     * DELETE /api/stock/items/{id?}
     * Hapus Barang
     */
    public function destroyItem(Request $request, $id = null)
    {
        $nid = $id ?? $request->nid;

        if (empty($nid)) {
            return response()->json([
                'success' => false,
                'message' => 'ID barang harus diisi.'
            ], 400);
        }

        try {
            $item = MauditItem::find($nid);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak ditemukan.'
                ], 404);
            }

            // Validasi Transaksi: pastikan barang belum pernah digunakan dalam opname
            $isUsed = DB::table('maudit_invresp')->where('nid_item', $nid)->exists();

            if ($isUsed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak dapat dihapus karena sudah digunakan dalam audit.'
                ], 409);
            }

            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menghapus barang: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus barang.',
            ], 500);
        }
    }

    /**
     * POST /api/stock/items/reorder
     * Pengaturan Urutan Barang
     */
    public function reorderItems(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:maudit_itemgrp,nid',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'required|integer|exists:maudit_items,nid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $categoryId = $request->category_id;
        $itemIds = $request->item_ids;

        // Pastikan semua barang milik kategori yang dipilih
        $count = MauditItem::where('nid_grp', $categoryId)
            ->whereIn('nid', $itemIds)
            ->count();

        if ($count !== count($itemIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Beberapa barang tidak termasuk dalam kategori yang dipilih.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($itemIds as $index => $itemId) {
                MauditItem::where('nid', $itemId)->update([
                    'nsequence' => $index + 1
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Urutan barang berhasil diperbarui.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal memperbarui urutan barang: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui urutan barang.',
            ], 500);
        }
    }
}
