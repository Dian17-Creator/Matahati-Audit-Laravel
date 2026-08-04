<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MauditAudit;
use App\Models\MauditKat;
use App\Models\MauditQuest;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $totalKategori = MauditKat::count();

        $totalPertanyaan = MauditQuest::count();

        $totalAudit = MauditAudit::count();

        $recentAudits = MauditAudit::with('department')
            ->orderBy('started_at', 'desc')
            ->take(5)
            ->get();

        $recentActivity = $recentAudits->map(function ($audit) {
            return [
                'id' => $audit->nid,
                'title' => 'Audit ' . ($audit->department->cname ?? 'Departemen'),
                'subtitle' => $audit->cdocid . ' • ' . ($audit->daudit ? $audit->daudit->format('d M Y') : '-'),
                'status' => $audit->cstatus
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Dashboard summary berhasil diambil.',
            'data' => [
                'total_kategori'   => $totalKategori,
                'total_pertanyaan' => $totalPertanyaan,
                'total_audit'      => $totalAudit,
                'recent_activity'  => $recentActivity
            ]
        ]);
    }
}
