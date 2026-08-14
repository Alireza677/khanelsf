<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('client.profile', [
            'accountUser' => $request->user('client'),
        ]);
    }

    public function legacy(): RedirectResponse
    {
        return redirect()->route('account.profile.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user('client');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
        ]);

        $user->update($validated);

        return redirect()->route('account.profile.edit')->with('status', 'Profile updated successfully.');
    }
}
