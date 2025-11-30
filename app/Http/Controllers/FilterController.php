<?php

namespace App\Http\Controllers;

use App\Models\Filters;

class FilterController extends Controller
{
    public function __construct(Private Filters $filter)
    {
    }
    public function index()
    {
        try {
            $filters = $this->filter->all(['id', 'type', 'value']);
            if (!$filters) {
                return response()->json(['message' => 'No filters found'], 404);
            }

            return response()->json($filters);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve filters'], 500);
        }
    }
}
