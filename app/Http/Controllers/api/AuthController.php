<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\muser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validasi request
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Cari user yang memiliki akses audit
        $user = muser::where('cemail', $request->email)
            ->where('faudit', 1)
            ->first();

        // User tidak ditemukan
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan atau tidak memiliki akses audit.'
            ], 401);
        }

        if (!$user->faudit) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.'
            ], 403);
        }

        // Password salah
        if (!Hash::check($request->password, $user->cpassword)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.'
            ], 401);
        }

        // User nonaktif
        if (!$user->factive) {
            return response()->json([
                'success' => false,
                'message' => 'Akun sudah tidak aktif.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'id' => $user->nid,
                'name' => $user->cfullname,
                'email' => $user->cemail,
                'company' => $user->ccompany,
                'department_id' => $user->niddept,
                'department_name' => $user->department?->cname,

                'role' => [
                    'admin' => $user->fadmin,
                    'super' => $user->fsuper,
                    'hrd' => $user->fhrd,
                    'audit' => $user->faudit,
                ]
            ]
        ]);
    }
}
