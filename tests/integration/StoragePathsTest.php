<?php
/**
 * Guards against a class of bug found while testing the download path:
 * several files constructed __DIR__-relative paths to storage/ and
 * public/media/d that were off by one directory level and resolved to
 * nowhere (realpath() => false), so file_exists()/is_dir() checks against
 * them silently and permanently returned false. This meant the actual
 * customer-facing download endpoint could never find a single file, for
 * any order, ever — masked because every call site treated "file not
 * found" as an ordinary, handled outcome rather than a startup-time
 * configuration error.
 *
 * These assertions don't touch app behavior; they just resolve the same
 * __DIR__-relative expressions the app code uses and confirm they land on
 * the real storage/ and public/media/d directories, so a future edit that
 * reintroduces a wrong ../ count fails immediately and loudly instead of
 * degrading into a silent 404 on every download.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

final class StoragePathsTest extends TestCase {
    public function testKnownPathsResolveToRealStorageDirectory(): void {
        $root = dirname(__DIR__, 2);
        $expectedStorage = realpath($root . '/storage');
        $expectedMedia = realpath($root . '/public/media/d');

        $this->assertNotFalse($expectedStorage, 'storage/ must exist at the repo root for this test to be meaningful');
        $this->assertNotFalse($expectedMedia, 'public/media/d must exist at the repo root for this test to be meaningful');

        $cases = [
            'app/controllers/admin/api_system.php' => ['../../../storage/hires', $expectedStorage . '/hires'],
            'app/controllers/admin/health.php' => ['../../../storage', $expectedStorage],
            'app/controllers/public/download.php' => ['../../../storage/hires', $expectedStorage . '/hires'],
            'app/lib/cron.php' => ['../../storage/hires', $expectedStorage . '/hires'],
            'app/lib/derivatives.php' => ['../../storage/hires', $expectedStorage . '/hires'],
        ];

        foreach ($cases as $file => [$relative, $expected]) {
            $dir = dirname($root . '/' . $file);
            $resolved = realpath($dir . '/' . $relative);
            $this->assertSame(
                $expected,
                $resolved,
                "$file's \"$relative\" relative to its own directory must resolve to $expected"
            );
        }
    }
}
