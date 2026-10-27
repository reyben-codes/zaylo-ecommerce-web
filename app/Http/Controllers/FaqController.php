<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Questions and answers for Zay, the help bot (config/faq.php): the signed-in account's own, plus the ones for everyone.
 * The bot matches typed questions itself and hands over to Help & support when nothing fits.
 */
class FaqController extends Controller
{
    /** Account roles that share another role's answers. */
    private const ROLE_ALIASES = ['courier' => 'rider', 'sorting_center' => 'logistics'];

    /** The bot on every website portal. */
    public function web(Request $request): JsonResponse
    {
        $role = self::roleOf($request->user());

        return response()->json([
            'greeting' => 'Hi '.(strtok((string) $request->user()->name, ' ') ?: 'there')."! I'm Zay, ZAYLO's helper. Tap a question below or ask me in your own words.",
            'items' => self::items($role, withLinks: true),
            // Admins are the support team, so they get their inbox instead.
            'support' => $role === 'admin'
                ? ['url' => route('admin.chat'), 'label' => 'Open support inbox']
                : ['url' => route('support'), 'label' => 'Message ZAYLO support'],
        ]);
    }

    /** The bot in the app: buyer answers, or rider answers for the rider app. */
    public function mobile(Request $request): JsonResponse
    {
        $role = $request->validate(['role' => ['nullable', 'in:buyer,rider']])['role'] ?? 'buyer';

        return response()->json([
            'greeting' => "Hi! I'm Zay, ZAYLO's helper. Tap a question below or ask me in your own words.",
            'items' => self::items($role, withLinks: false),
        ]);
    }

    /** @return list<array{q: string, a: string, keywords: list<string>, link?: array{url: string, label: string}}> */
    public static function items(string $role, bool $withLinks): array
    {
        $entries = array_merge(config("faq.{$role}", []), config('faq.all', []));

        return array_map(function (array $entry) use ($withLinks) {
            $item = ['q' => $entry['q'], 'a' => $entry['a'], 'keywords' => $entry['keywords'] ?? []];
            $link = $entry['link'] ?? null;
            if ($withLinks && $link && Route::has($link['route'])) {
                $item['link'] = ['url' => route($link['route']), 'label' => $link['label']];
            }

            return $item;
        }, $entries);
    }

    public static function roleOf(User $user): string
    {
        $role = self::ROLE_ALIASES[$user->role] ?? $user->role;

        return config()->has("faq.{$role}") ? $role : 'buyer';
    }
}
