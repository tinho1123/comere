<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class SelectCompanyController extends Controller
{
    public function show(Request $request)
    {
        $user = auth()->user();

        if ($request->filled('uuid')) {
            $company = Company::where('uuid', $request->uuid)->firstOrFail();
            abort_unless($user->canAccessTenant($company), 403);

            return redirect()->to(route('filament.admin.pages.dashboard', ['tenant' => $company->uuid]));
        }

        $companies = $user->isMaster()
            ? Company::orderBy('name')->get(['uuid', 'name', 'logo_path'])
            : $user->companies()->orderBy('name')->get(['companies.uuid', 'companies.name', 'companies.logo_path']);

        if ($companies->count() === 1) {
            return redirect()->to(route('filament.admin.pages.dashboard', ['tenant' => $companies->first()->uuid]));
        }

        return view('admin.select-company', compact('companies'));
    }

    public function store(Request $request)
    {
        $request->validate(['company_uuid' => ['required', 'string', 'exists:companies,uuid']]);

        $company = Company::where('uuid', $request->company_uuid)->firstOrFail();

        abort_unless(auth()->user()->canAccessTenant($company), 403);

        return redirect()->to(route('filament.admin.pages.dashboard', ['tenant' => $company->uuid]));
    }
}
