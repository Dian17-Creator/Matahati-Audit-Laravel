<?php

namespace App\Http\Controllers;

use App\Models\MauditKat;
use App\Models\MauditQuest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuditCategoryController extends Controller
{
    /**
     * GET /api/audit/categories
     */
    public function index()
    {
        $categories = MauditKat::withCount('questions')
            ->orderBy('nid')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->nid,
                    'name' => $item->cnama,
                    'description' => $item->cket,
                    'question_count' => $item->questions_count,
                    'created_at' => $item->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Data kategori berhasil diambil.',
            'data' => $categories,
        ]);
    }

    /**
     * POST /api/audit/categories
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $category = MauditKat::create([
            'cnama' => $request->name,
            'cket' => $request->description,
            'created_at' => now(),
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
    }

    /**
     * PUT /api/audit/categories/{id}
     */
    public function update(Request $request, $id)
    {
        Log::info('UPDATE MASUK', [
            'id' => $id,
            'data' => $request->all(),
        ]);

        $category = MauditKat::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $category->update([
            'cnama' => $request->name,
            'cket' => $request->description,
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
    }

    /**
     * DELETE /api/audit/categories/{id}
     */
    public function destroy($id)
    {
        Log::info('DELETE MASUK', [
            'id' => $id,
        ]);

        $category = MauditKat::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan.'
            ], 404);
        }

        // Cek apakah ada pertanyaan dalam kategori ini yang sudah
        // digunakan dalam transaksi audit (maudit_responses)
        $usedInResponses = DB::table('maudit_quest')
            ->join('maudit_responses', 'maudit_responses.nid_quest', '=', 'maudit_quest.nid')
            ->where('maudit_quest.nid_kat', $id)
            ->exists();

        if ($usedInResponses) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak dapat dihapus karena satu atau lebih pertanyaan sudah digunakan dalam proses audit.'
            ], 409);
        }

        // Cek apakah ada pertanyaan yang sudah di-mapping ke department
        $usedInDept = DB::table('maudit_quest')
            ->join('maudit_deptquest', 'maudit_deptquest.nid_quest', '=', 'maudit_quest.nid')
            ->where('maudit_quest.nid_kat', $id)
            ->exists();

        if ($usedInDept) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak dapat dihapus karena satu atau lebih pertanyaan sudah di-mapping ke department.'
            ], 409);
        }

        try {
            DB::beginTransaction();

            // Hapus semua pertanyaan dalam kategori ini terlebih dahulu
            MauditQuest::where('nid_kat', $id)->delete();

            // Hapus kategorinya
            $category->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menghapus kategori audit: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus kategori.',
            ], 500);
        }
    }

    /**
     * GET /api/audit/categories/{id}
     */
    public function show($id)
    {
        $category = MauditKat::withCount('questions')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail kategori berhasil diambil.',
            'data' => [
                'id' => $category->nid,
                'name' => $category->cnama,
                'description' => $category->cket,
                'question_count' => $category->questions_count,
                'created_at' => $category->created_at,
            ],
        ]);
    }
}
