<?php

namespace App\Http\Controllers;

use App\Models\Specialization;
use Illuminate\Http\Request;

class SpecializationController extends Controller
{
    public function index()
    {
        $specializations = Specialization::all();
        if (!$specializations) {
            return response()->json(['message' => 'No specializations found'], 404);
        }
        return response()->json($specializations, 200);
    }
}
