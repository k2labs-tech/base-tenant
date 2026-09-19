<?php

declare(strict_types=1);

return [

    'title' => 'Domains',
    'description' => 'Where your account is served: a subdomain of ours, or a domain of your own.',
    'currently_served_at' => 'Currently served at',

    'primary' => 'Primary',

    'subdomain' => [
        'title' => 'Subdomain',
        'description' => 'Your address under our domain. Available the moment you save it.',
        'label' => 'Subdomain',
        'placeholder' => 'your-company',
        'hint' => 'Letters, digits and hyphens. Between 3 and 63 characters, and it cannot start or end with a hyphen.',
    ],

    'custom' => [
        'title' => 'Your own domain',
        'description' => 'Point a domain you control at your account. It is served once you have proved you own it.',
        'label' => 'Domain',
        'add' => 'Add domain',
        'verify' => 'Verify',
        'make_primary' => 'Make primary',
        'remove' => 'Remove',
        'remove_title' => 'Remove domain',
        'remove_confirm' => 'Remove :hostname? It will stop serving your account immediately.',
        'instructions' => 'Publish this TXT record in your DNS, then press Verify. It can take a few minutes to propagate.',
        'cname_hint' => 'Also point the domain here with a CNAME to :target.',
        'empty_title' => 'No domains yet',
        'empty_description' => 'Add a domain you control to serve your account from it.',
    ],

    'record' => [
        'type' => 'Type',
        'host' => 'Host',
        'value' => 'Value',
    ],

    'statuses' => [
        'pending' => 'Pending verification',
        'verified' => 'Verified',
        'failed' => 'Not verified',
    ],

    'subdomain_saved' => 'Subdomain updated.',
    'domain_added' => 'Domain added. Publish the DNS record to verify it.',
    'domain_verified' => 'Domain verified.',
    'domain_not_verified' => 'The record is not visible yet. DNS changes can take a few minutes.',
    'domain_removed' => 'Domain removed.',
    'primary_updated' => 'Primary domain updated.',

    'errors' => [
        'subdomain_invalid' => 'A subdomain uses letters, digits and hyphens, between :min and :max characters, and cannot start or end with a hyphen.',
        'reserved' => '":subdomain" is reserved and cannot be used.',
        'subdomain_taken' => '":subdomain" is already taken.',
        'hostname_invalid' => '":hostname" is not a valid domain.',
        'hostname_taken' => '":hostname" is already registered.',
        'hostname_is_central' => '":hostname" belongs to this product and cannot be claimed.',
        'too_many' => 'You can register up to :max domains.',
        'record_not_found' => 'The verification record was not found in DNS.',
        'account_gone' => 'The account this domain belonged to no longer exists.',
        'custom_disabled' => 'Custom domains are not available on this installation.',
        'subdomains_disabled' => 'Subdomains are not available on this installation.',
    ],

    'console' => [
        'nothing_due' => 'No domains due for verification.',
        'check_failed' => 'Could not check :hostname: :error',
        'custom_disabled' => 'Custom domains are switched off; nothing to verify.',
        'now_verified' => ':hostname is now verified.',
        'not_verified' => ':hostname could not be verified.',
        'summary' => 'Verified: :verified · Not verified: :failed',
    ],

];
