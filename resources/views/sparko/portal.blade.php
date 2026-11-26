<div id="sparko-portal" data-billable-id="{{ $billable->getKey() }}">
    <a href="{{ $returnUrl }}" data-return>돌아가기</a>

    @if ($subscription !== null)
        <section data-sparko-subscription
                 data-status="{{ $subscription->status }}"
                 data-plan-id="{{ $subscription->plan_id }}"
                 data-seat-quantity="{{ $subscription->seat_quantity }}"
                 data-next-payment="{{ $formatter->date($subscription->next_charge_at) }}">
            <span data-renewal-amount>{{ $formatter->currency((int) $subscription->renewal_amount) }}</span>
            @if ($subscription->status === 'CANCELLED')
                <form method="POST" action="{{ route('sparko.subscription.resume', $billable) }}">
                    @csrf
                    <button type="submit" data-resume>구독 재개</button>
                </form>
            @else
                <form method="POST" action="{{ route('sparko.subscription.cancel', $billable) }}">
                    @csrf
                    <button type="submit" data-cancel>구독 취소</button>
                </form>
            @endif
        </section>
        <section data-sparko-history-plan data-plan-id="{{ $subscription->plan_id }}" data-seat-quantity="{{ $subscription->seat_quantity }}"></section>
    @endif

    @if (config('discounts.enabled'))
        <section data-sparko-promotion>
            <form method="POST" action="{{ route('sparko.discount.store', $billable) }}">
                @csrf
                <input type="text" name="code" data-promotion-code>
                <button type="submit" data-apply-promotion>쿠폰 적용</button>
            </form>
        </section>
    @endif

    <section data-sparko-payments>
        @foreach ($payments as $payment)
            <article data-payment-id="{{ $payment->payment_id }}" data-status="{{ $payment->status }}">
                <time>{{ $formatter->date($payment->paid_at) }}</time>
                <span data-amount>{{ $formatter->currency((int) $payment->amount) }}</span>
                <span data-order-name>{{ $payment->order_name }}</span>
                @if ($payment->receipt_url)
                    <a href="{{ $payment->receipt_url }}" data-receipt>영수증</a>
                @endif
                <a href="{{ route('sparko.invoice.download', [$billable, $payment]) }}" data-invoice>인보이스</a>
            </article>
        @endforeach
    </section>

    <section data-sparko-plans>
        @foreach ($plans as $plan)
            <article data-plan-id="{{ $plan['id'] }}" @class(['archived' => $plan['archived'], 'current' => $plan['is_current']])>
                <h2>{{ $plan['name'] }}</h2>
                <p>{{ $plan['description'] }}</p>
                @foreach ($plan['prices'] as $interval => $amount)
                    <span data-price-interval="{{ $interval }}">{{ $formatter->currency((int) $amount) }}</span>
                @endforeach
                @if ($plan['trial_days'] !== null)
                    <span data-trial-days="{{ $plan['trial_days'] }}">{{ $plan['trial_days'] }}일 무료 체험</span>
                @endif
                @if ($plan['incentive'] !== null)
                    <span data-incentive>{{ $plan['incentive'] }}</span>
                @endif
                @foreach ($plan['features']['included'] as $feature)
                    <span data-feature-included>{{ $feature }}</span>
                @endforeach
                @foreach ($plan['features']['unavailable'] as $feature)
                    <span data-feature-unavailable>{{ $feature }}</span>
                @endforeach
                @if ($plan['archived'])
                    <span data-archived>보관됨</span>
                @endif
                @if (! $plan['selectable'])
                    <span data-not-selectable>선택 불가</span>
                @endif
            </article>
        @endforeach
    </section>
</div>
@vite(['resources/js/portal.jsx'])
