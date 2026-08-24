<?php

namespace App\Http\Controllers;

use App\Domain\Galleries\GalleryAudience;
use App\Domain\Galleries\MediaExposureService;
use App\Enums\WebsitePageStatus;
use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumPhoto;
use App\Models\Lodge;
use App\Models\MediaAsset;
use App\Models\WebsitePageVersion;
use App\Services\Audit;
use App\Services\GalleryPublisher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GalleryController extends Controller
{
    public function index(Request $request, Lodge $lodge, GalleryAudience $audience)
    {
        $albums = $lodge->galleryAlbums()
            ->whereHas('published', fn ($query) => $audience->visible($query, $lodge, $request->user()))
            ->with(['published.photos.mediaAsset'])
            ->get();

        return Inertia::render('galleries/Index', [
            'lodge' => $lodge,
            'albums' => $albums,
            'navigation' => $this->publicNavigation($lodge),
        ]);
    }

    public function show(Request $request, Lodge $lodge, GalleryAlbum $album, GalleryAudience $audience)
    {
        abort_unless($album->lodge_id === $lodge->id, 404);
        $version = $album->published()->with('photos.mediaAsset')->first();
        abort_unless($version && $audience->canView($request->user(), $lodge, $version), 404);

        return Inertia::render('galleries/Show', [
            'lodge' => $lodge,
            'album' => $album,
            'version' => $version,
            'navigation' => $this->publicNavigation($lodge),
            'galleryIndexUrl' => $this->galleryPageUrl($lodge, $request->string('from')->toString()),
        ]);
    }

    public function photo(Request $request, Lodge $lodge, GalleryAlbum $album, GalleryAlbumPhoto $photo, GalleryAudience $audience, MediaExposureService $media)
    {
        abort_unless($album->lodge_id === $lodge->id, 404);
        $version = $album->published()->first();
        abort_unless($version && $photo->gallery_album_version_id === $version->id && $audience->canView($request->user(), $lodge, $version), 404);
        if ($version->visibility->value === 'public') {
            return redirect()->away($photo->mediaAsset->url);
        }

        return $media->privateDerivativeResponse($photo->mediaAsset, true);
    }

    public function manage(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'galleries.manage');

        return Inertia::render('galleries/Manage', ['lodge' => $lodge, 'albums' => $lodge->galleryAlbums()->with(['draft.photos.mediaAsset', 'published.photos.mediaAsset'])->get(), 'media' => $lodge->mediaAssets()->orderByDesc('id')->get(), 'canPublish' => $request->user()->hasLodgePermission($lodge, 'galleries.publish')]);
    }

    public function store(Request $request, Lodge $lodge, GalleryPublisher $publisher)
    {
        $this->allowLodge($lodge, 'galleries.manage');
        $album = $publisher->create($lodge, $request->user(), $this->validateAlbum($request, $lodge));

        return redirect()->route('lodges.galleries.manage', $lodge);
    }

    public function edit(Request $request, Lodge $lodge, GalleryAlbum $album, GalleryPublisher $publisher)
    {
        $this->allowAlbum($lodge, $album, 'galleries.manage');

        return Inertia::render('galleries/Edit', ['lodge' => $lodge, 'album' => $album, 'draft' => $publisher->draftFor($album, $request->user())->load('photos.mediaAsset'), 'media' => $lodge->mediaAssets()->orderByDesc('id')->get(), 'canPublish' => $request->user()->hasLodgePermission($lodge, 'galleries.publish')]);
    }

    public function update(Request $request, Lodge $lodge, GalleryAlbum $album, GalleryPublisher $publisher)
    {
        $this->allowAlbum($lodge, $album, 'galleries.manage');
        $publisher->update($album, $lodge, $request->user(), $this->validateAlbum($request, $lodge, $album));

        return back();
    }

    public function addPhoto(Request $request, Lodge $lodge, GalleryAlbum $album, GalleryPublisher $publisher)
    {
        $this->allowAlbum($lodge, $album, 'galleries.manage');
        $asset = MediaAsset::query()->whereKey($request->validate(['media_asset_id' => 'required|integer'])['media_asset_id'])->where('lodge_id', $lodge->id)->firstOrFail();
        $draft = $publisher->draftFor($album, $request->user());
        GalleryAlbumPhoto::firstOrCreate(['gallery_album_version_id' => $draft->id, 'media_asset_id' => $asset->id], ['lodge_id' => $lodge->id, 'caption' => '', 'sort_order' => ((int) $draft->photos()->max('sort_order')) + 1]);

        return back();
    }

    public function updatePhoto(Request $request, Lodge $lodge, GalleryAlbum $album, GalleryAlbumPhoto $photo, GalleryPublisher $publisher)
    {
        $this->allowAlbum($lodge, $album, 'galleries.manage');
        $draft = $publisher->draftFor($album, $request->user());
        abort_unless($photo->gallery_album_version_id === $draft->id, 404);
        $photo->update($request->validate(['caption' => 'nullable|string|max:2000', 'sort_order' => 'nullable|integer|min:0']));

        return back();
    }

    public function removePhoto(Request $request, Lodge $lodge, GalleryAlbum $album, GalleryAlbumPhoto $photo, GalleryPublisher $publisher)
    {
        $this->allowAlbum($lodge, $album, 'galleries.manage');
        abort_unless($photo->gallery_album_version_id === $publisher->draftFor($album, $request->user())->id, 404);
        $photo->delete();

        return back();
    }

    public function publish(Request $request, Lodge $lodge, GalleryAlbum $album, GalleryPublisher $publisher)
    {
        $this->allowAlbum($lodge, $album, 'galleries.publish');
        $publisher->publish($album, $request->user());

        return redirect()->route('lodges.galleries.manage', $lodge);
    }

    public function unpublish(Request $request, Lodge $lodge, GalleryAlbum $album, GalleryPublisher $publisher)
    {
        $this->allowAlbum($lodge, $album, 'galleries.publish');
        $publisher->unpublish($album, $request->user());

        return back();
    }

    public function destroy(Lodge $lodge, GalleryAlbum $album)
    {
        $this->allowAlbum($lodge, $album, 'galleries.manage');
        abort_if($album->published()->exists(), 422);
        $album->delete();
        Audit::record('gallery.album_deleted', $album, $lodge);

        return back();
    }

    public function restore(Lodge $lodge, int $albumId)
    {
        $this->allowLodge($lodge, 'galleries.manage');
        $album = GalleryAlbum::onlyTrashed()->where('lodge_id', $lodge->id)->findOrFail($albumId);
        $album->restore();
        Audit::record('gallery.album_restored', $album, $lodge);

        return back();
    }

    private function validateAlbum(Request $request, Lodge $lodge, ?GalleryAlbum $album = null): array
    {
        return $request->validate(['title' => 'required|string|max:255', 'slug' => ['required', 'alpha_dash', 'max:160', Rule::unique('gallery_albums')->where(fn ($q) => $q->where('lodge_id', $lodge->id)->whereNull('deleted_at'))->ignore($album?->id)], 'description' => 'nullable|string|max:10000', 'visibility' => ['required', Rule::in(['public', 'masons', 'lodge'])], 'cover_photo_id' => 'nullable|integer']);
    }

    private function allowAlbum(Lodge $lodge, GalleryAlbum $album, string $permission): void
    {
        abort_unless($album->lodge_id === $lodge->id, 404);
        $this->allowLodge($lodge, $permission);
    }

    private function publicNavigation(Lodge $lodge): array
    {
        $versions = WebsitePageVersion::query()
            ->where('lodge_id', $lodge->id)
            ->where('status', WebsitePageStatus::Published)
            ->where('show_in_navigation', true)
            ->with('page')
            ->whereHas('page', fn ($query) => $query->whereNull('deleted_at'))
            ->orderBy('navigation_order')
            ->orderBy('title')
            ->get();

        return $this->navigationTree($versions);
    }

    private function galleryPageUrl(Lodge $lodge, string $pageSlug): string
    {
        $page = WebsitePageVersion::query()
            ->where('lodge_id', $lodge->id)
            ->where('status', WebsitePageStatus::Published)
            ->when(
                $pageSlug === 'home',
                fn ($query) => $query->where('is_home', true),
                fn ($query) => $query->where('slug', $pageSlug),
            )
            ->whereHas('page', fn ($query) => $query->whereNull('deleted_at'))
            ->whereHas('sections', fn ($query) => $query->where('type', 'gallery_placeholder'))
            ->first();

        if (! $page) {
            return "/l/{$lodge->slug}/galleries";
        }

        return $page->is_home
            ? "/l/{$lodge->slug}"
            : "/l/{$lodge->slug}/{$page->slug}";
    }

    private function navigationTree($versions, ?int $parentId = null): array
    {
        return $versions->filter(fn ($version) => $version->navigation_parent_page_id === $parentId)
            ->map(fn ($version) => [
                'title' => $version->title,
                'slug' => $version->slug,
                'is_home' => $version->is_home,
                'children' => $this->navigationTree($versions, $version->website_page_id),
            ])->values()->all();
    }
}
