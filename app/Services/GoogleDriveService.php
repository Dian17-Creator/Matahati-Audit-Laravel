<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use RuntimeException;

class GoogleDriveService
{
    protected $client;
    protected $drive;
    protected $folderId;

    public function __construct()
    {
        $this->folderId = env('GOOGLE_DRIVE_FOLDER_ID');
    }

    public function getClient(): Client
    {
        $googleDir = storage_path('app/google');
        $clientSecretPath = $googleDir . '/client_secret.json';
        $tokenPath = $googleDir . '/token.json';

        if (!file_exists($clientSecretPath)) {
            throw new RuntimeException('File client_secret.json tidak ditemukan di storage/app/google.');
        }

        if (!file_exists($tokenPath)) {
            throw new RuntimeException('File token.json tidak ditemukan. Harap buka URL otorisasi terlebih dahulu.');
        }

        $client = new Client();
        $client->setAuthConfig($clientSecretPath);
        $client->setScopes([Drive::DRIVE_FILE]);
        $client->setAccessType('offline');

        $token = json_decode(file_get_contents($tokenPath), true);
        if (!is_array($token)) {
            throw new RuntimeException('Token JSON tidak valid.');
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();
            if (!$refreshToken) {
                throw new RuntimeException('Google refresh token tidak ada. Perlu otorisasi ulang.');
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            if (isset($newToken['error'])) {
                throw new RuntimeException('Google token refresh gagal: ' . ($newToken['error_description'] ?? $newToken['error']));
            }

            // Simpan refresh_token lama jika tidak dikembalikan di respons baru
            $newToken['refresh_token'] = $refreshToken;
            
            if (!file_exists($googleDir)) {
                mkdir($googleDir, 0755, true);
            }
            file_put_contents($tokenPath, json_encode($newToken, JSON_PRETTY_PRINT));
            
            $client->setAccessToken($newToken);
        }

        return $client;
    }

    public function getDriveService(): Drive
    {
        if (!$this->drive) {
            $this->client = $this->getClient();
            $this->drive = new Drive($this->client);
        }
        return $this->drive;
    }

    public function findFile(string $filename): ?array
    {
        $drive = $this->getDriveService();
        $safeFilename = str_replace(["\\", "'"], ["\\\\", "\\'"], $filename);
        $safeFolderId = str_replace(["\\", "'"], ["\\\\", "\\'"], $this->folderId);

        $result = $drive->files->listFiles([
            'q' => "name = '{$safeFilename}' and '{$safeFolderId}' in parents and trashed = false",
            'fields' => 'files(id,name,size,webViewLink,modifiedTime)',
            'pageSize' => 1,
            'orderBy' => 'modifiedTime desc',
        ]);

        $files = $result->getFiles();

        if (empty($files)) {
            return null;
        }

        $file = $files[0];
        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'size' => $file->getSize(),
            'webViewLink' => $file->getWebViewLink(),
            'existing' => true,
        ];
    }

    public function ensurePublicViewerPermission(string $fileId): void
    {
        $drive = $this->getDriveService();
        $permissionList = $drive->permissions->listPermissions($fileId, [
            'fields' => 'permissions(id,type,role,allowFileDiscovery)',
        ]);

        foreach ($permissionList->getPermissions() as $permission) {
            if ($permission->getType() !== 'anyone') {
                continue;
            }

            if ($permission->getRole() !== 'reader') {
                $updatedPermission = new Permission(['role' => 'reader']);
                $drive->permissions->update($fileId, $permission->getId(), $updatedPermission);
            }
            return;
        }

        $permission = new Permission([
            'type' => 'anyone',
            'role' => 'reader',
            'allowFileDiscovery' => false,
        ]);

        $drive->permissions->create($fileId, $permission);
    }

    public function uploadFile(string $localPath, ?string $driveFilename = null): array
    {
        if (!file_exists($localPath)) {
            throw new RuntimeException('File lokal tidak ditemukan: ' . $localPath);
        }

        if (empty($this->folderId)) {
            throw new RuntimeException('GOOGLE_DRIVE_FOLDER_ID belum diatur di file .env');
        }

        $drive = $this->getDriveService();
        $filename = $driveFilename ?: basename($localPath);
        $existingFile = $this->findFile($filename);

        if ($existingFile !== null) {
            $this->ensurePublicViewerPermission($existingFile['id']);
            return $existingFile;
        }

        $metadata = new DriveFile([
            'name' => $filename,
            'parents' => [$this->folderId],
        ]);

        $result = $drive->files->create($metadata, [
            'data' => file_get_contents($localPath),
            'mimeType' => 'application/pdf',
            'uploadType' => 'multipart',
            'fields' => 'id,name,size,webViewLink',
        ]);

        $this->ensurePublicViewerPermission($result->getId());

        return [
            'id' => $result->getId(),
            'name' => $result->getName(),
            'size' => $result->getSize(),
            'webViewLink' => $result->getWebViewLink(),
            'existing' => false,
        ];
    }
}
