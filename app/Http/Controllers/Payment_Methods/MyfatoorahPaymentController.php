<?php

namespace App\Http\Controllers\Payment_Methods;

use App\Models\PaymentRequest;
use App\Models\User;
use App\Traits\Processor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class MyfatoorahPaymentController extends Controller
{
    use Processor;

    private string $api_key = '';
    private string $base_url = 'https://apitest.myfatoorah.com';
    private string $session_js_url = 'https://demo.myfatoorah.com/sessions/v1/session.js';

    private PaymentRequest $payment;
    private User $user;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $config = $this->payment_config('myfatoorah', 'payment_config');
        $values = null;

        if (!is_null($config) && $config->mode == 'live') {
            $values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $values = json_decode($config->test_values);
        }

        if ($values) {
            $this->api_key = (string) (env('MYFATOORAH_TOKEN') ?: ($values->api_key ?? ''));
            $this->base_url = rtrim((string) (env('MYFATOORAH_BASE_URL') ?: ($values->base_url ?? $this->base_url)), '/');
            $this->session_js_url = (string) (env('MYFATOORAH_SESSION_JS_URL') ?: ($values->session_js_url ?? $this->session_js_url));
        } else {
            $this->api_key = (string) env('MYFATOORAH_TOKEN', '');
            $this->base_url = rtrim((string) env('MYFATOORAH_BASE_URL', $this->base_url), '/');
            $this->session_js_url = (string) env('MYFATOORAH_SESSION_JS_URL', $this->session_js_url);
        }

        $this->payment = $payment;
        $this->user = $user;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if (empty($this->api_key)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        session()->put('payment_id', $data->id);

        $payer = json_decode($data['payer_information']);
        $currency = strtoupper($data['currency_code'] ?? '');
        $language = strtoupper(session('local') ?? app()->getLocale()) === 'AR' ? 'AR' : 'EN';
        $supportedCurrencies = ['SAR', 'BHD', 'AED', 'QAR', 'OMR', 'KWD', 'JOD', 'EGP'];

        // Keep payload aligned with MyFatoorah docs (Amount required; Currency optional)
        $payload = [
            'PaymentMode' => 'COMPLETE_PAYMENT',
            'Order' => [
                'Amount' => round((float) $data->payment_amount, 3),
                'ExternalIdentifier' => (string) $data->id,
            ],
            'Customer' => [
                'Name' => !empty($payer->name) ? $payer->name : 'Customer',
                'Email' => !empty($payer->email) ? $payer->email : 'customer@example.com',
                'Reference' => (string) $data->id,
            ],
            'IntegrationUrls' => [
                'Redirection' => route('myfatoorah.callback', ['payment_id' => $data->id]),
            ],
            'Language' => $language,
        ];

        if (in_array($currency, $supportedCurrencies, true)) {
            $payload['Order']['Currency'] = $currency;
        }

        $mobile = $this->formatMobile($payer->phone ?? null);
        if ($mobile) {
            $payload['Customer']['Mobile'] = $mobile;
        }

        $sessionResponse = $this->apiRequest('POST', '/v3/sessions', $payload);
        Log::info('MyFatoorah create session', [
            'url' => rtrim($this->base_url, '/') . '/v3/sessions',
            'payload' => $payload,
            'response' => $sessionResponse,
        ]);

        if (
            empty($sessionResponse['IsSuccess']) ||
            empty($sessionResponse['Data']['SessionId']) ||
            empty($sessionResponse['Data']['EncryptionKey'])
        ) {
            $message = $sessionResponse['Message'] ?? 'MyFatoorah session creation failed';
            $errors = [];
            foreach (($sessionResponse['ValidationErrors'] ?? []) as $error) {
                $errors[] = [
                    'error_code' => $error['Name'] ?? 'validation',
                    'message' => $error['Error'] ?? 'Invalid data',
                ];
            }

            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $errors ?: [
                ['error_code' => 'myfatoorah', 'message' => $message],
            ]), 400);
        }

        session()->put('myfatoorah_encryption_key', $sessionResponse['Data']['EncryptionKey']);
        session()->put('myfatoorah_session_id', $sessionResponse['Data']['SessionId']);

        $sessionId = $sessionResponse['Data']['SessionId'];
        $encryptionKey = $sessionResponse['Data']['EncryptionKey'];
        $sessionJsUrl = $this->session_js_url;
        $config = (object) [
            'session_js_url' => $sessionJsUrl,
            'base_url' => $this->base_url,
        ];

        return view('payment.payment-view-myfatoorah', compact(
            'data',
            'config',
            'sessionId',
            'encryptionKey',
            'sessionJsUrl'
        ));
    }

    public function make_payment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
            'paymentData' => 'nullable|string',
            'paymentId' => 'nullable|string',
            'sessionId' => 'nullable|string',
            'paymentCompleted' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Invalid request',
                'errors' => $this->error_processor($validator),
            ], 400);
        }

        $paymentRequest = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($paymentRequest)) {
            return response()->json(['status' => 'fail', 'message' => 'Payment not found'], 404);
        }

        $mfPaymentId = $request['paymentId'] ?? null;
        if (!$mfPaymentId && !empty($request['redirectionUrl'])) {
            $mfPaymentId = $this->extractPaymentIdFromUrl($request['redirectionUrl']);
        }

        $isPaid = false;
        $transactionId = $mfPaymentId ?: ($request['sessionId'] ?? $paymentRequest->id);

        if ($mfPaymentId) {
            $paymentDetails = $this->apiRequest('GET', '/v3/payments/' . urlencode($mfPaymentId));
            $invoiceStatus = strtoupper((string) ($paymentDetails['Data']['Invoice']['Status'] ?? ''));
            $transactionStatus = strtoupper((string) ($paymentDetails['Data']['Transaction']['Status'] ?? ''));
            $isPaid = $invoiceStatus === 'PAID' || $transactionStatus === 'SUCCESS';
            $transactionId = $paymentDetails['Data']['Transaction']['PaymentId']
                ?? $paymentDetails['Data']['Invoice']['Id']
                ?? $mfPaymentId;
        } elseif (!empty($request['paymentData'])) {
            $encryptionKey = session('myfatoorah_encryption_key');
            $decrypted = $encryptionKey ? $this->decryptPaymentData($request['paymentData'], $encryptionKey) : null;
            if ($decrypted) {
                $payload = json_decode($decrypted, true);
                $invoiceStatus = strtoupper((string) (
                    $payload['Invoice']['Status']
                    ?? $payload['InvoiceStatus']
                    ?? $payload['Status']
                    ?? ''
                ));
                $transactionStatus = strtoupper((string) (
                    $payload['Transaction']['Status']
                    ?? $payload['TransactionStatus']
                    ?? ''
                ));
                $isPaid = in_array($invoiceStatus, ['PAID', 'SUCCESS'], true)
                    || $transactionStatus === 'SUCCESS'
                    || (!empty($payload['IsSuccess']) && empty($payload['Error']));
                $transactionId = $payload['Transaction']['PaymentId']
                    ?? $payload['PaymentId']
                    ?? $payload['Invoice']['Id']
                    ?? $transactionId;
            }
        }

        if ($isPaid) {
            $this->payment::where(['id' => $paymentRequest->id])->update([
                'payment_method' => 'myfatoorah',
                'is_paid' => 1,
                'transaction_id' => $transactionId,
            ]);

            $data = $this->payment::where(['id' => $paymentRequest->id])->first();
            if (isset($data) && function_exists($data->success_hook)) {
                call_user_func($data->success_hook, $data);
            }

            return response()->json([
                'status' => 'success',
                'redirect_url' => route('myfatoorah.callback', [
                    'status' => 'success',
                    'payment_id' => $paymentRequest->id,
                ]),
            ]);
        }

        return response()->json([
            'status' => 'fail',
            'redirect_url' => route('myfatoorah.callback', [
                'status' => 'fail',
                'payment_id' => $paymentRequest->id,
            ]),
        ]);
    }

    public function callback(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $paymentId = $request['payment_id'] ?? session('payment_id');
        $data = $this->payment::where(['id' => $paymentId])->first();

        if (!$data) {
            return redirect()->route('home');
        }

        // Hosted methods may redirect here with MyFatoorah paymentId
        $mfPaymentId = $request['paymentId'] ?? $request['PaymentId'] ?? null;
        if ($mfPaymentId && (int) $data->is_paid === 0) {
            $paymentDetails = $this->apiRequest('GET', '/v3/payments/' . urlencode($mfPaymentId));
            $invoiceStatus = strtoupper((string) ($paymentDetails['Data']['Invoice']['Status'] ?? ''));
            $transactionStatus = strtoupper((string) ($paymentDetails['Data']['Transaction']['Status'] ?? ''));

            if ($invoiceStatus === 'PAID' || $transactionStatus === 'SUCCESS') {
                $this->payment::where(['id' => $data->id])->update([
                    'payment_method' => 'myfatoorah',
                    'is_paid' => 1,
                    'transaction_id' => $paymentDetails['Data']['Transaction']['PaymentId']
                        ?? $paymentDetails['Data']['Invoice']['Id']
                        ?? $mfPaymentId,
                ]);
                $data = $this->payment::where(['id' => $data->id])->first();
                if (isset($data) && function_exists($data->success_hook)) {
                    call_user_func($data->success_hook, $data);
                }
                return $this->payment_response($data, 'success');
            }
        }

        if (($request['status'] ?? '') === 'success' || (int) $data->is_paid === 1) {
            return $this->payment_response($data, 'success');
        }

        if (isset($data) && function_exists($data->failure_hook)) {
            call_user_func($data->failure_hook, $data);
        }

        return $this->payment_response($data, 'fail');
    }

    private function apiRequest(string $method, string $endpoint, array $payload = []): array
    {
        $url = $this->base_url . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function decryptPaymentData(string $encryptedText, string $encryptionKey): ?string
    {
        try {
            $encryptedTextBytes = base64_decode($encryptedText);
            $passBytes = $encryptionKey;
            $keyBytes = str_repeat("\0", 16);
            $len = min(strlen($passBytes), 16);
            for ($i = 0; $i < $len; $i++) {
                $keyBytes[$i] = $passBytes[$i];
            }

            $decrypted = openssl_decrypt(
                $encryptedTextBytes,
                'AES-128-CBC',
                $keyBytes,
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                $keyBytes
            );

            if ($decrypted === false) {
                $decrypted = openssl_decrypt(
                    $encryptedTextBytes,
                    'AES-128-CBC',
                    $keyBytes,
                    OPENSSL_RAW_DATA,
                    $keyBytes
                );
            }

            if ($decrypted === false) {
                return null;
            }

            // PKCS7 unpad if needed
            $pad = ord(substr($decrypted, -1));
            if ($pad > 0 && $pad <= 16) {
                $decrypted = substr($decrypted, 0, -$pad);
            }

            return trim($decrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractPaymentIdFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $parts = parse_url($url);
        if (empty($parts['query'])) {
            return null;
        }

        parse_str($parts['query'], $query);
        return $query['paymentId'] ?? $query['PaymentId'] ?? null;
    }

    /**
     * MyFatoorah CountryCode max length is 4 (e.g. "+965").
     * Return null to skip Mobile when phone cannot be safely parsed.
     */
    private function formatMobile(?string $phone): ?array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '' || strlen($digits) < 8) {
            return null;
        }

        // Prefer common GCC codes (digits only, then prefix "+") so total length stays <= 4
        $knownCodes = ['971', '965', '966', '974', '973', '968', '962', '20'];
        foreach ($knownCodes as $code) {
            if (str_starts_with($digits, $code)) {
                $number = substr($digits, strlen($code));
                if (strlen($number) >= 6 && strlen($number) <= 11) {
                    return [
                        'CountryCode' => '+' . $code,
                        'Number' => $number,
                    ];
                }
            }
        }

        // Fallback: last 9 digits as local number, first up to 3 as country (keeps "+xxx" <= 4)
        if (strlen($digits) >= 10) {
            $number = substr($digits, -9);
            $code = substr($digits, 0, min(3, strlen($digits) - 9));
            if ($code !== '' && strlen('+' . $code) <= 4) {
                return [
                    'CountryCode' => '+' . $code,
                    'Number' => $number,
                ];
            }
        }

        return null;
    }
}
