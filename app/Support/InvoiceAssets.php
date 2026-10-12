<?php

namespace App\Support;

/**
 * Images for PDF templates.
 *
 * A PDF template that points at asset('invoice-logo.jpg') makes DomPDF download
 * the logo from this app's own URL while the app is still busy rendering the
 * PDF. On a one-request-at-a-time server (php artisan serve) that request can
 * never be answered, so the order/invoice request hangs and the whole app stops
 * responding; elsewhere it is a pointless network round trip. Embedding the
 * file avoids the request entirely.
 */
class InvoiceAssets
{
    private static ?string $logo = null;

    /** The invoice logo as a data: URI, usable in both the PDF and the HTML preview. */
    public static function logoDataUri(): string
    {
        if (self::$logo === null) {
            $path = public_path('invoice-logo.jpg');

            self::$logo = is_file($path)
                ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($path))
                // 1x1 transparent GIF, so a missing file leaves a blank spot instead of failing the PDF
                : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        }

        return self::$logo;
    }
}
