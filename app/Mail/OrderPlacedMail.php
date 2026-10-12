<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;


class OrderPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct($order,$orderType = 'normal')
    {
        $this->order = $order;
        $this->type = $orderType;
    }

    public function build()
    {
        $isRecurring = $this->order instanceof \App\Models\RecurringOrder;
        $isPaid = $this->order->invoice->payment_status == 'paid'? 1 : 0;

        $view = $isRecurring
            ? 'emails.recurring-order-placed'
            : 'emails.order-placed';
        $subject = $isPaid
            ? 'Payment Received – Your Invoice is Attached'
            : 'Your Order Has Been Placed';

        $mail = $this->from(env('MAIL_FROM_ADDRESS'), 'IcebergDryIce Invoice')
            ->subject($subject)
            ->view($view)
            ->with([
                'order' => $this->order,
                'invoice_number' =>  $this->order->invoice->invoice_number ?? null,
            ]);

//        if ($isPaid) {
            $pdf = Pdf::loadView('emails.invoice-pdf', [
                'order' => $this->order,
                'invoice_number' => $this->order->invoice->invoice_number ?? null,
            ])->setOption('isHtml5ParserEnabled', true)->setOption('isRemoteEnabled', false)->setOption('chroot', public_path());

            $mail->attachData($pdf->output(), 'invoice.pdf', [
                'mime' => 'application/pdf',
            ]);
//        }

        return $mail;
    }
}

