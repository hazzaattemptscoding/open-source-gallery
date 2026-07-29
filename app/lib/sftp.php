<?php
/**
 * SFTP file operations for remote admin mode.
 * Uses phpseclib library for pure PHP SFTP without ssh2 extension.
 * Install with: composer require phpseclib/phpseclib
 */

declare(strict_types=1);

use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

class RemoteSFTP {
    private SFTP $sftp;
    private string $storagePath;

    public function __construct(array $config, string $storagePath) {
        if (empty($config['admin_remote'])) {
            throw new RuntimeException('admin_remote config not found');
        }

        $remote = $config['admin_remote'];
        $this->storagePath = rtrim($storagePath, '/');

        // Check for required phpseclib classes
        if (!class_exists('phpseclib3\Net\SFTP')) {
            throw new RuntimeException(
                'phpseclib3 not installed. Install with: composer require phpseclib/phpseclib'
            );
        }

        $this->sftp = new SFTP($remote['sftp_host'], $remote['sftp_port'] ?? 22);

        // Authenticate with password or key
        if (!empty($remote['sftp_key_file'])) {
            $this->authenticateWithKey($remote['sftp_key_file'], $remote['sftp_user']);
        } else {
            $this->authenticateWithPassword($remote['sftp_user'], $remote['sftp_pass']);
        }
    }

    private function authenticateWithPassword(string $user, string $pass): void {
        if (!$this->sftp->login($user, $pass)) {
            throw new RuntimeException('SFTP authentication failed (password)');
        }
    }

    private function authenticateWithKey(string $keyFile, string $user): void {
        if (!file_exists($keyFile)) {
            throw new RuntimeException("Private key file not found: $keyFile");
        }

        try {
            $key = PublicKeyLoader::load(file_get_contents($keyFile));
            if (!$this->sftp->login($user, $key)) {
                throw new RuntimeException('SFTP authentication failed (key)');
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to load private key: ' . $e->getMessage());
        }
    }

    public function uploadFile(string $localPath, string $remoteFilename): void {
        if (!file_exists($localPath)) {
            throw new RuntimeException("Local file not found: $localPath");
        }

        $remotePath = $this->storagePath . '/' . $remoteFilename;

        // Ensure remote directory exists
        $dir = dirname($remotePath);
        $this->ensureDirectoryExists($dir);

        // Upload file
        if (!$this->sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new RuntimeException("Failed to upload file: $remotePath");
        }
    }

    public function downloadFile(string $remoteFilename, string $localPath): void {
        $remotePath = $this->storagePath . '/' . $remoteFilename;

        // Ensure local directory exists
        $dir = dirname($localPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!$this->sftp->get($remotePath, $localPath)) {
            throw new RuntimeException("Failed to download file: $remotePath");
        }
    }

    public function deleteFile(string $remoteFilename): void {
        $remotePath = $this->storagePath . '/' . $remoteFilename;

        if (!$this->sftp->delete($remotePath)) {
            throw new RuntimeException("Failed to delete file: $remotePath");
        }
    }

    public function fileExists(string $remoteFilename): bool {
        $remotePath = $this->storagePath . '/' . $remoteFilename;
        return $this->sftp->file_exists($remotePath);
    }

    public function listDirectory(string $remoteDir = ''): array {
        $remotePath = $remoteDir ? $this->storagePath . '/' . $remoteDir : $this->storagePath;

        $files = $this->sftp->rawlist($remotePath);
        if ($files === false) {
            return [];
        }

        $result = [];
        foreach ($files as $name => $attr) {
            // Skip . and ..
            if ($name === '.' || $name === '..') {
                continue;
            }

            $result[] = [
                'name' => $name,
                'is_dir' => isset($attr['-']),  // directories have '-' key in phpseclib
                'size' => $attr['size'] ?? 0,
                'mtime' => $attr['mtime'] ?? 0,
            ];
        }

        return $result;
    }

    private function ensureDirectoryExists(string $remotePath): void {
        if ($this->sftp->is_dir($remotePath)) {
            return;
        }

        // Create parent directories recursively
        $parts = explode('/', trim($remotePath, '/'));
        $current = '';

        foreach ($parts as $part) {
            $current .= '/' . $part;
            if (!$this->sftp->is_dir($current)) {
                $this->sftp->mkdir($current, 0755, true);
            }
        }
    }

    public function close(): void {
        $this->sftp->disconnect();
    }
}
