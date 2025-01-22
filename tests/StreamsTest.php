<?php

namespace Spiral\Tests\Streams;

use PHPUnit\Framework\TestCase;
use Spiral\Files\Files;
use Spiral\Files\FilesInterface;
use Spiral\Streams\StreamWrapper;
use Nyholm\Psr7\Stream;

class StreamsTest extends TestCase
{
    private const FIXTURE_DIRECTORY = __DIR__ . '/fixtures';

    public function setUp(): void
    {
        $files = new Files();
        $files->ensureDirectory(self::FIXTURE_DIRECTORY, FilesInterface::RUNTIME);
    }

    public function tearDown(): void
    {
        $files = new Files();
        $files->deleteDirectory(self::FIXTURE_DIRECTORY, true);
    }

    public function testGetUri()
    {
        $stream = Stream::create();
        $stream->write('sample text');

        $filename = StreamWrapper::getFilename($stream);

        $this->assertFileExists($filename);
        $this->assertSame(strlen('sample text'), filesize($filename));
        $this->assertSame(md5('sample text'), md5_file($filename));

        $newFilename = self::FIXTURE_DIRECTORY . '/test.txt';
        copy($filename, $newFilename);

        $this->assertFileExists($newFilename);
        $this->assertSame(strlen('sample text'), filesize($newFilename));
        $this->assertSame(md5('sample text'), md5_file($newFilename));

        //Rewinding
        $this->assertFileExists($newFilename);
        $this->assertSame(strlen('sample text'), filesize($newFilename));
        $this->assertSame(md5('sample text'), md5_file($newFilename));

        $this->assertTrue(StreamWrapper::has($filename));
        $this->assertFalse(StreamWrapper::has($newFilename));
    }

    public function testGetResource()
    {
        $stream = Stream::create();
        $stream->write('sample text');

        $this->assertFalse(StreamWrapper::has($stream));
        $resource = StreamWrapper::getResource($stream);
        $this->assertTrue(StreamWrapper::has($stream));

        $this->assertIsResource($resource);
        $this->assertSame('sample text', stream_get_contents($resource, -1, 0));

        //Rewinding
        $this->assertSame('sample text', stream_get_contents($resource, -1, 0));

        fseek($resource, 7);
        $this->assertSame('text', stream_get_contents($resource, -1));
        $this->assertSame('sample', stream_get_contents($resource, 6, 0));
    }

    /**
     * @requires PHP < 8.0
     */
    public function testException()
    {
        try {
            fopen('spiral://non-exists', 'rb');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('failed to open stream', $e->getMessage());
        }

        try {
            filemtime('spiral://non-exists');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('stat failed', $e->getMessage());
        }
    }

    /**
     * @requires PHP >= 8.0
     */
    public function testExceptionPHP8()
    {
        try {
            fopen('spiral://non-exists', 'rb');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Failed to open stream', $e->getMessage());
        }

        try {
            filemtime('spiral://non-exists');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('stat failed', $e->getMessage());
        }
    }

    public function testWriteIntoStream()
    {
        $stream = Stream::create(fopen('php://temp', 'wrb+'));
        $file = StreamWrapper::getFilename($stream);

        file_put_contents($file, 'test');

        $this->assertSame('test', file_get_contents($file));

        StreamWrapper::release($file);
    }
}
