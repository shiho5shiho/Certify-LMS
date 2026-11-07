<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/**
 * 修了証 PDF を生成し、private disk の `pdf_path` に保存する Service。
 *
 * 呼び出し元: 修了証受領フロー(ReceiveCertificateAction)/ Seeder。
 * 日本語は mPDF 同梱フォントを autoScriptToLang / autoLangToFont で自動選択する。
 */
class CertificatePdfService
{
    /**
     * @throws RuntimeException PDF の保存に失敗した
     */
    public function generate(Certificate $certificate): void
    {
        $certificate->loadMissing(['user', 'certification']);

        $html = view('certificates.pdf', ['certificate' => $certificate])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => storage_path('framework/cache/mpdf'),
        ]);
        $mpdf->WriteHTML($html);

        // private disk は throw=false のため、保存失敗は戻り値で検知して例外にする
        $saved = Storage::disk('private')->put(
            $certificate->pdf_path,
            $mpdf->Output('', Destination::STRING_RETURN),
        );

        if (! $saved) {
            throw new RuntimeException('修了証 PDF の保存に失敗しました。');
        }
    }
}
