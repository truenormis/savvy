<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $tags = Tag::withCount('transactions')
            ->orderBy('name')
            ->get();

        return TagResource::collection($tags);
    }

    public function store(StoreTagRequest $request): TagResource
    {
        $tag = Tag::create($request->validated());

        return new TagResource($tag);
    }

    public function show(Tag $tag): TagResource
    {
        return new TagResource($tag->loadCount('transactions'));
    }

    public function update(UpdateTagRequest $request, Tag $tag): TagResource
    {
        $tag->update($request->validated());

        return new TagResource($tag);
    }

    public function destroy(Tag $tag): \Illuminate\Http\JsonResponse
    {
        $tag->delete();

        return response()->json(null, 204);
    }
}
