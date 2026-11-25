<div id="sparko-portal" data-billable-id="{{ $billable->getKey() }}">
    @if ($subscription !== null)
        <section data-sparko-subscription
                 data-status="{{ $subscription->status }}"
                 data-plan-id="{{ $subscription->plan_id }}"
                 data-seat-quantity="{{ $subscription->seat_quantity }}"
                 data-next-payment="{{ $subscription->next_charge_at?->toIso8601String() ?? '' }}">
            <span data-renewal-amount>{{ $subscription->renewal_amount }}</span>
        </section>
    @endif
    @if ($subscription !== null)
        <section data-sparko-history-plan data-plan-id="{{ $subscription->plan_id }}" data-seat-quantity="{{ $subscription->seat_quantity }}"></section>
    @endif
    <section data-sparko-payments>
        @foreach ($payments as $payment)
            <article data-payment-id="{{ $payment->payment_id }}" data-status="{{ $payment->status }}">
                <time>{{ $payment->paid_at?->toIso8601String() ?? '' }}</time>
                <span data-amount>{{ $payment->amount }}</span>
                <span data-order-name>{{ $payment->order_name }}</span>
                @if ($payment->receipt_url)
                    <a href="{{ $payment->receipt_url }}" data-receipt>receipt</a>
                @endif
                <a href="{{ route('sparko.invoice.download', [$billable, $payment]) }}" data-invoice>invoice</a>
            </article>
        @endforeach
    </section>
    <section data-sparko-plans>
        @foreach ($plans as $plan)
            <article data-plan-id="{{ $plan['id'] }}" @class(['archived' => $plan['archived'], 'current' => $plan['is_current']])>
                <h2>{{ $plan['name'] }}</h2>
                <p>{{ $plan['description'] }}</p>
                @foreach ($plan['prices'] as $interval => $amount)
                    <span data-price-interval="{{ $interval }}">{{ $amount }}</span>
                @endforeach
                @if ($plan['trial_days'] !== null)
                    <span data-trial-days="{{ $plan['trial_days'] }}"></span>
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
                    <span data-archived></span>
                @endif
                @if (! $plan['selectable'])
                    <span data-not-selectable></span>
                @endif
            </article>
        @endforeach
    </section>
</div>
@vite(['resources/js/portal.jsx'])
