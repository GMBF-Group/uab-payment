# 💳 UAB Payment Laravel Package

A Laravel package to integrate UAB TransactEase v1.5 Payment Gateway.

---

## 📑 Table of Contents

- [Features](#-features)
- [Installation](#-installation)
- [Configuration](#%EF%B8%8F-configuration)
- [Example Usage](#-example-usage)
- [Methods](#-methods)
- [Security Notes](#-security-notes)
- [License](#-license)
- [Credits](#-credits)

---

## ✅ Features

- Generate signed UAB payment form
- Secure callback and redirect signature verification
- Transaction status check
- Token-based login flow
- Designed for Laravel projects

---

## 📦 Installation

Install via Composer:

```bash
composer require gmbfgp/uabpayment
```

## ⚙️ Configuration

1. Publish Config File:

```bash
php artisan vendor:publish --provider="Gmbf\Uabpayment\UabpaymentServiceProvider"
```
2. Add the following to your .env file:

```bash
UAB_MERCHANT_ID=your_merchant_id
UAB_MERCHANT_CHANNEL=your_channel
UAB_ACCESS_KEY=your_access_key
UAB_SECRET_KEY=your_secret_key
UAB_INS_ID=your_inst_id
UAB_CLIENT_SECRET=your_client_secret
UAB_PAYMENT_METHOD=your_payment_method
UAB_PAYMENT_URL=https://uat-uab.com/payment
UAB_PAYMENT_EXPIRE=1800
```

## 🚀 Example Usage

```bash
use App\Services\UabPaymentService;

$uab = new UabPaymentService();

$form = $uab->uab(15000, [
        'BillToForename' => 'John',
        'BillToSurname' => 'Depp',
        'BillToPhone' => '9591234567',
        'BillToEmail' => 'test@example.com',
]);

// Blade View
<form method="POST" action="{{ $form['url'] }}">
    {!! $form['values'] !!}
    <button type="submit">Pay Now</button>
</form>
```
## 🧰 Methods
1. ### uab($amount, array $extraFields): array
- Generates the URL and signed form fields to initiate a UAB payment.

Example:
```bash
//input
Uabpayment::uab(10000, [
        'BillToForename' => 'John',
        'BillToSurname' => 'Depp',
        'BillToPhone' => '9591234567',
        'BillToEmail' => 'test@example.com',
]);

//output
[
    'url' => 'https://{uab_url}/Payments/Request',
    'values' => [
        "<input type='hidden' id='amount' name='amount' value='10000' />
        <input type='hidden' id='BillToForename' name='BillToForename' value='John' />
        <input type='hidden' id='BillToSurname' name='BillToSurname' value='Depp' />
        ..."
    ]
]
```

 
2. ### checkCallbackSignature(array $data): bool
- Validates the HMAC signature received from UAB in a callback.

Example:
```bash
//input
$isValid = Uabpayment::checkCallbackSignature($request->all());

//output
true or false
```


3. ### checkRedirectSignature(array $data): bool
- Validates the redirect signature (used in front-end return/cancel URLs).

Example:
```bash
//input
$isValid = Uabpayment::checkRedirectSignature($request->all());

//output
true or false
```


4. ### getSignString(array $fields): string
- Generates the sign string used before hashing, based on sorted key-value pairs.

Example:
```bash
//input
$signString = Uabpayment::getSignString([
    'amount' => 10000,
    'merchant_id' => 'UAB123456',
    'timestamp' => '20250604140000',
]);

//output
"amount=10000,merchant_id=UAB123456,timestamp=20250604140000"
```


5. ### hashSignature(string $signString): string
- Creates an HMAC hash using your app’s secret key.

Example:
```bash
//input
$signString = "amount=10000,merchant_id=UAB123456,timestamp=20250604140000";
$signature = Uabpayment::hashSignature($signString);

//output
"f3a1a8a6d8b4f5c29e10b02c3d56cabc09ff2f7d"
```

6. ### generateMsgInfo(string $msgType): array
- Generates a standard message information structure for UAB API requests.
```bash
//input
$this->generateMsgInfo('LOGIN');

//output
[
    'VersionNo' => '1.0.0',
    'MsgID'     => 'MUAB1234567890123456000012', // Unique message ID
    'TimeStamp' => '20250605123045',
    'MsgType'   => 'LOGIN',
    'InsID'     => 'UAB123456',
]
```

7. ### getLoginToken(): ?string
- Makes an API request to UAB's login endpoint to retrieve an access token using client credentials.
```bash
//input
$token = $uabService->getLoginToken();

//output
'eyJhbGciOi...dXNzIn0.abc123' or null
```

## 🔒 Security Notes

-Always use checkCallbackSignature() on UAB’s callback endpoint.

-Ensure system time is synced to avoid signature mismatch errors.

-Never expose your secret key or credentials in frontend code.

## 📝 License

MIT License — See the LICENSE file for more information.

## 👨‍💻 Credits

Made by Pyae Sone Phyo for GMBF Tech.

