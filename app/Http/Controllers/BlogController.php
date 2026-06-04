<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BlogController extends Controller
{
    // ── Public endpoints ──────────────────────────────────────────

    /**
     * GET /api/blog/posts
     * List published posts (paginated, newest first).
     */
    public function index(Request $request)
    {
        $perPage = min((int)($request->query('per_page', 10)), 50);
        $category = $request->query('category');

        $query = BlogPost::published()
            ->with('author:id,name')
            ->orderByDesc('published_at');

        if ($category) {
            $query->where('category', $category);
        }

        $posts = $query->paginate($perPage)->through(fn($p) => $this->publicShape($p));

        return response()->json($posts);
    }

    /**
     * GET /api/blog/posts/{slug}
     * Single published post.
     */
    public function show(string $slug)
    {
        $post = BlogPost::published()
            ->where('slug', $slug)
            ->with('author:id,name')
            ->firstOrFail();

        return response()->json($this->publicShape($post, full: true));
    }

    /**
     * GET /api/blog/latest?limit=5
     * Latest N published posts (for dashboard widget).
     */
    public function latest(Request $request)
    {
        $limit = min((int)($request->query('limit', 5)), 20);

        $posts = BlogPost::published()
            ->select('id', 'title', 'slug', 'excerpt', 'category', 'published_at', 'content')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'title'       => $p->title,
                'slug'        => $p->slug,
                'category'    => $p->category,
                'preview'     => $p->preview,
                'published_at'=> $p->published_at?->toISOString(),
            ]);

        return response()->json(['posts' => $posts]);
    }

    // ── Admin endpoints ───────────────────────────────────────────

    /**
     * GET /api/admin/blog/posts
     */
    public function adminIndex(Request $request)
    {
        $perPage = min((int)($request->query('per_page', 20)), 100);
        $status  = $request->query('status'); // published | draft | all

        $query = BlogPost::with('author:id,name')
            ->orderByDesc('created_at');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $posts = $query->paginate($perPage)->through(fn($p) => $this->adminShape($p));

        return response()->json($posts);
    }

    /**
     * POST /api/admin/blog/posts
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'excerpt'  => 'nullable|string|max:500',
            'content'  => 'required|string',
            'category' => 'required|in:general,update,feature,tip,security,announcement',
            'status'   => 'required|in:draft,published',
            'thumbnail'=> 'nullable|image|max:5120', // 5MB max
        ]);

        $data['author_id'] = $request->user()->id;
        $data['slug']      = BlogPost::generateUniqueSlug($data['title']);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('blog_thumbnails', 'public');
        }

        if ($data['status'] === 'published') {
            $data['published_at'] = now();
        }

        $post = BlogPost::create($data);

        return response()->json([
            'success' => true,
            'data'    => $this->adminShape($post->load('author:id,name')),
        ], 201);
    }

    /**
     * PUT /api/admin/blog/posts/{id}
     */
    public function update(Request $request, int $id)
    {
        $post = BlogPost::findOrFail($id);

        $data = $request->validate([
            'title'    => 'sometimes|required|string|max:255',
            'excerpt'  => 'nullable|string|max:500',
            'content'  => 'sometimes|required|string',
            'category' => 'sometimes|required|in:general,update,feature,tip,security,announcement',
            'status'   => 'sometimes|required|in:draft,published',
            'thumbnail'=> 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('blog_thumbnails', 'public');
        }

        // Auto-set published_at when first publishing
        if (isset($data['status']) && $data['status'] === 'published' && !$post->published_at) {
            $data['published_at'] = now();
        }

        // Regenerate slug if title changed
        if (isset($data['title']) && $data['title'] !== $post->title) {
            $data['slug'] = BlogPost::generateUniqueSlug($data['title']);
        }

        $post->update($data);

        return response()->json([
            'success' => true,
            'data'    => $this->adminShape($post->fresh()->load('author:id,name')),
        ]);
    }

    /**
     * DELETE /api/admin/blog/posts/{id}
     */
    public function destroy(int $id)
    {
        $post = BlogPost::findOrFail($id);
        $post->delete();

        return response()->json(['success' => true]);
    }

    /**
     * PATCH /api/admin/blog/posts/{id}/toggle
     * Toggle published ↔ draft.
     */
    public function toggleStatus(int $id)
    {
        $post = BlogPost::findOrFail($id);

        if ($post->status === 'published') {
            $post->update(['status' => 'draft']);
        } else {
            $post->update([
                'status'       => 'published',
                'published_at' => $post->published_at ?? now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'status'  => $post->fresh()->status,
        ]);
    }

    // ── Private Shapers ───────────────────────────────────────────

    private function publicShape(BlogPost $p, bool $full = false): array
    {
        $base = [
            'id'           => $p->id,
            'title'        => $p->title,
            'slug'         => $p->slug,
            'excerpt'      => $p->excerpt ?? $p->preview,
            'category'     => $p->category,
            'thumbnail'    => $p->thumbnail ? url('storage/' . $p->thumbnail) : null,
            'published_at' => $p->published_at?->toISOString(),
            'author'       => 'WordCast Admin', // hardcoded as requested
        ];

        if ($full) {
            $base['content'] = $p->content;
        }

        return $base;
    }

    private function adminShape(BlogPost $p): array
    {
        return [
            'id'           => $p->id,
            'title'        => $p->title,
            'slug'         => $p->slug,
            'thumbnail'    => $p->thumbnail ? url('storage/' . $p->thumbnail) : null,
            'excerpt'      => $p->excerpt,
            'content'      => $p->content,
            'category'     => $p->category,
            'status'       => $p->status,
            'published_at' => $p->published_at?->toISOString(),
            'created_at'   => $p->created_at?->toISOString(),
            'author'       => $p->author?->name ?? '—',
        ];
    }
}
