<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class UserController extends Controller
{

    public function __construct()
    {
        $this->middleware(['role:admin', 'auth:sanctum']);
    }
    public function index(Request $request)
    {

        $keyword = $request->keyword;
        $per_page = $request->per_page ?? 10;

        $query = User::where(function ($query) use ($keyword) {
            if (isset($keyword) && !empty($keyword)) {
                $query->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%')
                    ->orWhere('phone_number', 'like', '%' . $keyword . '%')
                    ->orWhere('address', 'like', '%' . $keyword . '%');
            }
        });
        $user = $query->paginate($per_page);
        return response()->json([
            'status' => true,
            'message' => 'Success get users',
            'data' => $user
        ], 200);
    }

    public function store(Request $request)
    {
        $validation = Validator::make(
            $request->all(),
            [
                'email' => 'required|email|unique:users,email',
                'name' => 'required|string|max:255',
                'date_of_birth' => 'required|date',
                'role' => 'required',
                'password' => 'required|string|min:6',
                're_password' => 'required_with:password|same:password'
            ],
        );
        if (!$validation->fails()) {
            try {
                $payload = $request->except('re_password');
                $uploadedFileUrl = Cloudinary::upload($request->file('avatar')->getRealPath())->getSecurePath();
                $payload['avatar'] = $uploadedFileUrl;
                $user = User::create($payload);
                return response()->json([
                    'status' => true,
                    'message' => 'Create user success',
                    'data' => $user,
                ], 200);
            } catch (\Throwable $th) {
                return response()->json([
                    'status' => false,
                    'message' => 'Create user false',
                    'data' => [],
                    'error' => $th->getMessage(),
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Validate errors',
                'error' => $validation->errors()
            ]);
        }
    }

    public function show(string $id)
    {
        $user = User::find($id);
        $orders = $user->orders()->get();
        if ($orders) {
            foreach ($orders as $orderItem) {
                $orderItem->items = $orderItem->items()->get();
            }
        }
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User dosen\'t exist',
                'data' => []
            ], 404);
        }
        return response()->json([
            'status' => true,
            'message' => 'User get success',
            'data' => [
                'user' => $user,
                'userOrder' => $orders
            ]
        ], 200);
    }
    public function update(Request $request, string $id)
    {

        $user = User::find($id);
        if (!empty($user)) {
            $validation = Validator::make(
                $request->all(),
                [
                    'email' => 'required|email|unique:users,email,' . $user->id,
                    'name' => 'required|string|max:255',
                ],
            );
            if ($validation->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'error' => $validation->errors(),
                ], 422);
            }
            $user->update($request->all());
            return response()->json([
                'status' => true,
                'message' => 'Update user success',
                'data' => $user,
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'User doesn\'t exit',
                'data' => []
            ], 404);
        }
    }

    public function destroy(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'messate' => 'User doesn\'t exits',
                'data' => []
            ], 404);
        }
        $user->delete();
        return response()->json([
            'status' => true,
            'message' => 'User deleted successfuly'
        ], 200);
    }
}