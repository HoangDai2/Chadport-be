<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $role = Role::all();
        return response()->json([
            'roles' => $role
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->isMethod('POST')) {
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
            ]);
            $params = $request->all();

            $newrole = Role::create($params);
            return 
            response()->json([
                'message' => 'Successfully created role',
                'role' => $newrole
            ], 201);
        }
    }


    public function update(Request $request, string $id)
    {
        
        if ($request->isMethod('PUT')) {
            
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $params = $request->all();
            $role = Role::findOrFail($id);
            $role->update($params);
            return response()->json([
                'message' => 'Successfully updated role',
                'role' => $role
            ]);
        }
    }

}
