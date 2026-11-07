<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\MeetingQuota\InvalidStripeWebhookException;
use App\Services\Contracts\StripeServiceContract;
use App\UseCases\MeetingQuota\HandleStripeWebhookAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Stripe からの決済結果の通知(Webhook)の受信窓口。
 *
 * 認証なしの公開エンドポイントのため、署名検証だけが正当性の担保となる。
 * 署名を検証できない通知は処理せずに 400 を返す。処理した通知・想定外の通知は 2xx を返す
 * (2xx 以外を返すと Stripe が再送する)。
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeServiceContract $stripe, HandleStripeWebhookAction $action): Response
    {
        try {
            $event = $stripe->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (InvalidStripeWebhookException $e) {
            Log::warning('Stripe の Webhook の署名を検証できませんでした。', ['exception' => $e]);

            return response('', 400);
        }

        $action($event);

        return response()->noContent();
    }
}
