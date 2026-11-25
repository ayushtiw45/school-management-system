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
