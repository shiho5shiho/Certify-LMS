<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 修了証レコードはあるが、PDF ファイルが保管領域に存在しない際の例外（HTTP 404）。
 * `Certificate\DownloadAction` から throw され、既存の 404 エラー画面が表示される。
 */
final class CertificatePdfNotFoundException extends NotFoundHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('修了証ファイルが見つかりません。', $previous);
    }
}
