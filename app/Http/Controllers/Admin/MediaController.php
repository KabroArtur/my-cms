<?php

namespace App\Http\Controllers\Admin;

use App\Core\Media\Models\MediaFile;
use App\Core\Media\Models\MediaFolder;
use App\Core\Media\Services\MediaUploadService;
use App\Core\Media\Services\MediaVariantManager;
use App\Core\Security\Services\SecurityAuditLogger;
use App\Core\Support\Services\CmsCacheService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Media\StoreMediaFolderRequest;
use App\Http\Requests\Admin\Media\UploadMediaBatchRequest;
use App\Http\Requests\Admin\Media\UpdateMediaFileRequest;
use App\Http\Requests\Admin\Media\UpdateMediaFolderRequest;
use App\Http\Requests\Admin\Media\UploadMediaRequest;
use App\Http\Resources\Admin\MediaFileResource;
use App\Http\Resources\Admin\MediaFolderResource;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    public function __construct(
        protected MediaVariantManager $variants,
        protected MediaUploadService $uploads,
        protected CmsCacheService $cache,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MediaFile::class);

        $folderId = $request->integer('folder_id');
        $currentFolder = $folderId > 0 ? MediaFolder::query()->findOrFail($folderId) : null;

        $folders = MediaFolder::query()
            ->withCount(['children', 'files'])
            ->where('parent_id', $currentFolder?->id)
            ->orderBy('name')
            ->get();

        $folderOptions = MediaFolder::query()
            ->orderBy('path')
            ->get();

        $files = MediaFile::query()
            ->with('folder')
            ->where('folder_id', $currentFolder?->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'current_folder' => $currentFolder ? MediaFolderResource::make($currentFolder) : null,
                'breadcrumbs' => MediaFolderResource::collection($this->breadcrumbs($currentFolder)),
                'folders' => MediaFolderResource::collection($folders),
                'folder_options' => MediaFolderResource::collection($folderOptions),
                'files' => MediaFileResource::collection($files),
            ],
        ]);
    }

    public function storeFolder(StoreMediaFolderRequest $request, SecurityAuditLogger $audit): JsonResponse
    {
        $parent = $request->integer('parent_id') > 0
            ? MediaFolder::query()->findOrFail($request->integer('parent_id'))
            : null;

        $slug = $this->resolveUniqueFolderSlug($parent, (string) $request->string('name'));
        $path = $parent === null ? $slug : $parent->path.'/'.$slug;

        $folder = MediaFolder::query()->create([
            'parent_id' => $parent?->id,
            'created_by' => $request->user()?->id,
            'name' => (string) $request->string('name'),
            'slug' => $slug,
            'path' => $path,
        ]);

        Storage::disk('public')->makeDirectory('media/'.$folder->path);

        $audit->log('media.folders.created', $request->user(), [
            'folder_id' => $folder->id,
            'folder_path' => $folder->path,
        ]);

        $this->cache->invalidateAll(reason: 'media-folder-created');

        return MediaFolderResource::make($folder->loadCount(['children', 'files']))
            ->response()
            ->setStatusCode(201);
    }

    public function destroyFolder(MediaFolder $folder, SecurityAuditLogger $audit): Response
    {
        $this->authorize('delete', $folder);

        abort_if($folder->children()->exists(), 422, 'Сначала удалите вложенные папки.');
        abort_if($folder->files()->exists(), 422, 'Сначала удалите файлы из папки.');

        Storage::disk('public')->deleteDirectory('media/'.$folder->path);

        $audit->log('media.folders.deleted', request()->user(), [
            'folder_id' => $folder->id,
            'folder_path' => $folder->path,
        ]);

        $folder->delete();
        $this->cache->invalidateAll(reason: 'media-folder-deleted');

        return response()->noContent();
    }

    public function updateFolder(UpdateMediaFolderRequest $request, MediaFolder $folder, SecurityAuditLogger $audit): MediaFolderResource
    {
        $targetParent = $request->integer('parent_id') > 0
            ? MediaFolder::query()->findOrFail($request->integer('parent_id'))
            : null;

        abort_if($targetParent?->id === $folder->id, 422, 'Нельзя переместить папку в саму себя.');
        abort_if($targetParent !== null && $this->isDescendantOf($targetParent, $folder), 422, 'Нельзя переместить папку во вложенную папку.');

        $previousPath = $folder->path;
        $slug = $this->resolveUniqueFolderSlug($targetParent, (string) $request->string('name'), $folder->id);
        $nextPath = $targetParent === null ? $slug : $targetParent->path.'/'.$slug;

        if ($previousPath !== $nextPath) {
            Storage::disk('public')->makeDirectory('media/'.dirname($nextPath));
            Storage::disk('public')->move('media/'.$previousPath, 'media/'.$nextPath);
        }

        $folder->forceFill([
            'name' => (string) $request->string('name'),
            'parent_id' => $targetParent?->id,
            'slug' => $slug,
            'path' => $nextPath,
        ])->save();

        $this->syncNestedFolderPaths($folder, $previousPath);
        $this->syncFilePaths($folder, $previousPath);

        $audit->log('media.folders.updated', $request->user(), [
            'folder_id' => $folder->id,
            'from_path' => $previousPath,
            'to_path' => $nextPath,
            'parent_id' => $targetParent?->id,
        ]);

        $this->cache->invalidateAll(reason: 'media-folder-updated');

        return MediaFolderResource::make($folder->fresh()->loadCount(['children', 'files']));
    }

    public function storeFile(UploadMediaRequest $request, SecurityAuditLogger $audit): JsonResponse
    {
        $folder = $request->integer('folder_id') > 0
            ? MediaFolder::query()->findOrFail($request->integer('folder_id'))
            : null;

        $mediaFile = $this->uploads->storeUploadedFile(
            file: $request->file('file'),
            folder: $folder,
            userId: $request->user()?->id,
            desiredName: $request->input('name'),
        );

        $audit->log('media.files.uploaded', $request->user(), [
            'file_id' => $mediaFile->id,
            'file_path' => $mediaFile->path,
            'folder_id' => $folder?->id,
        ]);

        $this->cache->invalidateAll(reason: 'media-file-uploaded');

        return MediaFileResource::make($mediaFile)
            ->response()
            ->setStatusCode(201);
    }

    public function storeFiles(UploadMediaBatchRequest $request, SecurityAuditLogger $audit): JsonResponse
    {
        $results = [];
        $uploadedAny = false;

        foreach (array_keys($request->input('items', [])) as $index) {
            $payload = [
                'file' => $request->file("items.$index.file"),
                'name' => $request->input("items.$index.name"),
                'folder_id' => $request->input("items.$index.folder_id"),
            ];

            $validator = Validator::make($payload, [
                'folder_id' => ['nullable', 'integer', Rule::exists('media_folders', 'id')],
                'name' => ['nullable', 'string', 'max:255'],
                'file' => [
                    'required',
                    'file',
                    'max:10240',
                    'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/avif,image/bmp',
                ],
            ]);

            if ($validator->fails()) {
                $results[] = [
                    'index' => $index,
                    'status' => 'error',
                    'message' => 'Файл не прошел валидацию.',
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $folderId = $validator->validated()['folder_id'] ?? null;
            $folder = $folderId ? MediaFolder::query()->find($folderId) : null;

            try {
                $mediaFile = $this->uploads->storeUploadedFile(
                    file: $payload['file'],
                    folder: $folder,
                    userId: $request->user()?->id,
                    desiredName: $payload['name'],
                );

                $audit->log('media.files.uploaded', $request->user(), [
                    'file_id' => $mediaFile->id,
                    'file_path' => $mediaFile->path,
                    'folder_id' => $folder?->id,
                ]);

                $results[] = [
                    'index' => $index,
                    'status' => 'success',
                    'data' => MediaFileResource::make($mediaFile->load('folder'))->resolve(),
                ];
                $uploadedAny = true;
            } catch (ValidationException $exception) {
                $results[] = [
                    'index' => $index,
                    'status' => 'error',
                    'message' => 'Файл не прошел валидацию.',
                    'errors' => $exception->errors(),
                ];
            } catch (\Throwable $exception) {
                report($exception);

                $results[] = [
                    'index' => $index,
                    'status' => 'error',
                    'message' => 'Не удалось загрузить файл.',
                ];
            }
        }

        if ($uploadedAny) {
            $this->cache->invalidateAll(reason: 'media-file-batch-uploaded');
        }

        return response()->json([
            'data' => [
                'results' => $results,
            ],
        ]);
    }

    public function updateFile(UpdateMediaFileRequest $request, MediaFile $mediaFile, SecurityAuditLogger $audit): MediaFileResource
    {
        $validated = $request->validated();

        if (array_key_exists('original_name', $validated) && $validated['original_name'] !== null) {
            $mediaFile = $this->uploads->renameMediaFile($mediaFile->load('folder'), (string) $validated['original_name']);
        }

        $mediaFile->forceFill([
            'title' => $this->nullableString($validated['title'] ?? null),
            'alt_text' => $this->nullableString($validated['alt_text'] ?? null),
            'caption' => $this->nullableString($validated['caption'] ?? null),
        ])->save();

        $audit->log('media.files.updated', $request->user(), [
            'file_id' => $mediaFile->id,
            'file_path' => $mediaFile->path,
        ]);

        $this->cache->invalidateAll(reason: 'media-file-updated');

        return MediaFileResource::make($mediaFile->fresh()->load('folder'));
    }

    public function destroyFile(MediaFile $mediaFile, SecurityAuditLogger $audit): Response
    {
        $this->authorize('delete', $mediaFile);

        $this->variants->deleteVariants($mediaFile);
        Storage::disk($mediaFile->disk)->delete($mediaFile->path);

        $audit->log('media.files.deleted', request()->user(), [
            'file_id' => $mediaFile->id,
            'file_path' => $mediaFile->path,
        ]);

        $mediaFile->delete();
        $this->cache->invalidateAll(reason: 'media-file-deleted');

        return response()->noContent();
    }

    public function moveFile(Request $request, MediaFile $mediaFile, SecurityAuditLogger $audit): MediaFileResource
    {
        $this->authorize('update', $mediaFile);

        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer', Rule::exists('media_folders', 'id')],
        ]);

        $targetFolder = isset($validated['folder_id'])
            ? MediaFolder::query()->findOrFail((int) $validated['folder_id'])
            : null;

        if ((int) ($mediaFile->folder_id ?? 0) === (int) ($targetFolder?->id ?? 0)) {
            return MediaFileResource::make($mediaFile->load('folder'));
        }

        $targetDirectory = 'media'.($targetFolder ? '/'.$targetFolder->path : '');
        $targetPath = $targetDirectory.'/'.$mediaFile->filename;

        Storage::disk($mediaFile->disk)->makeDirectory($targetDirectory);
        Storage::disk($mediaFile->disk)->move($mediaFile->path, $targetPath);
        $variants = $this->variants->moveVariants($mediaFile, $targetDirectory);

        $mediaFile->forceFill([
            'folder_id' => $targetFolder?->id,
            'directory' => $targetDirectory,
            'path' => $targetPath,
            'variants' => $variants,
        ])->save();

        $audit->log('media.files.moved', $request->user(), [
            'file_id' => $mediaFile->id,
            'from_folder_id' => $mediaFile->getOriginal('folder_id'),
            'to_folder_id' => $targetFolder?->id,
            'file_path' => $mediaFile->path,
        ]);

        $this->cache->invalidateAll(reason: 'media-file-moved');

        return MediaFileResource::make($mediaFile->fresh()->load('folder'));
    }

    protected function breadcrumbs(?MediaFolder $folder): array
    {
        $breadcrumbs = [];

        while ($folder !== null) {
            array_unshift($breadcrumbs, $folder->loadCount(['children', 'files']));
            $folder = $folder->parent;
        }

        return $breadcrumbs;
    }

    protected function resolveUniqueFolderSlug(?MediaFolder $parent, string $name, ?int $ignoreFolderId = null): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'folder';
        $slug = $baseSlug;
        $index = 2;

        $query = MediaFolder::query()
            ->where('parent_id', $parent?->id)
            ->where('slug', $slug);

        if ($ignoreFolderId !== null) {
            $query->whereKeyNot($ignoreFolderId);
        }

        while ($query->exists()) {
            $slug = $baseSlug.'-'.$index;
            $index++;

            $query = MediaFolder::query()
                ->where('parent_id', $parent?->id)
                ->where('slug', $slug);

            if ($ignoreFolderId !== null) {
                $query->whereKeyNot($ignoreFolderId);
            }
        }

        return $slug;
    }

    protected function isDescendantOf(MediaFolder $candidate, MediaFolder $folder): bool
    {
        $current = $candidate;

        while ($current !== null) {
            if ($current->id === $folder->id) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    protected function syncNestedFolderPaths(MediaFolder $folder, string $previousPath): void
    {
        foreach ($folder->children()->get() as $child) {
            $child->forceFill([
                'path' => $folder->path.substr($child->path, strlen($previousPath)),
            ])->save();

            $this->syncNestedFolderPaths($child, $previousPath);
            $this->syncFilePaths($child, $previousPath);
        }
    }

    protected function syncFilePaths(MediaFolder $folder, string $previousPath): void
    {
        foreach ($folder->files()->get() as $file) {
            $nextDirectory = 'media/'.$folder->path;
            $nextPath = 'media/'.$folder->path.'/'.$file->filename;

            $file->forceFill([
                'directory' => $nextDirectory,
                'path' => $nextPath,
                'variants' => $this->variants->rebaseVariantPaths($file->variants ?? [], $file->directory, $nextDirectory),
            ])->save();
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}