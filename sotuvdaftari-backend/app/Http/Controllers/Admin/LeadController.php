<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Saytdan kelgan arizalar: ko'rish, holat/izoh, o'chirish, CSV eksport */
class LeadController extends Controller
{
    public function index(Request $request)
    {
        [$q, $status] = [trim((string) $request->query('q', '')), $request->query('status')];

        $leads = $this->query($q, $status)->paginate(25)->withQueryString();

        // Ilovada allaqachon ro'yxatdan o'tgan raqamlar
        $registered = User::whereIn('phone', $leads->pluck('phone'))->pluck('id', 'phone');

        $counts = Lead::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('admin.leads', compact('leads', 'q', 'status', 'registered', 'counts'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Lead::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:255'],
        ]);

        $lead = Lead::findOrFail($id);
        $lead->update($data + [
            'processed_at' => $data['status'] === Lead::STATUS_NEW ? null : ($lead->processed_at ?? now()),
        ]);

        return back()->with('status', 'Ariza yangilandi.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Lead::findOrFail($id)->delete();

        return back()->with('status', 'Ariza o\'chirildi.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query(trim((string) $request->query('q', '')), $request->query('status'));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel uchun UTF-8 BOM
            fputcsv($out, ['ID', 'Sana', 'Ism', 'Telefon', 'Savdo turi', 'Izoh', 'Holat', 'Admin izohi', 'Til']);

            $query->reorder('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $l) {
                    fputcsv($out, [
                        $l->id, $l->created_at->format('Y-m-d H:i'), $l->name, $l->phone, $l->business_type,
                        $l->message, $l->status, $l->admin_note, $l->locale,
                    ]);
                }
            });

            fclose($out);
        }, 'arizalar-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(string $q, ?string $status)
    {
        return Lead::query()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%");
            }))
            ->when(in_array($status, Lead::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest('id');
    }
}
