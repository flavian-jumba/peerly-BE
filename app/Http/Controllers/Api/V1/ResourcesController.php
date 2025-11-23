<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Resources;
use Illuminate\Http\Request;

class ResourcesController extends Controller
{
    // GET /api/resources
    public function index()
    {
        return Resources::paginate(10);
    }

    // GET /api/resources/{id}
    public function show($id)
    {
        return Resources::findOrFail($id);
    }

    // POST /api/resources
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'url' => 'nullable|url',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'tags' => 'nullable|string',
        ]);
        return Resources::create($validated);
    }

    // PUT/PATCH /api/resources/{id}
    public function update(Request $request, $id)
    {
        $resource = Resources::findOrFail($id);
        $resource->update($request->all());
        return $resource;
    }

    // DELETE /api/resources/{id}
    public function destroy($id)
    {
        $resource = Resources::findOrFail($id);
        $resource->delete();
        return response()->json(['message' => 'Resource deleted.']);
    }
}
