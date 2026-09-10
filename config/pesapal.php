<?php
/**
 * Pesapal API 3.0 (live). Override with PESAPAL_* on the host.
 */
return [
    'consumer_key' => getenv('PESAPAL_CONSUMER_KEY') ?: 'wBxb8DA4uE0AKXsN35k0L0Auw6Si8Mut',
    'consumer_secret' => getenv('PESAPAL_CONSUMER_SECRET') ?: '6HQvYhJP0bRKpKS3uLHmlMokn8I=',
    'base' => rtrim((string) (getenv('PESAPAL_BASE') ?: 'https://pay.pesapal.com/v3/api'), '/'),
    'ipn_url' => (string) (getenv('PESAPAL_IPN_URL') ?: ''),
];
