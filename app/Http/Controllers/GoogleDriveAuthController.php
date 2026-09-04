<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client;

class GoogleDriveAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        $googleDir = storage_path('app/google');
        $clientSecretPath = $googleDir . '/client_secret.json';
        $tokenPath = $googleDir . '/token.json';

        if (!file_exists($clientSecretPath)) {
            return response('File client_secret.json tidak ditemukan di storage/app/google/. Harap download dari Google Cloud Console lalu letakkan di folder tersebut.', 500);
        }

        $client = new Client();
        $client->setAuthConfig($clientSecretPath);
        $client->setScopes([\Google\Service\Drive::DRIVE_FILE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent select_account');

        if ($request->has('error')) {
            return response('Google authorization failed: ' . $request->error, 400);
        }

        if (!$request->has('code')) {
            $authUrl = $client->createAuthUrl();
            return redirect()->away($authUrl);
        }

        $token = $client->fetchAccessTokenWithAuthCode($request->code);

        if (array_key_exists('error', $token)) {
            return response()->json(['error' => 'Google authorization failed', 'details' => $token], 500);
        }

        if (!file_exists($googleDir)) {
            mkdir($googleDir, 0755, true);
        }

        file_put_contents($tokenPath, json_encode($token, JSON_PRETTY_PRINT));

        return response('<h2>Google Drive authorization successful.</h2><p>File token.json berhasil di-generate secara otomatis di storage/app/google/. Sekarang sistem sudah dapat melakukan upload ke Google Drive.</p>');
    }
}
