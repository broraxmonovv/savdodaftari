<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Tarif narxi, muddati va faolligini yangilash */
class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans', ['plans' => Plan::orderBy('sort')->get()]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'max_customers' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'max_products' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $plan = Plan::where('key', $key)->firstOrFail();
        $plan->update([
            'price' => $data['price'],
            // Bo'sh qoldirilsa — cheksiz (NULL)
            'max_customers' => $data['max_customers'] ?? null,
            'max_products' => $data['max_products'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', "{$plan->key} tarifi yangilandi.");
    }
}
