<?php

declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\ServerRequestFactory;

/**
 * Multi-file upload normalization tests.
 *
 * PHP's $_FILES has a quirky structure for <input name="files[]" multiple>:
 * the tmp_name, name, size, error, and type keys are parallel arrays.
 * ServerRequestFactory must transpose them into individual UploadedFile instances.
 *
 * Previously, the factory only checked `is_string($value['tmp_name'])`,
 * silently dropping all multi-file uploads.
 *
 * @package SovereignStack\Core\Http\Tests\Unit
 */
final class MultiFileUploadTest extends TestCase
{
    public function testSingleFileUpload(): void
    {
        $files = [
            'document' => [
                'tmp_name' => '/tmp/php123',
                'size' => 1024,
                'error' => UPLOAD_ERR_OK,
                'name' => 'doc.pdf',
                'type' => 'application/pdf',
            ],
        ];

        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('POST', '/upload');
        $request = $request->withUploadedFiles(
            $this->callNormalizeUploadedFiles($files)
        );

        $uploaded = $request->getUploadedFiles();
        self::assertCount(1, $uploaded);
        self::assertInstanceOf(\Psr\Http\Message\UploadedFileInterface::class, $uploaded['document']);
        self::assertSame('doc.pdf', $uploaded['document']->getClientFilename());
    }

    public function testMultiFileUpload(): void
    {
        $files = [
            'files' => [
                'tmp_name' => ['/tmp/php111', '/tmp/php222'],
                'size' => [100, 200],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'name' => ['file1.jpg', 'file2.png'],
                'type' => ['image/jpeg', 'image/png'],
            ],
        ];

        $normalized = $this->callNormalizeUploadedFiles($files);

        self::assertArrayHasKey('files', $normalized);
        self::assertIsArray($normalized['files']);
        self::assertCount(2, $normalized['files']);

        self::assertSame('file1.jpg', $normalized['files'][0]->getClientFilename());
        self::assertSame('file2.png', $normalized['files'][1]->getClientFilename());

        self::assertSame(100, $normalized['files'][0]->getSize());
        self::assertSame(200, $normalized['files'][1]->getSize());
    }

    public function testNestedMultiFileUpload(): void
    {
        $files = [
            'gallery' => [
                'images' => [
                    'tmp_name' => ['/tmp/a', '/tmp/b'],
                    'size' => [10, 20],
                    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                    'name' => ['a.jpg', 'b.jpg'],
                    'type' => ['image/jpeg', 'image/jpeg'],
                ],
            ],
        ];

        $normalized = $this->callNormalizeUploadedFiles($files);

        self::assertArrayHasKey('gallery', $normalized);
        self::assertArrayHasKey('images', $normalized['gallery']);
        self::assertCount(2, $normalized['gallery']['images']);
        self::assertSame('a.jpg', $normalized['gallery']['images'][0]->getClientFilename());
    }

    public function testMixedSingleAndMultiFile(): void
    {
        $files = [
            'avatar' => [
                'tmp_name' => '/tmp/avatar',
                'size' => 5000,
                'error' => UPLOAD_ERR_OK,
                'name' => 'avatar.png',
                'type' => 'image/png',
            ],
            'attachments' => [
                'tmp_name' => ['/tmp/att1', '/tmp/att2'],
                'size' => [1000, 2000],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'name' => ['att1.pdf', 'att2.pdf'],
                'type' => ['application/pdf', 'application/pdf'],
            ],
        ];

        $normalized = $this->callNormalizeUploadedFiles($files);

        // Single file
        self::assertInstanceOf(\Psr\Http\Message\UploadedFileInterface::class, $normalized['avatar']);
        self::assertSame('avatar.png', $normalized['avatar']->getClientFilename());

        // Multi file
        self::assertIsArray($normalized['attachments']);
        self::assertCount(2, $normalized['attachments']);
        self::assertSame('att1.pdf', $normalized['attachments'][0]->getClientFilename());
        self::assertSame('att2.pdf', $normalized['attachments'][1]->getClientFilename());
    }

    /**
     * Call the private static normalizeUploadedFiles via reflection.
     */
    private function callNormalizeUploadedFiles(array $files): array // @phpstan-ignore-next-line
    {
        $method = new \ReflectionMethod(ServerRequestFactory::class, 'normalizeUploadedFiles');
        $method->setAccessible(true);
        return $method->invoke(null, $files);
    }
}
