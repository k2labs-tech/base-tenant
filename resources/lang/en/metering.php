<?php

declare(strict_types=1);

return [

    'title' => 'Usage',
    'description' => 'What this account has consumed against its plan.',

    'limit_reached' => 'You have reached the :metric allowance included in your plan.',
    'limit_reached_title' => 'Plan limit reached',
    'limit_reached_body' => 'This account has used :current of the :limit included in its plan. Upgrading raises the allowance straight away.',
    'upgrade' => 'See plans',
    'go_back' => 'Go back',

    'unlimited' => 'Unlimited',
    'scope' => 'Scope',
    'search_placeholder' => 'Search by name or key...',
    'of_limit' => 'of :limit',
    'count_summary' => '{0} No metrics|{1} 1 metric|[2,*] :count metrics',
    'at_limit_summary' => '{1} 1 metric has reached its limit|[2,*] :count metrics have reached their limit',
    'approaching_summary' => '{1} 1 metric is near its limit|[2,*] :count metrics are near their limit',
    'no_limit' => 'Not capped',
    'used_of' => ':used of :limit',
    'remaining' => ':count left',

    'column' => [
        'metric' => 'Metric',
        'usage' => 'Usage',
        'limit' => 'Included in plan',
        'remaining' => 'Remaining',
        'period' => 'Period',
    ],

    'period' => [
        'none' => 'Total',
        'day' => 'Today',
        'month' => 'This month',
        'year' => 'This year',
    ],

    'summary' => [
        'metrics' => 'Metrics',
        'at_limit' => 'At the limit',
        'approaching' => 'Near the limit',
    ],

    'empty' => 'Nothing is metered yet',
    'empty_hint' => 'Declare the metrics of this product in config/base-tenant.php and they will appear here.',

    'filter' => [
        'all' => 'All',
        'capped' => 'Capped by the plan',
        'uncapped' => 'Measured only',
    ],

    'notification' => [
        'approaching_subject' => 'You have used :percentage% of your :metric allowance',
        'approaching_line' => 'This account has used :value of the :limit :metric included in its plan (:percentage%).',
        'reached_subject' => 'You have reached your :metric limit',
        'reached_line' => 'This account has used all :limit of the :metric included in its plan. Anything beyond it will be refused until the plan changes or the period resets.',
        'action' => 'See usage',
        'database_title' => ':metric limit',
        'database_message' => ':value of :limit used (:percentage%).',
    ],

    /*
    | Metric names shown in the interface. A metric with no entry here falls
    | back to its key, which is readable enough to ship without.
    */
    'metrics' => [
        'storage.bytes' => 'Storage',
    ],

];
