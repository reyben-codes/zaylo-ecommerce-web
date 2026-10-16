<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\PlatformPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Admin Center: platform settings. Post announcements to buyers, sellers, riders or logistics partners,
 * and update the policy pages everyone agrees to.
 */
class AdminSettingsController extends Controller
{
    public function index()
    {
        if (! Schema::hasTable('announcements') || ! Schema::hasTable('platform_policies')) {
            return view('admin.setup-needed', ['title' => 'Platform settings']);
        }

        return view('admin.settings', [
            'announcements' => Announcement::with('author')->latest()->paginate(10),
            'audiences' => Announcement::AUDIENCES,
            'policies' => collect(PlatformPolicy::PAGES)->map(fn ($title, $slug) => [
                'slug' => $slug,
                'title' => $title,
                'record' => PlatformPolicy::with('editor')->where('slug', $slug)->first(),
            ]),
        ]);
    }

    public function storeAnnouncement(Request $request)
    {
        Announcement::create($this->announcementData($request) + ['created_by' => $request->user()->id]);

        return back()->with('status', 'Announcement saved.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement)
    {
        if ($request->has('toggle')) {
            $announcement->update(['is_published' => ! $announcement->is_published]);

            return back()->with('status', $announcement->is_published ? 'Announcement published.' : 'Announcement moved to drafts.');
        }
        $announcement->update($this->announcementData($request));

        return back()->with('status', 'Announcement updated.');
    }

    public function destroyAnnouncement(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('status', 'Announcement deleted.');
    }

    public function editPolicy(string $slug)
    {
        abort_unless(isset(PlatformPolicy::PAGES[$slug]), 404);

        return view('admin.policy', [
            'slug' => $slug,
            'title' => PlatformPolicy::PAGES[$slug],
            'policy' => PlatformPolicy::forSlug($slug),
        ]);
    }

    public function updatePolicy(Request $request, string $slug)
    {
        abort_unless(isset(PlatformPolicy::PAGES[$slug]), 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'body' => ['required', 'string', 'min:50', 'max:60000'],
        ], ['body.min' => 'The policy text must be at least 50 characters.']);
        PlatformPolicy::updateOrCreate(['slug' => $slug], $data + ['updated_by' => $request->user()->id]);

        return redirect()->route('admin.settings')->with('status', $data['title'].' updated. The public page shows the new text now.');
    }

    private function announcementData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'body' => ['required', 'string', 'min:5', 'max:1000'],
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at', 'after:now'],
            'is_published' => ['nullable', 'boolean'],
        ], [
            'ends_at.after' => 'The end date must be in the future and after the start date.',
        ]);
        // The form uses Philippine time.
        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? Carbon::parse($data[$field], 'Asia/Manila')->utc() : null;
        }
        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }
}
