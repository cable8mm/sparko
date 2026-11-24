<div id="sparko-portal" data-billable-id="{{ $billable->getKey() }}">
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
