<?php
namespace Gmbfgp\Uabpayment;

use GuzzleHttp\Client;

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

    public function __construct(){
        $this->merchant_id = config('uabpayment.merchant_id');
        $this->merchant_channel = config('uabpayment.merchant_channel');
        $this->merchant_access_key = config('uabpayment.access_key');
        $this->secret_key = config('uabpayment.secret_key');
        $this->ins_id = config('uabpayment.ins_id');
        $this->client_secret = config('uabpayment.client_secret');
        $this->payment_method = config('uabpayment.payment_method');
        $this->payment_url = config('uabpayment.payment_url');
        $this->payment_expire_in_second = config('uabpayment.payment_expire');
    }

    public function uab($totalAmount,array $extraFields)
    {
    $baseFields = [
        'MerchantUserID' => $this->merchant_id,
        'AccessKey' => $this->merchant_access_key,
        'Channel' => $this->merchant_channel,
        'PaymentMethod' => $this->payment_method,
        'Amount' => number_format($totalAmount, 2, '.', ''),
        'Currency' => 'MMK',
        'BillToAddressLine1' => '',
        'BillToAddressLine2' => '',
        'BillToAddressCity' => '',
        'BillToAddressPostalCode' => '',
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
    $fields = $this->getSignatureAndSignedField($fields);
    return $this->getFormData($fields);
}

    public function getSignatureAndSignedField(array $fields){
        $signedFields = array_keys($fields);

        $signatureString = '';
        foreach ($signedFields as $key) {
            $signatureString .= "$key=" . $fields[$key] . ",";
        }
        $signatureString = rtrim($signatureString, ',');

        $fields['Signature'] = base64_encode(
            hash_hmac('sha256', $signatureString, $this->secret_key, true)
        );

        // SignedFields and Signature
        $fields['SignedFields'] = implode(',', $signedFields);

        return $fields;
    }

    public function getFormData(array $fields){
        $form['url'] = rtrim($this->payment_url, '/') . '/Payments/Request';

        $form['values'] = '';
        foreach ($fields as $key => $value) {
            $form['values'] .= "<input type='hidden' id='" . htmlspecialchars($key, ENT_QUOTES) . "' name='" . htmlspecialchars($key, ENT_QUOTES) . "' value='" . htmlspecialchars($value, ENT_QUOTES) . "' />\n";
        }
        return $form;
    }

    public function checkCallbackSignature(array $data)
    {
        $signedFields = explode(',', $data['SignedFields']);

        $signStringParts = [];
        foreach ($signedFields as $field) {
            if ($field === 'CardType' && !isset($data[$field])) {
                continue;
            }
            $value = $data[$field];
            $signStringParts[] = $field . '=' . $value;
        }
        $signString = implode(',', $signStringParts);

        $generatedSignature = $this->hashSignature($signString);

        \Log::info("Expected Signature: $generatedSignature");
        \Log::info("Provided Signature: " . $data['Signature']);

        if (!hash_equals($generatedSignature, $data['Signature'])) {
            \Log::error('Signature verification failed.');
            return false;
        }
        return true;
    }

    public function checkRedirectSignature(array $data){
        unset($data['Signature']);

        $signString = $this->getSignString($data);
        $hashedSignature = $this->hashSignature($signString);

        if (!hash_equals($hashedSignature, $data['Signature'])) {
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

    public function hashSignature(string $signString){
        $signature = base64_encode(
            hash_hmac('sha256', $signString, $this->secret_key, true)
        );
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
    public function getLoginToken(){
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
                \Log::info('Login token received:', $responseBody);
                return $responseBody['MsgData']['AccessToken'];
            } else {
                \Log::error('Login failed: No access token in response.', $responseBody);
                return null;
            }

        } catch (\Exception $e) {
            \Log::error('Login API request failed: ' . $e->getMessage());
            return null;
        }
    }

    //status api request
    public function getTransactionStatus($requestId)
    {
        $msgInfo = $this->generateMsgInfo('GET_TRANSACTION_STATUS');

        $msgData = [
            'RequestID'       => $requestId,
            'MerchantUserID'  => $this->merchant_id,
            'AccessKey'       => $this->merchant_access_key,
        ];

        $signatureString = "RequestID={$msgData['RequestID']},MerchantUserID={$msgData['MerchantUserID']},AccessKey={$msgData['AccessKey']}";
        $signature = $this->hashSignature($signatureString);

        $payload = [
            'MsgInfo'   => $msgInfo,
            'MsgData'   => $msgData,
            'Signature' => $signature
        ];

        try {
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
                ],
                'json' => $payload,
            ]);

            $responseBody = json_decode($response->getBody(), true);
            \Log::info('Transaction status response:', $responseBody);

            return $responseBody;

        } catch (\Exception $e) {
            \Log::error('Transaction status API failed: ' . $e->getMessage());
            return null;
        }
    }
}
