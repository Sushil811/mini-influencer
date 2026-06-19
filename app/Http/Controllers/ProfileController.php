<?php

namespace App\Http\Controllers;

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display a listing of watchlisted profiles.
     */
    public function index(Request $request): Response
    {
        $query = Profile::query();

        // 1. Search by username
        if ($request->filled('q')) {
            $search = strtolower(trim($request->input('q')));
            $query->where('username', 'like', "%{$search}%");
        }

        // 2. Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 3. Eager load or paginate (10 per page)
        $profiles = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Watchlist/Index', [
            'profiles' => $profiles,
            'filters' => $request->only(['q', 'status']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    /**
     * Store a newly watchlisted profile.
     */
    public function store(Request $request): RedirectResponse
    {
        $input = $request->all();

        // Normalize handle: lowercase, trim, and strip starting @
        if (isset($input['username'])) {
            $input['username'] = strtolower(trim(ltrim(trim($input['username']), '@')));
        }

        // Default to instagram if not provided
        $platform = strtolower(trim($input['platform'] ?? 'instagram'));
        $input['platform'] = $platform;

        $usernameRegex = $platform === 'youtube'
            ? '/^[a-zA-Z0-9._-]+$/' // YouTube allows hyphens
            : '/^[a-zA-Z0-9._]+$/';

        $validator = Validator::make($input, [
            'platform' => [
                'required',
                'string',
                'in:instagram,youtube',
            ],
            'username' => [
                'required',
                'string',
                'min:1',
                'max:50',
                'regex:'.$usernameRegex,
                Rule::unique('profiles')->where(function ($query) use ($input) {
                    return $query->where('username', $input['username'])
                        ->where('platform', $input['platform']);
                }),
            ],
        ], [
            'username.regex' => 'The username format is invalid.',
            'username.unique' => 'This profile handle is already on your watchlist for this platform.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Create pending profile
        $profile = Profile::create([
            'username' => $input['username'],
            'platform' => $input['platform'],
            'status' => 'pending',
        ]);

        // Dispatch queued background job
        FetchProfileJob::dispatch($profile->id);

        $platformName = ucfirst($profile->platform);

        return redirect()->route('watchlist.index')->with('success', "Added @{$profile->username} ({$platformName}) to the watchlist. Fetching metrics...");
    }

    /**
     * Display a profile's detail page and snapshot history.
     */
    public function show(Profile $profile): Response
    {
        // Use a window function (LAG) to calculate the follower delta in a single query.
        // This avoids N+1 query loop and is highly efficient.
        // LAG(followers_count, 1, followers_count) gets the previous row's followers.
        // If there is no previous row, it defaults to current followers (resulting in delta 0).
        $snapshots = DB::table('profile_snapshots')
            ->select('id', 'followers_count', 'following_count', 'posts_count', 'created_at')
            ->selectRaw('followers_count - LAG(followers_count, 1, followers_count) OVER (PARTITION BY profile_id ORDER BY created_at ASC) as followers_delta')
            ->where('profile_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Convert timestamps to UTC ISO8601 strings so frontend can format locally in IST.
        $snapshots->getCollection()->transform(function ($item) {
            $item->created_at = (new \DateTime($item->created_at, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
            $item->followers_delta = (int) $item->followers_delta;

            return $item;
        });

        return Inertia::render('Watchlist/Show', [
            'profile' => $profile,
            'snapshots' => $snapshots,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Manually trigger a profile metrics re-fetch.
     */
    public function refetch(Profile $profile): RedirectResponse
    {
        if ($profile->status === 'fetching') {
            return redirect()->back()->with('error', 'Profile refresh is already in progress.');
        }

        $profile->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        FetchProfileJob::dispatch($profile->id);

        return redirect()->back()->with('success', "Queued refresh job for @{$profile->username}.");
    }

    /**
     * Remove the specified profile from the watchlist.
     */
    public function destroy(Profile $profile): RedirectResponse
    {
        $profile->delete();

        return redirect()->route('watchlist.index')->with('success', "Removed @{$profile->username} from your watchlist.");
    }
}
