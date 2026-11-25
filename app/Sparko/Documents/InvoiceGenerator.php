<?php

declare(strict_types=1);

namespace App\Sparko\Documents;

use Cable8mm\LaravelPortone\Models\Payment;
use Dompdf\Dompdf;

class InvoiceGenerator
{
    public function pdf(Payment $payment): string
    {
        $html = view('sparko.invoice', ['payment' => $payment])->render();

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
