<?php
namespace Gmbfgp\Uabpayment;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class UabPaymentService
{
    protected $merchant_id;
    protected $merchant_channel;
    protected $merchant_access_key;
    protected $secret_key;
    protected $ins_id;
    protected $client_secret;
    protected $payment_method;
    protected $payment_url;
    protected $payment_expire_in_second;
    protected $callback_url;
    protected $success_url;
    protected $fail_url;

    public function __construct(){
        $this->merchant_id = config('payment.uab.merchant_id');
        $this->merchant_channel = config('payment.uab.merchant_channel');
        $this->merchant_access_key = config('payment.uab.merchant_access_key');
        $this->secret_key = config('payment.uab.secret_key');
        $this->ins_id = config('payment.uab.ins_id');
        $this->client_secret = config('payment.uab.client_secret');
        $this->payment_method = config('payment.uab.payment_method');
        $this->payment_url = config('payment.uab.payment_url');
        $this->payment_expire_in_second = config('payment.uab.payment_expire_in_second');
        $this->callback_url = config('payment.uab.payment_callback_url');
        $this->success_url = config('payment.uab.payment_success_url');
        $this->fail_url = config('payment.uab.payment_failed_url');
    }

    public function uab($totalAmount,array $extraFields) : array
    {
        $baseFields = [
            'MerchantUserID' => $this->merchant_id,
            'AccessKey' => $this->merchant_access_key,
            'Channel' => $this->merchant_channel,
            'RequestID' => "",
            'PaymentMethod' => $this->payment_method,
            'Amount' => number_format($totalAmount, 2, '.', ''),
            'Currency' => 'MMK',
            'BillToAddressLine1' => '',
            'BillToAddressLine2' => '',
            'BillToAddressCity' => '',
            'BillToAddressPostalCode' => '11041',
            'BillToAddressState' => '',
            'BillToAddressCountry' => 'MM',
            'BillToForename' => '',
            'BillToSurname' => '',
            'BillToPhone' => '',
            'BillToEmail' => '',
            'ExpiredInSeconds' => $this->payment_expire_in_second,
            'Remark' => '',
            'UserDefined1' => '',
            'UserDefined2' => '',
            'UserDefined3' => '',
            'UserDefined4' => '',
            'UserDefined5' => '',
            'SignedDateTime' => now()->format('Y-m-d\TH:i:s'),
        ];

        $fields = array_merge($baseFields, $extraFields);
        $fields = $this->getSignatureAndSignedField('POST', 'Payments/Request', $fields['SignedDateTime'], $fields['RequestID'], $fields);
        $payment_url = $this->payment_url . 'Payments/Request';

        return $this->getFormData($fields, $payment_url);
    }

    public function getSignatureAndSignedField(string $method, string $uri, string $signedDateTime, string $requestId, array $fields) : array{
        $signedFields = array_keys($fields);

        $signatureString = "$method|$uri|$signedDateTime|$requestId|";
        foreach ($signedFields as $key) {
            $signatureString .= "$key=" . $fields[$key] . ",";
        }
        $signatureString = rtrim($signatureString, ',');

        $fields['Signature'] = $this->hashSignature($signatureString);

        $fields['SignedFields'] = implode(',', $signedFields);

        return $fields;
    }

    public function getFormData(array $fields, string $payment_url) : array {
        $form['url'] = $payment_url;

        $form['values'] = '';
        foreach ($fields as $key => $value) {
            $form['values'] .= "<input type='hidden' id='" . htmlspecialchars($key, ENT_QUOTES) . "' name='" . htmlspecialchars($key, ENT_QUOTES) . "' value='" . htmlspecialchars($value, ENT_QUOTES) . "' />\n";
        }
        return $form;
    }

    public function checkCallbackSignature(Request $request) : bool{
        $access_key = $request->header('X-Auth-AccessKey');
        $method = $request->method();
        $url = $this->callback_url;
        $timestamp = $request->header('X-Auth-Timestamp');
        $nonce = $request->header('X-Auth-Nonce');
        $providedSignature = $request->header('X-Auth-Signature');
        $payloadJson = $request->getContent();

        $signatureString = "{$method}|{$url}|{$timestamp}|{$nonce}|{$payloadJson}";
        $generatedSignature = $this->hashSignature($signatureString);

        if (!hash_equals($generatedSignature,$providedSignature)) {
            \Log::error('Callback Signature verification failed.');
            return false;
        }
        return true;
    }

    public function checkRedirectSignature(string $method, array $data, bool $status) : bool{
        $providedSignature = $data['Signature'];
        unset($data['Signature']);

        $url = $status ? $this->success_url : $this->fail_url;
        $request_id = @$data['RequestID'];
        $payload = $this->getSignString($data);

        $signString = "{$method}|{$url}|{$request_id}|{$payload}";

        $hashedSignature = $this->hashSignature($signString);

        if (!hash_equals($hashedSignature, $providedSignature)) {
            \Log::error('Redirect Page Signature verification failed.');
            return false;
        }
        return true;
    }

    public function getSignString(array $fields): string
    {
        $signStringParts = [];
        foreach ($fields as $key => $value) {
            $signStringParts[] = "{$key}={$value}";
        }

        return implode(',', $signStringParts);
    }

    public function hashSignature($signString) : string{
        $signature = base64_encode(
            hash_hmac('sha256', $signString, $this->secret_key, true)
        );
        \Log::info("Generated Signature: $signature");
        return $signature;
    }

    public function generateMsgInfo(string $msgType): array
    {
        $timestamp = now()->format('YmdHis');
        $serial = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $msgId = 'M' . $this->ins_id . $timestamp . $serial;

        return [
            'VersionNo' => '1.0.0',
            'MsgID'     => $msgId,
            'TimeStamp' => $timestamp,
            'MsgType'   => $msgType,
            'InsID'     => $this->ins_id,
        ];
    }

    //auth token api request
    public function getLoginToken() : ?string{
        $msgInfo = $this->generateMsgInfo('LOGIN');

        $payload = [
            'MsgInfo' => $msgInfo,
            'MsgData' => [
                'ClientID'     => $this->merchant_id,
                'ClientSecret' => $this->client_secret,
                'GrantType'    => 'client_credentials',
            ]
        ];

        try {
            $client = new Client();
            $response = $client->post($this->payment_url . 'api/login', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $payload,
            ]);

            $responseBody = json_decode($response->getBody(), true);

            if (isset($responseBody['MsgData']['AccessToken'])) {
                \Log::info('Uab Login token received:', $responseBody);
                return $responseBody['MsgData']['AccessToken'];
            } else {
                \Log::error('Uab Login failed: No access token in response.', $responseBody);
                return null;
            }

        } catch (\Exception $e) {
            \Log::error('Uab Login API request failed: ' . $e->getMessage());
            return null;
        }
    }

    //status api request
    public function getTransactionStatus($requestId) : ?array
    {
        try {
            $timestamp = now()->format('Y-m-d\TH:i:s');

            $msgInfo = $this->generateMsgInfo('GET_TRANSACTION_STATUS');
            $msgId = $msgInfo['MsgID'];

            $msgData = [
                'RequestID'       => $requestId,
                'MerchantUserID'  => $this->merchant_id,
            ];

            $payload = [
                'MsgInfo' => $msgInfo,
                'MsgData' => $msgData,
            ];

            $signString = "POST|api/transaction/status|{$timestamp}|{$msgId}|" . json_encode($payload);
            $signature = $this->hashSignature($signString);
            $client = new Client();

            $accessToken = $this->getLoginToken();
            if (!$accessToken) {
                \Log::error('Failed to retrieve access token for transaction status.');
                return null;
            }

            $response = $client->post($this->payment_url . 'api/transaction/status', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-Auth-AccessKey' => $this->merchant_access_key,
                    'X-Auth-Timestamp' => $timestamp,
                    'X-Auth-Nonce' => $msgId,
                    'X-Auth-Signature' => $signature
                ],
                'json' => $payload,
            ]);

            $responseBody = json_decode($response->getBody(), true);
            \Log::info('Uab Transaction status response:', $responseBody);

            return $responseBody;

        } catch (\Exception $e) {
            \Log::error('Uab Transaction status API failed: ' . $e->getMessage());
            return null;
        }
    }
}
