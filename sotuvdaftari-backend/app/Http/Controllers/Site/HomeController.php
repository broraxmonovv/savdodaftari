<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\Billing\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Ochiq sayt: xizmat haqida ma'lumot va ariza (lead) formasi */
class HomeController extends Controller
{
    public function index(Request $request, BillingService $billing)
    {
        $this->applyLocale($request);

        return view('site.landing', [
            'plans' => collect($billing->plans())->keyBy('id'),
            'support' => config('savdodaftar.support'),
            'site' => config('savdodaftar.site'),
            'percent' => (float) config('savdodaftar.referral.percent'),
            'lang' => app()->getLocale(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $this->applyLocale($request);

        // Honeypot: botlar yashirin maydonni to'ldiradi — jim "muvaffaqiyat" qaytaramiz
        if (filled($request->input('website'))) {
            return $this->done();
        }

        $request->merge(['phone' => $this->normalizePhone((string) $request->input('phone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'regex:/^\+998\d{9}$/'],
            'business_type' => ['nullable', Rule::in(Lead::BUSINESS_TYPES)],
            'message' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
        ], [
            'phone.regex' => __('site.form.invalid_phone'),
            'phone.required' => __('site.form.invalid_phone'),
            'name.required' => __('site.form.name'),
            'name.min' => __('site.form.name'),
            'consent.accepted' => __('site.form.consent'),
        ]);

        // Bir raqamdan 10 daqiqada takroriy ariza saqlanmaydi
        $duplicate = Lead::where('phone', $data['phone'])->where('created_at', '>=', now()->subMinutes(10))->exists();

        if (! $duplicate) {
            Lead::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'business_type' => $data['business_type'] ?? null,
                'message' => $data['message'] ?? null,
                'source' => 'site',
                'status' => Lead::STATUS_NEW,
                'locale' => app()->getLocale(),
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
        }

        return $this->done();
    }

    private function done(): RedirectResponse
    {
        return redirect(route('site.home', ['lang' => app()->getLocale()]).'#contact')
            ->with('lead_sent', true);
    }

    private function applyLocale(Request $request): void
    {
        $lang = $request->query('lang', $request->input('lang', $request->session()->get('site_lang', 'uz')));
        $lang = in_array($lang, config('savdodaftar.locales', ['uz', 'ru']), true) ? $lang : 'uz';

        $request->session()->put('site_lang', $lang);
        app()->setLocale($lang);
    }

    /** `90 123 45 67`, `998901234567`, `+998 (90) 123-45-67` -> `+998901234567` */
    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }

        return $digits === '' ? '' : '+'.$digits;
    }
}
