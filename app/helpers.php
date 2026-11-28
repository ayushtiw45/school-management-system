<?php

if (! function_exists('inr')) {
    /** Indian-style currency format: ₹1,23,456.00 */
    function inr($amount, bool $symbol = true): string
    {
        $amount = round((float) $amount, 2);
        $neg = $amount < 0;
        [$int, $dec] = explode('.', number_format(abs($amount), 2, '.', ''));
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        if ($rest !== '' && $rest !== false) {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest.','.$last3;
        }

        return ($neg ? '-' : '').($symbol ? '₹' : '').$int.'.'.$dec;
    }
}

if (! function_exists('school')) {
    function school(string $key): ?string
    {
        return \App\Models\Setting::get('school_'.$key);
    }
}

if (! function_exists('school_image')) {
    /** The school's logo or signature as a data: URI (works in PDFs and prints), or null. */
    function school_image(string $which): ?string
    {
        $path = \App\Models\Setting::get($which === 'logo' ? 'logo_path' : 'signature_path');
        if (! $path || ! \Illuminate\Support\Facades\Storage::exists($path)) {
            return null;
        }
        $mime = \Illuminate\Support\Facades\Storage::mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(\Illuminate\Support\Facades\Storage::get($path));
    }
}

if (! function_exists('upi_link')) {
    /** upi://pay link for the school's UPI ID (parents tap it on the phone), or null. */
    function upi_link(?float $amount = null, string $note = ''): ?string
    {
        $vpa = trim((string) \App\Models\Setting::get('upi_id'));
        if ($vpa === '') {
            return null;
        }
        $q = ['pa' => $vpa, 'pn' => (string) school('name'), 'cu' => 'INR'];
        if ($amount) {
            $q['am'] = number_format($amount, 2, '.', '');
        }
        if ($note !== '') {
            $q['tn'] = mb_substr($note, 0, 60);
        }

        return 'upi://pay?'.http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }
}

if (! function_exists('amount_in_words')) {
    /** 12500.50 → "Rupees Twelve Thousand Five Hundred and Fifty Paise Only" (Indian system: lakh, crore). */
    function amount_in_words($amount): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen',
            'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two = fn (int $n) => $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)].' '.$ones[$n % 10]);
        $three = fn (int $n) => trim(($n >= 100 ? $ones[intdiv($n, 100)].' Hundred ' : '').$two($n % 100));

        $amount = round(abs((float) $amount), 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);
        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$div, $label]) {
            if ($rupees >= $div) {
                $parts[] = ($div === 10000000 ? amount_in_words_int(intdiv($rupees, $div), $three, $two) : $two(intdiv($rupees, $div))).' '.$label;
                $rupees %= $div;
            }
        }
        if ($rupees) {
            $parts[] = $three($rupees);
        }
        $words = 'Rupees '.(implode(' ', $parts) ?: 'Zero');
        if ($paise) {
            $words .= ' and '.$two($paise).' Paise';
        }

        return $words.' Only';
    }

    function amount_in_words_int(int $n, callable $three, callable $two): string
    {
        // crores can exceed 99: use hundreds/thousands for them
        return $n >= 1000 ? trim($two(intdiv($n, 1000)).' Thousand '.$three($n % 1000)) : $three($n);
    }
}
