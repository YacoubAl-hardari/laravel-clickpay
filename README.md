# Laravel ClickPay Package

A simple, all-in-one Laravel package for integrating with the ClickPay Transaction API (hosted, managed, own-form), 3D‑Secure redirect handling, follow‑up transactions (refund/void), transaction & token queries, and PayPage options (`framed`, `hide_shipping`).

---

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Usage](#usage)

   * [Basic Hosted Payment](#basic-hosted-payment)
   * [Managed‑Form / Token Payment](#managed-form--token-payment)
   * [Own‑Form / Card‑Details Payment](#own-form--card-details-payment)
   * [Including Customer Details](#including-customer-details)
   * [3D‑Secure Redirect Handling](#3d-secure-redirect-handling)
   * [Query Transaction](#query-transaction)
   * [Follow‑Up (Refund / Void)](#follow-up-refund--void)
   * [Token Operations](#token-operations)
5. [Error Handling](#error-handling)
6. [PayPage Options](#paypage-options)
7. [PCI DSS Note](#pci-dss-note)

---

## Requirements

* PHP 8.0+
* Laravel 8.x or newer
* A ClickPay **Profile ID** & **Server Key**
* Guzzle HTTP (automatically installed via Composer)

---

## Installation

1. **Require the package** via Composer:

   ```bash
   composer require yacoubalhaidari/laravel-clickpay
   ```

2. **Publish the config** file:

   ```bash
   php artisan vendor:publish \
     --provider="ClickPay\Providers\ClickPayServiceProvider" \
     --tag=config
   ```

3. (Optional) **Add the Facade** in `config/app.php`:

   ```php
   'aliases' => [
       // ...
       'ClickPay' => ClickPay\Facades\ClickPay::class,
   ],
   ```

---

## Configuration

Edit `config/clickpay.php`:

```php
return [
    'profile_id'   => env('CLICKPAY_PROFILE_ID', 0),
    'server_key'   => env('CLICKPAY_SERVER_KEY', ''),
    'base_url'     => env('CLICKPAY_BASE_URL', 'https://secure.clickpay.com.sa'),
    'return_url'   => env('CLICKPAY_RETURN_URL', null),
    'callback_url' => env('CLICKPAY_CALLBACK_URL', null),

    // المجموعات المسموحة
    'classes'           => ['ecom','moto','cont'],
    'follow_up_types'   => ['refund','void'],
    'page_options'      => ['framed','hide_shipping'],
];
```

Add to your `.env`:

```dotenv
CLICKPAY_PROFILE_ID=46600
CLICKPAY_SERVER_KEY=your_server_key_here
CLICKPAY_RETURN_URL=https://yourdomain.com/payment/return
CLICKPAY_CALLBACK_URL=https://yourdomain.com/payment/callback
```

---

## Usage

Inject `ClickPay\ClickPayService` into your controller, or use the `ClickPay` facade.

### Basic Hosted Payment

```php
use ClickPay\Facades\ClickPay;

$result = ClickPay::transaction([
    'tran_type'        => 'sale',
    'tran_class'       => 'ecom',
    'cart_id'          => (string) Str::uuid(),
    'cart_description' => 'Order #1234',
    'cart_currency'    => 'AED',
    'cart_amount'      => 100.00,
], [
    'framed'        => true,
    'hide_shipping' => true,
]);
```

### Managed‑Form / Token Payment

```php
$result = ClickPay::transaction([
    'tran_type'        => 'sale',
    'tran_class'       => 'ecom',
    'cart_id'          => 'ORDER-789',
    'cart_description' => 'Monthly Subscription',
    'cart_currency'    => 'AED',
    'cart_amount'      => 50.00,
    'payment_token'    => $jsToken,
]);
```

### Own‑Form / Card‑Details Payment

```php
$result = ClickPay::transaction([
    'tran_type'        => 'sale',
    'tran_class'       => 'ecom',
    'cart_id'          => 'EVENT-001',
    'cart_description' => 'Concert Ticket',
    'cart_currency'    => 'AED',
    'cart_amount'      => 75.50,
    'card_details'     => [
        'pan'          => '4111111111111111',
        'expiry_month' => 12,
        'expiry_year'  => 25,
        'cvv'          => '123',
    ],
]);
```

### Including Customer Details

You can include `customer_details` in two ways:

1. **Auto‑build defaults** by setting:

   ```php
   'include_customer_details' => true,
   ```
2. **Provide specific fields**:

   ```php
   'name'    => 'John Smith',
   'email'   => 'jsmith@example.com',
   'phone'   => '971111111111',
   'street1' => '404, 11th St',
   'city'    => 'Dubai',
   'state'   => 'DU',
   'country' => 'AE',
   'zip'     => '00000',
   'ip'      => request()->ip(),
   ```

The package will automatically collect these into a `customer_details` object, filling any missing values with defaults.

### 3D‑Secure Redirect Handling

```php
public function paymentReturn(Request $req)
{
    try {
        $data = ClickPay::handleReturn($req->all());
        // contains: tranRef, respStatus, respCode, token, customerEmail, etc.
    } catch (Exception $e) {
        // handle invalid/missing signature
    }
}
```

### Query Transaction

```php
$status = ClickPay::query($tranRef);
```

### Follow‑Up (Refund / Void)

```php
$refund = ClickPay::followUp('refund', $tranRef, 25.00);
```

### Token Operations

```php
$info   = ClickPay::tokenQuery($token);
$delete = ClickPay::deleteToken($token);
```

---

## Error Handling

Throws `ClickPay\Exceptions\ClickPayException` with Arabic messages:

* الحقل "X" مطلوب
* نوع الصف "X" غير صالح
* خيار الصفحة "X" غير مدعوم
* توقيع إعادة التوجيه غير صالح

---

## PayPage Options

* `framed` → عرض الصفحة داخل iFrame
* `hide_shipping` → إخفاء تفاصيل الشحن من PayPage

---

Enjoy seamless ClickPay integration in your Laravel app! Feel free to open issues or contribute.
