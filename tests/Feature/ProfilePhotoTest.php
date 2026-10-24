<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Profile photos: every account can upload, replace and remove one on the website, and buyers and riders
 * can in the app. Uploads are cropped to a 512px WebP square; a Google photo never replaces an upload.
 */
class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;
    use SignsInMobile;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_buyer_uploads_replaces_and_removes_a_photo_on_the_website(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->get(route('buyer.account'))->assertOk()->assertSee('Upload photo');

        $this->from(route('buyer.account'))
            ->post(route('account.photo.update'), ['photo' => UploadedFile::fake()->image('wide.jpg', 1200, 800)])
            ->assertRedirect(route('buyer.account'))->assertSessionHas('status', 'Profile photo updated.');

        $first = $buyer->fresh()->getRawOriginal('avatar');
        $this->assertStringStartsWith("avatars/{$buyer->id}/", $first);
        $this->assertStringEndsWith('.webp', $first);
        Storage::disk('public')->assertExists($first);
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($first));
        $this->assertSame([512, 512, IMAGETYPE_WEBP], [$width, $height, $type]);

        $page = $this->get(route('buyer.account'))->assertSee('Change photo')->assertSee('Remove');
        $page->assertSee(Storage::disk('public')->url($first), false);

        $this->post(route('account.photo.update'), ['photo' => UploadedFile::fake()->image('new.png', 300, 300)]);
        $second = $buyer->fresh()->getRawOriginal('avatar');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->delete(route('account.photo.destroy'))->assertSessionHas('status', 'Profile photo removed.');
        $this->assertNull($buyer->fresh()->avatar);
        Storage::disk('public')->assertMissing($second);
        $this->get(route('buyer.account'))->assertSee('Upload photo')->assertDontSee('<img src="'.Storage::disk('public')->url($second), false);
    }

    public function test_only_reasonable_images_are_accepted(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer);

        $this->post(route('account.photo.update'), ['photo' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors(['photo' => 'Use a JPG, PNG or WebP photo.']);
        $this->post(route('account.photo.update'), ['photo' => UploadedFile::fake()->image('huge.jpg', 600, 600)->size(6000)])
            ->assertSessionHasErrors(['photo' => 'The photo must be 5 MB or smaller.']);
        $this->post(route('account.photo.update'), ['photo' => UploadedFile::fake()->image('tiny.png', 20, 20)])
            ->assertSessionHasErrors('photo');
        $this->post(route('account.photo.update'))->assertSessionHasErrors(['photo' => 'Choose a photo to upload.']);

        $this->assertNull($buyer->fresh()->avatar);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_every_account_page_offers_the_photo_and_headers_show_it(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->get(route('seller.account'))->assertOk()->assertSee('Profile photo')->assertSee(route('account.photo.update'), false);
        $this->post(route('account.photo.update'), ['photo' => UploadedFile::fake()->image('me.jpg', 400, 400)])->assertSessionHasNoErrors();
        $url = $seller->fresh()->avatar_url;
        $this->get(route('seller.account'))->assertSee('class="user-avatar header-avatar"', false)->assertSee($url, false);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.account'))->assertOk()->assertSee(route('account.photo.update'), false);

        $this->post(route('logout'));
        $this->post(route('account.photo.update'), ['photo' => UploadedFile::fake()->image('me.jpg', 400, 400)])->assertRedirect(route('login'));
    }

    public function test_buyers_and_riders_change_their_photo_in_the_app(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->signInMobile($buyer)->getJson('/api/mobile/me')->assertOk()->assertJsonPath('user.avatar_url', null);

        $url = $this->postJson('/api/mobile/account/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 900, 1600)])
            ->assertOk()->json('avatar_url');
        $this->assertSame(Storage::disk('public')->url($buyer->fresh()->getRawOriginal('avatar')), $url);
        $this->getJson('/api/mobile/me')->assertJsonPath('user.avatar_url', $url);
        $this->postJson('/api/mobile/account/photo', ['photo' => UploadedFile::fake()->create('a.gif', 10, 'image/gif')])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->deleteJson('/api/mobile/account/photo')->assertOk()->assertJsonPath('avatar_url', null);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $rider = $this->approvedRider();
        $this->signInMobile($rider)->postJson('/api/mobile/account/photo', ['photo' => UploadedFile::fake()->image('r.png', 500, 500)])->assertOk();
        $this->getJson('/api/mobile/rider/dashboard')->assertOk()->assertJsonPath('rider.avatar_url', $rider->fresh()->avatar_url);

        $this->app['auth']->forgetGuards();
        $this->withToken('nope')->postJson('/api/mobile/account/photo', ['photo' => UploadedFile::fake()->image('x.png', 100, 100)])->assertUnauthorized();
    }

    public function test_the_photo_is_cropped_to_the_square_the_user_framed(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer);
        // 400 x 200: left half red, right half blue.
        $photo = fn () => UploadedFile::fake()->createWithContent('halves.png', $this->halves());

        // The right-hand square, as the browser or app sends it.
        $this->post(route('account.photo.update'), [
            'photo' => $photo(), 'crop_x' => 0.5, 'crop_y' => 0, 'crop_width' => 0.5, 'crop_height' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame([0, 0, 255], $this->colorAt(256, 256, $buyer));
        $this->assertSame([0, 0, 255], $this->colorAt(10, 256, $buyer));

        // A zoomed-in square on the left: still only red, with a slightly off-square rectangle and one past the edge.
        $this->post(route('account.photo.update'), [
            'photo' => $photo(), 'crop_x' => 0.05, 'crop_y' => 0.25, 'crop_width' => 0.2, 'crop_height' => 0.41,
        ])->assertSessionHasNoErrors();
        $this->assertSame([255, 0, 0], $this->colorAt(256, 256, $buyer));
        $this->post(route('account.photo.update'), [
            'photo' => $photo(), 'crop_x' => 0.9, 'crop_y' => 0.9, 'crop_width' => 0.5, 'crop_height' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame([0, 0, 255], $this->colorAt(500, 500, $buyer));

        // Without a crop: the centered square, half red and half blue.
        $this->post(route('account.photo.update'), ['photo' => $photo()])->assertSessionHasNoErrors();
        $this->assertSame([255, 0, 0], $this->colorAt(10, 256, $buyer));
        $this->assertSame([0, 0, 255], $this->colorAt(500, 256, $buyer));

        $before = $buyer->fresh()->avatar;
        $this->post(route('account.photo.update'), ['photo' => $photo(), 'crop_x' => 0.5])
            ->assertSessionHasErrors(['crop_y' => 'The photo could not be cropped. Please choose it again.']);
        $this->post(route('account.photo.update'), ['photo' => $photo(), 'crop_x' => 2, 'crop_y' => 0, 'crop_width' => 0, 'crop_height' => 1])
            ->assertSessionHasErrors(['crop_x', 'crop_width']);
        $this->assertSame($before, $buyer->fresh()->avatar);

        $this->signInMobile($buyer)->postJson('/api/mobile/account/photo', [
            'photo' => $photo(), 'crop_x' => '0.5', 'crop_y' => '0', 'crop_width' => '0.5', 'crop_height' => '1',
        ])->assertOk();
        $this->assertSame([0, 0, 255], $this->colorAt(256, 256, $buyer));
    }

    private function halves(): string
    {
        $image = imagecreatetruecolor(400, 200);
        imagefilledrectangle($image, 0, 0, 199, 199, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 200, 0, 399, 199, imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** The color of one pixel of the saved 512px photo, rounded to cancel WebP compression noise. */
    private function colorAt(int $x, int $y, User $user): array
    {
        $image = imagecreatefromstring(Storage::disk('public')->get($user->fresh()->getRawOriginal('avatar')));
        $rgb = imagecolorat($image, $x, $y);

        return array_map(fn (int $channel) => $channel > 127 ? 255 : 0, [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF]);
    }

    public function test_a_google_photo_shows_until_the_user_uploads_one(): void
    {
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'avatar' => 'https://lh3.googleusercontent.com/me']);
        $this->assertSame('https://lh3.googleusercontent.com/me', $user->avatar_url);
        $this->assertFalse($user->hasUploadedAvatar());

        $user->forceFill(['avatar' => 'avatars/'.$user->id.'/mine.webp'])->save();
        $this->assertTrue($user->fresh()->hasUploadedAvatar());
        $this->assertSame(Storage::disk('public')->url('avatars/'.$user->id.'/mine.webp'), $user->fresh()->avatar_url);
        $this->assertSame('AB', User::factory()->make(['name' => 'Ana  Maria Bautista'])->initials());
    }
}
