<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchTemplateDefault;
use App\Models\Company;
use App\Models\PrintProfile;
use App\Models\PrintTemplate;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Branch::with(['company', 'agents', 'users', 'profiles'])
            ->withCount(['agents', 'users', 'profiles', 'jobs']);

        // Scope based on role
        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        if ($request->filled('company_id') && $user->isSuperAdmin()) {
            $query->where('company_id', $request->company_id);
        }

        $branches = $query->orderBy('name')->get();

        $companies = $user->isSuperAdmin()
            ? Company::active()->orderBy('name')->get()
            : Company::where('id', $user->company_id)->get();

        return view('admin.branches.index', compact('branches', 'companies'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:30|unique:branches,code',
            'address'    => 'nullable|string|max:500',
        ]);

        // Non-super-admin can only create branches in their own company
        if (!$user->isSuperAdmin() && $data['company_id'] != $user->company_id) {
            abort(403, 'You can only create branches in your own company.');
        }

        Branch::create($data);

        return redirect()->route('admin.branches')->with('success', 'Branch created successfully.');
    }

    public function update(Request $request, Branch $branch)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $branch->company_id != $user->company_id) {
            abort(403);
        }

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => 'required|string|max:30|unique:branches,code,' . $branch->id,
            'address'   => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active');
        $branch->update($data);

        return redirect()->route('admin.branches')->with('success', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $branch->company_id != $user->company_id) {
            abort(403);
        }

        if ($branch->agents()->count() > 0 || $branch->users()->count() > 0) {
            return back()->withErrors(['delete' => 'Cannot delete branch with assigned agents or users. Reassign them first.']);
        }

        $branch->delete();
        return redirect()->route('admin.branches')->with('success', 'Branch deleted successfully.');
    }

    /**
     * Manage template defaults for a branch.
     */
    public function templateDefaults(Branch $branch)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $branch->company_id != $user->company_id) {
            abort(403);
        }

        $templates = PrintTemplate::orderBy('name')->get();
        $profiles  = PrintProfile::where('branch_id', $branch->id)->with('agent')->get();
        $defaults  = $branch->templateDefaults()->with(['template', 'profile.agent'])->get()->keyBy('print_template_id');

        return view('admin.branches.template-defaults', compact('branch', 'templates', 'profiles', 'defaults'));
    }

    /**
     * Save template defaults for a branch.
     */
    public function saveTemplateDefaults(Request $request, Branch $branch)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $branch->company_id != $user->company_id) {
            abort(403);
        }

        $data = $request->validate([
            'defaults'              => 'nullable|array',
            'defaults.*.template_id' => 'required|exists:print_templates,id',
            'defaults.*.profile_id'  => 'nullable|exists:print_profiles,id',
        ]);

        // Clear existing defaults for this branch
        $branch->templateDefaults()->delete();

        // Save new defaults
        foreach ($data['defaults'] ?? [] as $item) {
            if (!empty($item['profile_id'])) {
                BranchTemplateDefault::create([
                    'branch_id'          => $branch->id,
                    'print_template_id'  => $item['template_id'],
                    'print_profile_id'   => $item['profile_id'],
                ]);
            }
        }

        return redirect()->route('admin.branches.template-defaults', $branch)
            ->with('success', 'Template defaults saved successfully.');
    }
}
