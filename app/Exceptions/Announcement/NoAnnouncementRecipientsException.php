<?php

declare(strict_types=1);

namespace App\Exceptions\Announcement;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 配信対象の受講生が 0 人のときにお知らせを配信しようとした際の例外(HTTP 422)。
 * `Announcement\StoreAction` が throw し、配信履歴は作らない(PM 確認済)。
 * Handler がフォームへ戻して Flash error で表示する。
 */
final class NoAnnouncementRecipientsException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('配信対象の受講生がいないため、配信できませんでした。', $previous);
    }
}
