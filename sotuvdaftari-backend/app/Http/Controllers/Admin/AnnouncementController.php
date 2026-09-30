<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Bildirishnomalar: hammaga, tarif bo'yicha yoki bitta foydalanuvchiga */
class AnnouncementController extends Controller
{
    public function index()
    {
        return view('admin.announcements', [
            'announcements' => Announcement::with('user')->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', Rule::in([Announcement::AUDIENCE_ALL, Announcement::AUDIENCE_PLAN, Announcement::AUDIENCE_USER])],
            'plan' => ['required_if:audience,plan', 'nullable', Rule::in(['free', 'standard', 'pro'])],
            'phone' => ['required_if:audience,user', 'nullable', 'string', 'max:20'],
        ]);

        $userId = null;

        if ($data['audience'] === Announcement::AUDIENCE_USER) {
            $phone = '+'.ltrim(preg_replace('/[^\d+]/', '', $data['phone']), '+');
            $user = User::where('phone', $phone)->first();

            if ($user === null) {
                return back()->withInput()->withErrors(['phone' => 'Bunday telefonli foydalanuvchi topilmadi.']);
            }

            $userId = $user->id;
        }

        Announcement::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'plan' => $data['audience'] === Announcement::AUDIENCE_PLAN ? $data['plan'] : null,
            'user_id' => $userId,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Bildirishnoma yuborildi.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Announcement::findOrFail($id)->delete();

        return back()->with('status', 'Bildirishnoma o\'chirildi.');
    }
}
