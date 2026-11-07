<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Exceptions\Certification\CertificatePdfNotFoundException;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 修了証 PDF をファイル添付形式で返すユースケース。認可は Controller(CertificatePolicy::download)で判定済の前提。
 *
 * ダウンロード時のファイル名は `certificate-{修了証ID}.pdf`。
 */
final class DownloadAction
{
    /**
     * @throws CertificatePdfNotFoundException PDF ファイルが保管領域に存在しない
     */
    public function __invoke(Certificate $certificate): StreamedResponse
    {
        $disk = Storage::disk('private');

        if (! $disk->exists($certificate->pdf_path)) {
            throw new CertificatePdfNotFoundException;
        }

        return $disk->download($certificate->pdf_path, "certificate-{$certificate->id}.pdf");
    }
}
