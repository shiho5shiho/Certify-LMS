<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\MeetingQuota\StripeRequestFailedException;
use App\Http\Requests\MeetingQuota\CheckoutCreateRequest;
use App\Models\MeetingPack;
use App\UseCases\MeetingQuota\CreateCheckoutAction;
use App\UseCases\MeetingQuota\IndexPurchasablePacksAction;
use App\UseCases\MeetingQuota\ShowCheckoutSuccessAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 受講生の追加面談購入(購入画面 / Stripe の決済画面への遷移 / 決済完了画面)。
 *
 * `role:student` / `active-learning` middleware で、学習中の受講生以外は 403。
 */
class MeetingQuotaCheckoutController extends Controller
{
    private const FAILED_MESSAGE = '決済画面を開けませんでした。時間をおいてもう一度お試しください。';

    public function select(IndexPurchasablePacksAction $action): View
    {
        return view('meeting-quota.checkout-select', [
            'plans' => $action(),
        ]);
    }

    public function create(CheckoutCreateRequest $request, CreateCheckoutAction $action): RedirectResponse
    {
        $meetingPack = MeetingPack::findOrFail($request->validated('meeting_pack_id'));

        try {
            $checkoutUrl = $action($request->user(), $meetingPack);
        } catch (StripeRequestFailedException $e) {
            Log::warning('Stripe の決済画面を作成できませんでした。', [
                'user_id' => $request->user()->id,
                'meeting_pack_id' => $meetingPack->id,
                'exception' => $e,
            ]);

            return redirect()->route('meeting-quota.checkout.select')->with('error', self::FAILED_MESSAGE);
        }

        return redirect()->away($checkoutUrl);
    }

    public function success(Request $request, ShowCheckoutSuccessAction $action): View
    {
        $sessionId = $request->query('session_id');

        return view('meeting-quota.success', [
            'payment' => $action($request->user(), is_string($sessionId) ? $sessionId : null),
        ]);
    }
}
