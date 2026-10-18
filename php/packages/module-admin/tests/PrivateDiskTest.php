<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RuntimeException;
use WebxUi\Admin\Support\PrivateDisk;

/**
 * Which disks serve their files to anyone with the address (§14.5 of the permissions spec):
 * where a module keeps a stranger's files or an export, such a disk is refused.
 */
final class PrivateDiskTest extends BaseTestCase
{
    private function disks(): PrivateDisk
    {
        return new PrivateDisk(
            [
                'local' => ['driver' => 'local', 'root' => '/srv/site/storage/app/private'],
                'public' => ['driver' => 'local', 'root' => '/srv/site/storage/app/public', 'visibility' => 'public'],
                'published' => ['driver' => 'local', 'root' => '/srv/site/storage/app/public/inbox'],
                'webroot' => ['driver' => 'local', 'root' => '/srv/site/public/uploads'],
                'beside' => ['driver' => 'local', 'root' => '/srv/site/storage/app/publications'],
                'bucket' => ['driver' => 's3', 'bucket' => 'files'],
                'open-bucket' => ['driver' => 's3', 'bucket' => 'files', 'visibility' => 'public'],
            ],
            '/srv/site/public',
            '/srv/site/storage/app/public',
        );
    }

    #[Test]
    public function a_disk_is_public_by_its_visibility_or_by_where_its_root_is_published(): void
    {
        $disks = $this->disks();

        $this->assertFalse($disks->isPublic('local'));
        $this->assertTrue($disks->isPublic('public'));
        // Inside `storage/app/public`, which `storage:link` publishes, whatever the visibility says.
        $this->assertTrue($disks->isPublic('published'));
        $this->assertTrue($disks->isPublic('webroot'));
        // A folder whose name only starts the same is not inside it.
        $this->assertFalse($disks->isPublic('beside'));
        $this->assertFalse($disks->isPublic('bucket'));
        $this->assertTrue($disks->isPublic('open-bucket'));
        // A disk the site does not have is Laravel's to complain about, not this.
        $this->assertFalse($disks->isPublic('nowhere'));
    }

    #[Test]
    public function a_public_disk_is_refused_with_the_setting_that_named_it(): void
    {
        $this->disks()->assert('local', 'webx-inbox.disk');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The disk [public] named by [webx-inbox.disk] is public');

        $this->disks()->assert('public', 'webx-inbox.disk');
    }
}
