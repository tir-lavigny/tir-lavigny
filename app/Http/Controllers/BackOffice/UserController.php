<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Mail\UserCreated;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:create user', only: ['create', 'store']),
            new Middleware('permission:edit user', only: ['edit', 'update']),
            new Middleware('permission:delete user', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', null);

        $query = User::query();
        $query->with('roles');

        if ($search) {
            $search = strtolower($search);
            $query->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        $data = $query->paginate(10)->withQueryString();

        return Inertia::render('backOffice/Users/Index', [
            'paginatedUsers' => $data,
            'filters' => [
                'search' => $search,
            ],
            'protectedEmail' => env('APP_USER_EMAIL'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::all(['id', 'name']);

        return Inertia::render('backOffice/Users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users',
            'role' => 'required|exists:roles,name',
        ]);

        // Generate a random password
        $password = Str::random(12);

        $user = new User;
        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->password = Hash::make($password);
        $user->email_verified_at = now(); // Auto-verify admin-created users
        $user->save();

        // Assign role
        $user->assignRole($request->input('role'));

        // Send email with credentials
        try {
            Mail::to($user->email)->send(new UserCreated($user, $password));
        } catch (Exception $e) {
            // Log the error but don't fail the user creation
            logger()->error('Failed to send user creation email: '.$e->getMessage());
        }

        return redirect()->route('bo.users.show', $user->id)->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('roles')->findOrFail($id);

        return Inertia::render('backOffice/Users/Show', [
            'user' => $user,
            'protectedEmail' => env('APP_USER_EMAIL'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $user = User::with('roles')->findOrFail($id);
        $roles = Role::all(['id', 'name']);

        return Inertia::render('backOffice/Users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => 'required|exists:roles,name',
        ]);

        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->save();

        // Update role
        $user->syncRoles([$request->input('role')]);

        return redirect()->route('bo.users.show', $user->id)->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);

        // Prevent deletion of the protected user
        $protectedEmail = env('APP_USER_EMAIL');
        if ($user->email === $protectedEmail) {
            return redirect()->route('bo.users.index')->with('error', 'Cannot delete the protected user account.');
        }

        $user->delete();

        return redirect()->route('bo.users.index')->with('success', 'User deleted successfully.');
    }
}
