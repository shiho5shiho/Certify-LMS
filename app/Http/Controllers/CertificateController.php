<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\UseCases\Certificate\DownloadAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 修了証 PDF のダウンロード Controller。認可は CertificatePolicy::download(本人 / 担当コーチ / admin)。
 */
class CertificateController extends Controller
{
    public function download(Certificate $certificate, DownloadAction $action): StreamedResponse
    {
        $this->authorize('download', $certificate);

        return $action($certificate);
    }
}
