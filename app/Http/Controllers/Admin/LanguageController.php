<?php

namespace App\Http\Controllers\Admin;

use App\Core\Languages\Models\Language;
use App\Core\Languages\Services\LanguageManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Languages\StoreLanguageRequest;
use App\Http\Requests\Admin\Languages\UpdateLanguageRequest;
use App\Http\Resources\Admin\LanguageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LanguageController extends Controller
{
    public function __construct(
        protected LanguageManager $languages,
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        return LanguageResource::collection($this->languages->all());
    }

    public function store(StoreLanguageRequest $request): JsonResponse
    {
        return LanguageResource::make($this->languages->create($request->validated()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateLanguageRequest $request, Language $language): LanguageResource
    {
        return LanguageResource::make($this->languages->update($language, $request->validated()));
    }

    public function destroy(Language $language): JsonResponse
    {
        $this->languages->delete($language);

        return response()->json([
            'message' => __('languages.messages.deleted'),
        ]);
    }
}