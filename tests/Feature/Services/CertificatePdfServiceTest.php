<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Certificate;
use App\Services\CertificatePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificatePdfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_pdf_file_at_pdf_path(): void
    {
        Storage::fake('private');
        $certificate = Certificate::factory()->create();

        app(CertificatePdfService::class)->generate($certificate);

        Storage::disk('private')->assertExists($certificate->pdf_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($certificate->pdf_path));
    }
}
