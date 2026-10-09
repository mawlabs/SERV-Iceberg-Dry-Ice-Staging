@extends('website.layouts.main')

@section('content')
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
            <div class="col-12 col-md-6">
                <div class="payment-result-container text-center p-5">
                    @if($success)
                        <div class="success-icon mb-4">
                            <i class="la la-check-circle"></i>
                        </div>
                        <h2 class="text-success mb-3">{{ $heading ?? 'Payment Successful!' }}</h2>
                        <p class="lead mb-4">{{ $message }}</p>

                        <div class="payment-details">
                            <div class="detail-row">
                                <span class="detail-label">Invoice Number:</span>
                                <span class="detail-value">#{{ $invoice_number }}</span>
                            </div>
                            @if(isset($amount))
                                <div class="detail-row">
                                    <span class="detail-label">Amount Paid:</span>
                                    <span class="detail-value">${{ $amount }} CAD</span>
                                </div>
                            @endif
                            @if(isset($transaction_id))
                                <div class="detail-row">
                                    <span class="detail-label">Transaction ID:</span>
                                    <span class="detail-value">{{ $transaction_id }}</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="error-icon mb-4">
                            <i class="la la-times-circle"></i>
                        </div>
                        <h2 class="text-danger mb-3">{{ $heading ?? 'Payment Failed' }}</h2>
                        <p class="lead mb-4">{{ $message }}</p>

                        @if(isset($invoice_number))
                            <div class="payment-details">
                                <div class="detail-row">
                                    <span class="detail-label">Invoice Number:</span>
                                    <span class="detail-value">#{{ $invoice_number }}</span>
                                </div>
                                @if(!empty($transaction_id))
                                    <div class="detail-row">
                                        <span class="detail-label">Transaction ID:</span>
                                        <span class="detail-value">{{ $transaction_id }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif

                    <div class="mt-5">
                        {{-- Guests (public checkout) have no dashboard, so send them to the home page. --}}
                        @if(Auth::guard('customer')->check())
                            <a href="{{ route('customer.dashboard') }}" class="btn btn-primary btn-lg">
                                <i class="la la-home me-2"></i>Go to Dashboard
                            </a>
                        @else
                            <a href="{{ route('home') }}" class="btn btn-primary btn-lg">
                                <i class="la la-home me-2"></i>Home
                            </a>
                        @endif
                        @if(!$success && !empty($retry_url))
                            <a href="{{ $retry_url }}" class="btn btn-secondary btn-lg ms-2">
                                <i class="la la-redo me-2"></i>Try Again
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Glassmorphism container */
        .payment-result-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .success-icon i, .error-icon i {
            font-size: 100px;
            animation: scaleIn 0.5s ease-out;
        }

        .success-icon i {
            color: #28a745;
        }

        .error-icon i {
            color: #dc3545;
        }

        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .payment-details {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: rgb(11, 117, 175);
        }

        .detail-value {
            font-weight: 700;
            color: #002480;
        }

        .btn-lg {
            border-radius: 25px;
            padding: 12px 40px;
            font-weight: 600;
        }

        footer {
            display: none;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if($success)
            Swal.fire({
                icon: 'success',
                title: 'Payment Successful!',
                html: '<strong>Invoice #{{ $invoice_number }}</strong><br>{{ $message }}',
                confirmButtonColor: '#28a745',
                confirmButtonText: 'Continue',
                allowOutsideClick: false
            });
            @else
            Swal.fire({
                icon: 'error',
                title: 'Payment Failed',
                html: '<strong>{{ $message }}</strong>',
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Close',
                allowOutsideClick: false
            });
            @endif
        });
    </script>
@endsection

@push('after_scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush
