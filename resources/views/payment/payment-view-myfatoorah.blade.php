<?php
    $payerInformation = json_decode($data['payer_information'], true);
    $additionalData = json_decode($data['additional_data'] ?? '', true);
?>

@extends('payment.layouts.master')

@push('script')
    <title>{{ translate('MyFatoorah_Payment') }} - {{ $additionalData['business_name'] ?? '' }}</title>
    <link rel="shortcut icon" href="{{ $additionalData['business_logo'] ?? '' }}" type="image/x-icon">
    <script src="{{ $sessionJsUrl }}"></script>
    <style>
        body {
            background: #f5f7fb;
        }
        .myfatoorah-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 12px;
        }
        .myfatoorah-card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .myfatoorah-card__header {
            padding: 20px 24px 12px;
            border-bottom: 1px solid #eef2f7;
        }
        .myfatoorah-card__header h1 {
            font-size: 1.15rem;
            margin: 0 0 4px;
            font-weight: 700;
        }
        .myfatoorah-card__header p {
            margin: 0;
            color: #64748b;
            font-size: .9rem;
        }
        .myfatoorah-card__body {
            padding: 16px 20px 24px;
            min-height: 320px;
        }
        .myfatoorah-card__footer {
            padding: 0 20px 20px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }
        .myfatoorah-amount {
            font-weight: 700;
            color: #0f172a;
        }
        #myfatoorah-status {
            display: none;
            margin-top: 12px;
            font-size: .9rem;
        }
    </style>
@endpush

@section('content')
    <div class="myfatoorah-wrap">
        <div class="myfatoorah-card">
            <div class="myfatoorah-card__header">
                <h1>{{ translate('Complete_Payment') }}</h1>
                <p>{{ translate('Secure_payment_powered_by_MyFatoorah') }}</p>
            </div>
            <div class="myfatoorah-card__body">
                <div id="embedded-sessions"></div>
                <div id="myfatoorah-status" class="alert alert-info mb-0"></div>
            </div>
            <div class="myfatoorah-card__footer">
                <div class="myfatoorah-amount">
                    {{ number_format((float) $data->payment_amount, 2) }} {{ $data->currency_code }}
                </div>
                <a href="{{ route('myfatoorah.callback', ['status' => 'fail', 'payment_id' => $data->id]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    {{ translate('cancel') }}
                </a>
            </div>
        </div>
    </div>

    <script>
        "use strict";

        function setStatus(message, type) {
            const el = document.getElementById('myfatoorah-status');
            el.style.display = 'block';
            el.className = 'alert alert-' + (type || 'info') + ' mb-0';
            el.textContent = message;
        }

        function extractPaymentId(redirectionUrl) {
            try {
                const url = new URL(redirectionUrl);
                return url.searchParams.get('paymentId') || url.searchParams.get('PaymentId');
            } catch (e) {
                return null;
            }
        }

        function handlePaymentCallback(response) {
            console.log('MyFatoorah callback', response);

            if (!response || response.isSuccess === false) {
                setStatus('{{ translate("Payment_failed_please_try_again") }}', 'danger');
                window.location.href = "{!! route('myfatoorah.callback', ['status' => 'fail', 'payment_id' => $data->id]) !!}";
                return;
            }

            setStatus('{{ translate("Verifying_payment") }}...', 'info');

            const paymentId = extractPaymentId(response.redirectionUrl || '');

            fetch("{{ route('myfatoorah.make_payment') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({
                    payment_id: "{{ $data->id }}",
                    paymentData: response.paymentData || null,
                    paymentId: paymentId,
                    sessionId: response.sessionId || "{{ $sessionId }}",
                    paymentCompleted: !!response.paymentCompleted,
                    redirectionUrl: response.redirectionUrl || null,
                    paymentType: response.paymentType || null
                })
            })
                .then(function (res) { return res.json(); })
                .then(function (result) {
                    if (result.status === 'success') {
                        window.location.href = result.redirect_url || "{!! route('myfatoorah.callback', ['status' => 'success', 'payment_id' => $data->id]) !!}";
                    } else {
                        window.location.href = result.redirect_url || "{!! route('myfatoorah.callback', ['status' => 'fail', 'payment_id' => $data->id]) !!}";
                    }
                })
                .catch(function () {
                    setStatus('{{ translate("Payment_verification_failed") }}', 'danger');
                    window.location.href = "{!! route('myfatoorah.callback', ['status' => 'fail', 'payment_id' => $data->id]) !!}";
                });
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (typeof myfatoorah === 'undefined' || typeof myfatoorah.init !== 'function') {
                setStatus('{{ translate("Unable_to_load_payment_form") }}', 'danger');
                return;
            }

            myfatoorah.init({
                sessionId: "{{ $sessionId }}",
                callback: handlePaymentCallback,
                containerId: "embedded-sessions",
                shouldHandlePaymentUrl: true
            });
        });
    </script>
@endsection
