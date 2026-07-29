<?php
/**
 * Storage abstraction layer.
 * Automatically uses local filesystem or remote SFTP based on admin_mode.
 * Used by admin controllers to upload, download, and manage files.
 */

declare(strict_types=1);

class Storage {
    private string $mode;
    private string $storagePath;
    private ?RemoteSFTP $sftp = null;

    public function __construct(array $config) {
        $this->mode = $config['admin_mode'] ?? 'local';
        $this->storagePath = __DIR__ . '/../../storage';

        if ($this->mode === 'remote') {
            require_once __DIR__ . '/sftp.php';
            $this->sftp = new RemoteSFTP($config, $config['admin_remote']['storage_path']);
        }
    }

    public function uploadFile(string $sourceFile, string $destinationPath): void {
        if (!file_exists($sourceFile)) {
            throw new RuntimeException("Source file not found: $sourceFile");
        }

        if ($this->mode === 'local') {
            $dest = $this->storagePath . '/' . ltrim($destinationPath, '/');
            $dir = dirname($dest);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (!copy($sourceFile, $dest)) {
                throw new RuntimeException("Failed to copy file to: $dest");
            }
        } else {
            $this->sftp->uploadFile($sourceFile, $destinationPath);
        }
    }

    public function downloadFile(string $sourcePath, string $destinationFile): void {
        if ($this->mode === 'local') {
            $source = $this->storagePath . '/' . ltrim($sourcePath, '/');

            if (!file_exists($source)) {
                throw new RuntimeException("Source file not found: $source");
            }

            $dir = dirname($destinationFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (!copy($source, $destinationFile)) {
                throw new RuntimeException("Failed to copy file to: $destinationFile");
            }
        } else {
            $this->sftp->downloadFile($sourcePath, $destinationFile);
        }
    }

    public function deleteFile(string $path): void {
        if ($this->mode === 'local') {
            $file = $this->storagePath . '/' . ltrim($path, '/');

            if (file_exists($file) && !unlink($file)) {
                throw new RuntimeException("Failed to delete file: $file");
            }
        } else {
            $this->sftp->deleteFile($path);
        }
    }

    public function fileExists(string $path): bool {
        if ($this->mode === 'local') {
            return file_exists($this->storagePath . '/' . ltrim($path, '/'));
        } else {
            return $this->sftp->fileExists($path);
        }
    }

    public function listDirectory(string $dir = ''): array {
        if ($this->mode === 'local') {
            $path = $this->storagePath . ($dir ? '/' . ltrim($dir, '/') : '');

            if (!is_dir($path)) {
                return [];
            }

            $files = scandir($path);
            if ($files === false) {
                return [];
            }

            $result = [];
            foreach ($files as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }

                $fullPath = $path . '/' . $name;
                $result[] = [
                    'name' => $name,
                    'is_dir' => is_dir($fullPath),
                    'size' => filesize($fullPath) ?: 0,
                    'mtime' => filemtime($fullPath) ?: 0,
                ];
            }

            return $result;
        } else {
            return $this->sftp->listDirectory($dir);
        }
    }

    public function getStoragePath(): string {
        return $this->storagePath;
    }

    public function isRemote(): bool {
        return $this->mode === 'remote';
    }

    public function __destruct() {
        if ($this->sftp !== null) {
            $this->sftp->close();
        }
    }
}
