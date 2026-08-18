<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanSignatureController extends Controller
{
    public function __invoke(Loan $loan): StreamedResponse
    {
        abort_unless(
            auth()->user()?->can('loan.view'),
            403
        );

        abort_unless(
            filled($loan->signature_path)
            && Storage::disk('local')->exists($loan->signature_path),
            404
        );

        return Storage::disk('local')->response(
            $loan->signature_path,
            "firma-{$loan->code}.png",
            [
                'Content-Type' => 'image/png',
                'Content-Disposition' =>
                    "inline; filename=\"firma-{$loan->code}.png\"",
            ],
        );
    }
}