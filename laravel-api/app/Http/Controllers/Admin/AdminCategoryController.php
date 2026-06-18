<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class AdminCategoryController extends Controller
{
    /**
     * List all categories (for admin forms).
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name')->get();
        return response()->json($categories);
    }
}
