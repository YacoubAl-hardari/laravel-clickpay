<?php

namespace ClickPay;

use GuzzleHttp\Client;
use ClickPay\Exceptions\ClickPayException;

class ClickPayService
{
    protected Client $http;
    protected array  $cfg;

    public function __construct(array $config)
    {
        $this->cfg  = $config;
        $this->http = new Client([
            'base_uri' => rtrim($config['base_url'], '/'),
            'headers'  => [
                'Authorization' => $config['server_key'],
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    /**
     * تنفيذ أي معاملة (مستضافة أو مُدارة أو النموذج الخاص).
     * يدعم تضمين customer_details واستخدام حقول العميل أو القيم الافتراضية.
     *
     * @param  array  $payload       Required: tran_type, tran_class, cart_id, cart_amount, cart_currency, cart_description
     *                              Optional: return_url, callback_url, include_customer_details,
     *                                        name, email, phone, street1, city, state, country, zip, ip
     * @param  array  $pageOptions   PayPage flags: framed, hide_shipping
     * @return array  Formatted API response
     * @throws ClickPayException
     */
    public function transaction(array $payload, array $pageOptions = []): array
    {
        // Map return_url/callback_url to return/callback
        if (isset($payload['return_url'])) {
            $payload['return'] = $payload['return_url'];
            unset($payload['return_url']);
        }
        if (isset($payload['callback_url'])) {
            $payload['callback'] = $payload['callback_url'];
            unset($payload['callback_url']);
        }

        // Build base body with defaults
        $body = [
            'profile_id' => $this->cfg['profile_id'],
            'callback'   => $this->cfg['callback_url'],
            'return'     => $this->cfg['return_url'],
        ] + $payload;

        // Validate required fields
        foreach (['tran_type','tran_class','cart_id','cart_amount','cart_currency','cart_description'] as $f) {
            if (empty($body[$f])) {
                throw new ClickPayException("الحقل '{$f}' مطلوب.");
            }
        }

        // Validate tran_class
        if (! in_array($body['tran_class'], $this->cfg['classes'])) {
            throw new ClickPayException("نوع الصف '{$body['tran_class']}' غير صالح.");
        }

        // Determine if we should build customer_details
        $includeFlag = ! empty($body['include_customer_details']);
        unset($body['include_customer_details']);

        // Check if any customer fields provided
        $custFields = ['name','email','phone','street1','city','state','country','zip','ip'];
        $hasFields  = false;
        foreach ($custFields as $f) {
            if (isset($body[$f])) {
                $hasFields = true;
                break;
            }
        }
        if ($includeFlag || $hasFields) {
            $this->buildCustomerDetails($body);
        }

        // Merge PayPage flags
        foreach ($pageOptions as $opt => $val) {
            if (! in_array($opt, $this->cfg['page_options'])) {
                throw new ClickPayException("خيار الصفحة '{$opt}' غير مدعوم.");
            }
            $body[$opt] = $val;
        }

        // Execute request
        $resp = $this->http->post('/payment/request', ['json' => $body]);
        $raw  = json_decode($resp->getBody()->getContents(), true);

        return $this->formatResult($raw);
    }

    /**
     * Extract and build customer_details, applying defaults where missing.
     */
    protected function buildCustomerDetails(array &$body): void
    {
        $defaults = [
            'name'    => 'Guest',
            'email'   => 'no-reply@example.com',
            'phone'   => '0000000000',
            'street1' => 'N/A',
            'city'    => 'N/A',
            'state'   => 'N/A',
            'country' => 'N/A',
            'zip'     => '',
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ];

        $customer = [];
        foreach ($defaults as $key => $def) {
            $customer[$key] = $body[$key] ?? $def;
            unset($body[$key]);
        }
        $body['customer_details'] = $customer;
    }

    /**
     * Handle 3D-Secure redirect return.
     *
     * @param  array $queryParams
     * @return array
     * @throws ClickPayException
     */
    public function handleReturn(array $queryParams): array
    {
        if (empty($queryParams['signature'])) {
            throw new ClickPayException('معامل التوقيع مفقود.');
        }
        $sig = $queryParams['signature'];
        unset($queryParams['signature']);

        $filtered = array_filter($queryParams, fn($v) => $v !== null && $v !== '');
        ksort($filtered);
        $qs   = http_build_query($filtered);
        $calc = hash_hmac('sha256', $qs, $this->cfg['server_key']);

        if (! hash_equals($calc, $sig)) {
            throw new ClickPayException('توقيع إعادة التوجيه غير صالح.');
        }

        return $filtered;
    }

    /**
     * Query transaction by tran_ref.
     */
    public function query(string $tranRef): array
    {
        $resp = $this->http->post('/payment/query', [
            'json' => [
                'profile_id' => $this->cfg['profile_id'],
                'tran_ref'   => $tranRef,
            ],
        ]);
        return $this->formatResult(json_decode($resp->getBody()->getContents(), true));
    }

    /**
     * Follow-up transaction (refund or void).
     *
     * @param  string $type    'refund' or 'void'
     * @param  string $tranRef
     * @param  float  $amount
     * @return array
     * @throws ClickPayException
     */
    public function followUp(string $type, string $tranRef, float $amount): array
    {
        if (! in_array($type, $this->cfg['follow_up_types'])) {
            throw new ClickPayException("نوع العملية '{$type}' غير مدعوم.");
        }
        $resp = $this->http->post('/payment/request', ['json'=>[
            'profile_id'       => $this->cfg['profile_id'],
            'tran_type'        => $type,
            'tran_ref'         => $tranRef,
            'cart_id'          => '',
            'cart_description' => '',
            'cart_currency'    => '',
            'cart_amount'      => $amount,
        ]]);
        return $this->formatResult(json_decode($resp->getBody()->getContents(), true));
    }

    /**
     * Token query.
     */
    public function tokenQuery(string $token): array
    {
        $resp = $this->http->post('/payment/token',['json'=>[
            'profile_id'=> $this->cfg['profile_id'],
            'token'      => $token,
        ]]);
        return $this->formatResult(json_decode($resp->getBody()->getContents(), true));
    }

    /**
     * Delete token.
     */
    public function deleteToken(string $token): array
    {
        $resp = $this->http->post('/payment/token/delete',['json'=>[
            'profile_id'=> $this->cfg['profile_id'],
            'token'      => $token,
        ]]);
        return json_decode($resp->getBody()->getContents(), true);
    }

    /**
     * Format API result into a consistent array.
     */
    protected function formatResult(array $data): array
    {
        return [
            'tran_ref'         => $data['tran_ref']         ?? null,
            'cart_id'          => $data['cart_id']          ?? null,
            'cart_description' => $data['cart_description'] ?? null,
            'cart_currency'    => $data['cart_currency']    ?? null,
            'cart_amount'      => isset($data['cart_amount']) ? (float)$data['cart_amount'] : null,
            'customer_details' => $data['customer_details'] ?? [],
            'payment_result'   => $data['payment_result']   ?? [],
            'payment_info'     => $data['payment_info']     ?? [],
            'redirect_url'     => $data['redirect_url']     ?? null,
        ];
    }
}
