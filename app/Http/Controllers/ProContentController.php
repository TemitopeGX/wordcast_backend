<?php

namespace App\Http\Controllers;

use App\Models\ProContentAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProContentController extends Controller
{
    /**
     * Public catalog endpoint — called by the desktop app.
     * Returns active assets; supports ?category= and ?search= filters.
     */
    public function catalog(Request $request)
    {
        $query = ProContentAsset::where('is_active', true)
            ->orderBy('created_at', 'desc');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $assets = $query->select([
            'id', 'title', 'category', 'type',
            'cdn_url', 'thumbnail_url', 'file_size',
            'tags', 'created_at',
        ])->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => $assets->items(),
            'meta'    => [
                'total'        => $assets->total(),
                'current_page' => $assets->currentPage(),
                'last_page'    => $assets->lastPage(),
                'per_page'     => $assets->perPage(),
            ],
            'categories' => [
                'backgrounds',
                'motion_loops',
                'overlays',
                'gradients',
                'videos',
            ],
        ]);
    }

    /**
     * Filter by category — convenience route so the app can pass the
     * category as a path segment instead of a query param.
     */
    public function byCategory(Request $request, string $category)
    {
        $request->merge(['category' => $category]);
        return $this->catalog($request);
    }

    /**
     * Upload new asset — admin only.
     * Stores original file in R2; generates a thumbnail for images.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|max:102400', // 100 MB max
            'title'    => 'required|string|max:255',
            'category' => 'required|in:backgrounds,motion_loops,overlays,gradients,videos',
            'tags'     => 'nullable|array',
        ]);

        $file     = $request->file('file');
        $mimeType = $file->getMimeType();
        $type     = str_starts_with($mimeType, 'video') ? 'video' : 'image';
        $category = $request->category;
        $ext      = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $ext;
        $r2Key    = "media/{$category}/{$filename}";

        // Upload original to R2 securely using streams
        $success = Storage::disk('r2_media')->putFileAs(
            "media/{$category}",
            $file,
            $filename,
            'public'
        );

        if (!$success) {
            return response()->json(['success' => false, 'message' => 'Failed to upload file to Cloudflare R2'], 500);
        }

        $cdnBase     = rtrim(env('MEDIA_R2_CDN_URL', ''), '/');
        $cdnUrl      = "{$cdnBase}/{$r2Key}";
        $thumbnailUrl = $cdnUrl; // default: same as original (used for video)

        // Generate thumbnail for images (requires intervention/image package)
        if ($type === 'image' && class_exists(\Intervention\Image\Facades\Image::class)) {
            try {
                $thumbFilename = 'thumb_' . $filename;
                $thumbKey      = "media/thumbnails/{$thumbFilename}";

                $thumbnail = \Intervention\Image\Facades\Image::make($file->getRealPath())
                    ->fit(400, 225)
                    ->encode($ext, 80);

                Storage::disk('r2_media')->put($thumbKey, $thumbnail->__toString(), 'public');
                $thumbnailUrl = "{$cdnBase}/{$thumbKey}";
            } catch (\Throwable $e) {
                // Thumbnail generation failed — fall back to full image URL
                \Illuminate\Support\Facades\Log::warning('ProContent thumbnail generation failed: ' . $e->getMessage());
            }
        }

        $asset = ProContentAsset::create([
            'title'         => $request->title,
            'category'      => $category,
            'type'          => $type,
            'r2_key'        => $r2Key,
            'cdn_url'       => $cdnUrl,
            'thumbnail_url' => $thumbnailUrl,
            'filename'      => $filename,
            'file_size'     => $file->getSize(),
            'tags'          => $request->tags ?? [],
            'uploaded_by'   => $request->user()->email,
            'is_active'     => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Asset uploaded successfully',
            'data'    => $asset,
        ]);
    }

    /**
     * Update asset details (title, category, tags).
     */
    public function update(Request $request, int $id)
    {
        $asset = ProContentAsset::findOrFail($id);

        $request->validate([
            'title'    => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|in:backgrounds,motion_loops,overlays,gradients,videos',
            'tags'     => 'nullable|array',
        ]);

        if ($request->has('title')) {
            $asset->title = $request->title;
        }
        if ($request->has('category')) {
            $asset->category = $request->category;
        }
        if ($request->has('tags')) {
            $asset->tags = $request->tags ?? [];
        }

        $asset->save();

        return response()->json([
            'success' => true,
            'message' => 'Asset updated successfully',
            'data'    => $asset,
        ]);
    }

    /**
     * Toggle asset active / inactive state.
     */
    public function toggleActive(int $id)
    {
        $asset = ProContentAsset::findOrFail($id);
        $asset->update(['is_active' => !$asset->is_active]);

        return response()->json([
            'success'   => true,
            'is_active' => $asset->is_active,
        ]);
    }

    /**
     * Delete asset from R2 and database.
     */
    public function destroy(int $id)
    {
        $asset = ProContentAsset::findOrFail($id);

        // Delete original from R2
        Storage::disk('r2_media')->delete($asset->r2_key);

        // Delete thumbnail from R2 if it differs from the original
        if ($asset->thumbnail_url !== $asset->cdn_url) {
            $thumbKey = str_replace(rtrim(env('MEDIA_R2_CDN_URL', ''), '/') . '/', '', $asset->thumbnail_url);
            Storage::disk('r2_media')->delete($thumbKey);
        }

        $asset->delete();

        return response()->json(['success' => true, 'message' => 'Asset deleted']);
    }

    /**
     * Admin list — includes inactive assets.
     */
    public function adminList()
    {
        $assets = ProContentAsset::orderBy('created_at', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => $assets,
        ]);
    }
}
