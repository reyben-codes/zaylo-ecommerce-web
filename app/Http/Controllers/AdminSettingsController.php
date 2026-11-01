<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\PlatformPolicy;
use App\Notifications\AnnouncementPosted;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $announcement = Announcement::create($this->announcementData($request) + ['created_by' => $request->user()->id]);

        return back()->with('status', 'Announcement saved.'.$this->emailOnce($announcement));
    }

    public function updateAnnouncement(Request $request, Announcement $announcement)
    {
        if ($request->has('toggle')) {
            $announcement->update(['is_published' => ! $announcement->is_published]);

            return back()->with('status', $announcement->is_published ? 'Announcement published.'.$this->emailOnce($announcement) : 'Announcement moved to drafts.');
        }
        $announcement->update($this->announcementData($request));

        return back()->with('status', 'Announcement updated.'.$this->emailOnce($announcement));
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
            'builtIn' => PlatformPolicy::builtIn($slug),
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
            'show_in_section' => ['nullable', 'boolean'],
            'show_as_modal' => ['nullable', 'boolean'],
            'send_email' => ['nullable', 'boolean'],
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
        foreach (array_keys(Announcement::CHANNELS) as $channel) {
            $data[$channel] = $request->boolean($channel);
        }
        if (! $data['show_in_section'] && ! $data['show_as_modal'] && ! $data['send_email']) {
            throw ValidationException::withMessages(['channels' => 'Choose at least one way to show this announcement.']);
        }
        // Nothing here sends mail at a later time: the host may not run the scheduler.
        if ($data['send_email'] && $data['starts_at']?->isFuture()) {
            throw ValidationException::withMessages(['starts_at' => 'Email goes out as soon as an announcement is live. Remove the start time, or untick Email.']);
        }

        return $data;
    }

    /** Emails a live announcement the first time only, and returns the sentence for the admin's confirmation. */
    private function emailOnce(Announcement $announcement): string
    {
        if (! $announcement->send_email || $announcement->state !== 'live') {
            return '';
        }
        // Not through the model: a changed updated_at would show the announcement again to those who dismissed it.
        if (! DB::table('announcements')->where('id', $announcement->id)->whereNull('emailed_at')->update(['emailed_at' => now()])) {
            return '';
        }

        // Sent during this request, since the host runs no queue worker; a long list must not hit PHP's time limit.
        set_time_limit(0);
        $sent = $failed = 0;
        foreach ($announcement->emailRecipients() as $user) {
            try {
                $user->notify(new AnnouncementPosted($announcement));
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        return " Emailed {$sent} ".($sent === 1 ? 'person' : 'people').'.'.($failed ? " {$failed} could not be sent." : '');
    }
}
