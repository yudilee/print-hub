<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::withCount('branches')
            ->with(['branches' => function ($q) {
                $q->withCount(['agents', 'users', 'profiles']);
            }])
            ->orderBy('name')
            ->get();

        return view('admin.companies.index', compact('companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:20|unique:companies,code',
            'short_name' => 'nullable|string|max:100',
        ]);

        Company::create($data);

        return redirect()->route('admin.companies')->with('success', 'Company created successfully.');
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:20|unique:companies,code,' . $company->id,
            'short_name' => 'nullable|string|max:100',
            'is_active'  => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active');
        $company->update($data);

        return redirect()->route('admin.companies')->with('success', 'Company updated successfully.');
    }

    public function destroy(Company $company)
    {
        if ($company->branches()->count() > 0) {
            return back()->withErrors(['delete' => 'Cannot delete company with existing branches. Remove all branches first.']);
        }

        $company->delete();
        return redirect()->route('admin.companies')->with('success', 'Company deleted successfully.');
    }
}
