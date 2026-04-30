<?php

namespace App\Http\Controllers\Admin;

use App\Auth\PermissionConfig;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = User::with(['branch.company', 'company']);

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Scope: company-admin sees only their company's users
        if ($user->isCompanyAdmin()) {
            $branchIds = Branch::where('company_id', $user->company_id)->pluck('id');
            $query->where(function ($q) use ($branchIds, $user) {
                $q->whereIn('branch_id', $branchIds)
                  ->orWhere('company_id', $user->company_id);
            });
        }

        $users = $query->orderBy('name')->paginate(15);

        // Get available branches for the create/edit forms
        $branches = $user->isSuperAdmin()
            ? Branch::with('company')->active()->orderBy('name')->get()
            : Branch::with('company')->where('company_id', $user->company_id)->active()->orderBy('name')->get();

        $companies = $user->isSuperAdmin()
            ? Company::active()->orderBy('name')->get()
            : Company::where('id', $user->company_id)->get();

        $roles = PermissionConfig::roleOptions();

        // Restrict assignable roles based on current user's role
        if ($user->isCompanyAdmin()) {
            unset($roles['super-admin']);
        } elseif ($user->isBranchAdmin()) {
            $roles = array_intersect_key($roles, array_flip(['branch-operator', 'viewer']));
        }

        return view('admin.users.index', compact('users', 'branches', 'companies', 'roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users',
            'password'   => 'required|string|min:6',
            'role'       => ['required', 'string', Rule::in(PermissionConfig::roleKeys())],
            'branch_id'  => 'nullable|exists:branches,id',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        // Auto-resolve company_id from branch if not provided
        if (!empty($data['branch_id']) && empty($data['company_id'])) {
            $branch = Branch::find($data['branch_id']);
            $data['company_id'] = $branch?->company_id;
        }

        User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => Hash::make($data['password']),
            'role'        => $data['role'],
            'auth_source' => 'local',
            'branch_id'   => $data['branch_id'] ?? null,
            'company_id'  => $data['company_id'] ?? null,
        ]);

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id && $request->role !== $currentUser->role) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => ['required', 'email', Rule::unique('users')->ignore($user)],
            'role'       => ['required', 'string', Rule::in(PermissionConfig::roleKeys())],
            'branch_id'  => 'nullable|exists:branches,id',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        // Auto-resolve company_id from branch if not provided
        if (!empty($data['branch_id']) && empty($data['company_id'])) {
            $branch = Branch::find($data['branch_id']);
            $data['company_id'] = $branch?->company_id;
        }

        $user->update([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'role'       => $data['role'],
            'branch_id'  => $data['branch_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
        ]);

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return redirect()->route('admin.users')->with('success', 'User password reset successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['delete' => 'You cannot delete yourself.']);
        }

        $user->delete();
        return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
    }
}
