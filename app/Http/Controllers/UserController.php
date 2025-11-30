<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Schedule;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index($id)
    {
        $user = User::with('specializations')->find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $schedules = Schedule::where('user_id', $id)->get();

        return response()->json([
            'user' => $user,
            'schedules' => $schedules,
        ], 200);
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();
        if(isset($validated['schedules'])) {
            $schedules = Schedule::where('user_id', $validated['user_id'])->exists();
            if ($schedules) {
                Schedule::where('user_id', $validated['user_id'])->delete();
            }
            foreach ($validated['schedules'] as $scheduleData) {
                $scheduleData['user_id'] = $validated['user_id'];
                Schedule::create($scheduleData);
            }
        }
        if(isset($validated['specializations'])) {
            $user = User::find($validated['user_id']);
            $user->specializations()->sync(
                array_map(function ($name) {
                    return Specialization::where('name', $name)->first()->id;
                }, $validated['specializations'])
            );
        }

        return response()->json(['message' => 'User informations stored successfully'], 201);
    }

    public function update(UpdateUserRequest $request)
    {
        $validated = $request->validated();
        if(isset($validated['schedules'])) {
            Schedule::where('user_id', $validated['user_id'])->delete();
            foreach ($validated['schedules'] as $scheduleData) {
                $scheduleData['user_id'] = $validated['user_id'];
                Schedule::create($scheduleData);
            }
        }
        if(isset($validated['specializations'])) {
            $user = User::find($validated['user_id']);
            $user->specializations()->sync(
                array_map(function ($name) {
                    return Specialization::where('name', $name)->first()->id;
                }, $validated['specializations'])
            );
        }

        return response()->json(['message' => 'User informations updated successfully'], 200);
    }
}
