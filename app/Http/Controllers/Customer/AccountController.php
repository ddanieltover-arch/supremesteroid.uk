<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Models\Address;
use App\Support\IsoCountry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AccountController extends Controller
{
    public function profile(Request $request): Response
    {
        return Inertia::render('Account/Profile', [
            'profile' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'phone' => $request->user()->phone,
                'email_verified' => $request->user()->hasVerifiedEmail(),
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Profile updated.');
    }

    public function addresses(Request $request): Response
    {
        $addresses = Address::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->get();

        return Inertia::render('Account/Addresses', [
            'addresses' => $addresses->map(fn (Address $address) => [
                'id' => $address->id,
                'type' => $address->type,
                'full_name' => $address->full_name,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'state_county' => $address->state_county,
                'postal_code' => $address->postal_code,
                'country_code' => $address->country_code,
                'phone' => $address->phone,
                'is_default' => $address->is_default,
            ]),
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $validated = $this->validatedAddress($request);

        if ($validated['is_default'] ?? false) {
            Address::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        Address::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Address saved.');
    }

    public function updateAddress(Request $request, string $id): RedirectResponse
    {
        $address = Address::query()->where('user_id', $request->user()->id)->findOrFail($id);
        $validated = $this->validatedAddress($request);

        if ($validated['is_default'] ?? false) {
            Address::query()->where('user_id', $request->user()->id)->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return back()->with('success', 'Address updated.');
    }

    public function destroyAddress(Request $request, string $id): RedirectResponse
    {
        $address = Address::query()->where('user_id', $request->user()->id)->findOrFail($id);
        $address->delete();

        return back()->with('success', 'Address removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedAddress(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['shipping', 'billing'])],
            'full_name' => ['required', 'string', 'max:150'],
            'address_line_1' => ['required', 'string', 'max:150'],
            'address_line_2' => ['nullable', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'state_county' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        try {
            $validated['country_code'] = IsoCountry::normalize($validated['country_code']);
        } catch (InvalidArgumentException) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'country_code' => 'Country must be a two-letter ISO code such as GB.',
            ]);
        }

        return $validated;
    }
}
