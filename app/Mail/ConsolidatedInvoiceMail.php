<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email for a consolidated (account-holder) invoice.
 * The PDF is rendered by the caller and passed in as raw bytes so this
 * Mailable stays simple and has no DB/relationship dependencies at send time.
 */
class ConsolidatedInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $customer;
    public $pdfData;

    public function __construct($invoice, $customer, $pdfData = null)
    {
        $this->invoice  = $invoice;
        $this->customer = $customer;
        $this->pdfData  = $pdfData;
    }

    public function build()
    {
        $mail = $this->from(env('MAIL_FROM_ADDRESS'), 'IcebergDryIce Invoice')
            ->subject('Your Iceberg Dry Ice Invoice #' . ($this->invoice->invoice_number ?? ''))
            ->view('emails.consolidated-invoice-email')
            ->with([
                'invoice'  => $this->invoice,
                'customer' => $this->customer,
            ]);

        if (!empty($this->pdfData)) {
            $mail->attachData(
                $this->pdfData,
                $this->invoice->pdfFileName(),
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }
}
