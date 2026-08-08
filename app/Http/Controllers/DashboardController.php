<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MauditAudit;
use App\Models\MauditKat;
use App\Models\MauditQuest;
use App\Models\MauditItemgrp;
use App\Models\MauditItem;
use App\Models\MauditInventory;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        // =========================
        // AUDIT
        // =========================
        $totalKategori = MauditKat::count();

        $totalPertanyaan = MauditQuest::count();

        $totalAudit = MauditAudit::count();

        // =========================
        // STOCK
        // =========================
        $totalKategoriStok = MauditItemgrp::count();

        $totalBarang = MauditItem::count();

        $totalStokOpname = MauditInventory::count();

        // =========================
        // RECENT AUDIT
        // =========================
        $recentAudits = MauditAudit::with('department')
            ->orderBy('started_at', 'desc')
            ->take(5)
            ->get();

        $recentActivity = $recentAudits->map(function ($audit) {
            return [
                'id' => $audit->nid,
                'title' => 'Audit ' . ($audit->department->cname ?? 'Departemen'),
                'subtitle' => $audit->cdocid . ' • ' .
                    ($audit->daudit
                        ? $audit->daudit->format('d M Y')
                        : '-'),
                'status' => $audit->cstatus,
            ];
        });

        // =========================
        // RESPONSE
        // =========================
        return response()->json([
            'success' => true,
            'message' => 'Dashboard summary berhasil diambil.',
            'data' => [
                // Audit
                'total_kategori'   => $totalKategori,
                'total_pertanyaan' => $totalPertanyaan,
                'total_audit'      => $totalAudit,

                // Stock
                'total_kategori_stok' => $totalKategoriStok,
                'total_barang'        => $totalBarang,
                'total_stok_opname'   => $totalStokOpname,

                // Activity
                'recent_activity' => $recentActivity,
            ],
        ]);
    }
}
