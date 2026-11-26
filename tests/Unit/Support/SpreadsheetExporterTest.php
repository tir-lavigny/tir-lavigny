<?php

namespace Tests\Unit\Support;

use App\Support\SpreadsheetExporter;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SpreadsheetExporterTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/invoicing-export-test-'.Str::uuid();
        mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles() as $file) {
            @unlink($file);
        }

        @rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_removes_the_temporary_file_when_generating_rows_throws(): void
    {
        $rows = (function (): iterable {
            yield ['ok'];

            throw new RuntimeException('export crashed');
        })();

        try {
            (new SpreadsheetExporter($this->temporaryDirectory))->download(
                'test.csv',
                'csv',
                ['Column'],
                $rows,
            );

            $this->fail('The export should have thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('export crashed', $exception->getMessage());
        }

        $this->assertSame([], $this->temporaryFiles());
    }

    public function test_removes_the_temporary_file_after_streaming(): void
    {
        $response = (new SpreadsheetExporter($this->temporaryDirectory))->download(
            'test.csv',
            'csv',
            ['Column'],
            [['value']],
        );

        $this->assertCount(1, $this->temporaryFiles());

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $this->assertStringContainsString('value', $content);
        $this->assertSame([], $this->temporaryFiles());
    }

    /**
     * @return array<int, string>
     */
    private function temporaryFiles(): array
    {
        return glob($this->temporaryDirectory.'/invoicing-export-*') ?: [];
    }
}
