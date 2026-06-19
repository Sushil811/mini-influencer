import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { 
    Users, 
    Database, 
    RefreshCw, 
    AlertTriangle, 
    CheckCircle2, 
    TrendingUp, 
    Activity, 
    ExternalLink, 
    ArrowRight,
    Instagram,
    Youtube
} from 'lucide-react';

interface DashboardProps {
    stats: {
        total_profiles: number;
        pending_scrapes: number;
        active_scrapes: number;
        fetched_scrapes: number;
        failed_scrapes: number;
        total_snapshots: number;
    };
    system: {
        circuit_breaker_state: 'CLOSED' | 'OPEN' | 'HALF_OPEN';
        rate_limiter_tokens: number;
        quota_consumed: number;
        quota_limit: number;
    };
    top_influencers: {
        id: number;
        username: string;
        followers_count: number;
        following_count: number;
        posts_count: number;
        profile_picture_url: string | null;
        last_refreshed_at: string | null;
    }[];
    recent_snapshots: {
        id: number;
        username: string;
        followers_count: number;
        created_at: string;
    }[];
}

export default function Dashboard({ stats, system, top_influencers, recent_snapshots }: DashboardProps) {
    // Poll dashboard stats if there are active or pending scrapes running in queue
    useEffect(() => {
        if (stats.active_scrapes === 0 && stats.pending_scrapes === 0) return;

        const interval = setInterval(() => {
            router.reload({
                only: ['stats', 'top_influencers', 'recent_snapshots', 'system'],
                preserveState: true,
                preserveScroll: true,
            });
        }, 3000);

        return () => clearInterval(interval);
    }, [stats.active_scrapes, stats.pending_scrapes]);

    const quotaPercentage = Math.min(100, Math.round((system.quota_consumed / system.quota_limit) * 100));
    const quotaWarning = system.quota_consumed >= system.quota_limit * 0.9;

    const formatNumber = (num: number) => {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return num.toString();
    };

    const getCbBadge = (state: string) => {
        switch (state) {
            case 'CLOSED':
                return <Badge className="bg-emerald-500/10 text-emerald-500 border-emerald-500/30 gap-1" variant="outline">Healthy (Closed)</Badge>;
            case 'OPEN':
                return <Badge className="bg-rose-500/10 text-rose-500 border-rose-500/30 gap-1 animate-pulse" variant="outline">Tripped (Open)</Badge>;
            case 'HALF_OPEN':
                return <Badge className="bg-amber-500/10 text-amber-500 border-amber-500/30 gap-1" variant="outline">Testing (Half-Open)</Badge>;
            default:
                return <Badge>{state}</Badge>;
        }
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="space-y-6 p-6 max-w-7xl mx-auto">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-extrabold tracking-tight text-neutral-900 dark:text-neutral-50">
                        Metrics Dashboard
                    </h1>
                    <p className="text-neutral-500 dark:text-neutral-400 mt-1">
                        Real-time status of influencer tracking and API rates.
                    </p>
                </div>

                {/* DB Stats Cards */}
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950">
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-bold text-neutral-500">Monitored Accounts</CardTitle>
                            <Users className="size-4 text-neutral-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-extrabold">{stats.total_profiles}</div>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                {stats.fetched_scrapes} active &middot; {stats.pending_scrapes} pending
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950">
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-bold text-neutral-500">Total Snapshots</CardTitle>
                            <Database className="size-4 text-neutral-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-extrabold">{stats.total_snapshots}</div>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                Time-series points captured
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950">
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-bold text-neutral-500">Active Scrapes</CardTitle>
                            <RefreshCw className="size-4 text-blue-500 animate-spin" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-extrabold">{stats.active_scrapes}</div>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                Currently fetching metrics
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950">
                        <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                            <CardTitle className="text-sm font-bold text-neutral-500">Failed Profiles</CardTitle>
                            <AlertTriangle className={`size-4 ${stats.failed_scrapes > 0 ? 'text-rose-500' : 'text-neutral-400'}`} />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-extrabold">{stats.failed_scrapes}</div>
                            <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                Out-of-bounds or private handles
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* System & API Limits Overview */}
                <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950">
                    <CardHeader>
                        <CardTitle className="text-lg font-bold flex items-center gap-2">
                            <Activity className="size-5 text-indigo-500" />
                            System Health & Limits
                        </CardTitle>
                        <CardDescription>Status of Redis token bucket rate limiters and external APIs</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6 md:grid-cols-3">
                        <div className="space-y-2">
                            <span className="text-sm font-bold text-neutral-500">Circuit Breaker</span>
                            <div className="flex items-center gap-2 mt-1">
                                {getCbBadge(system.circuit_breaker_state)}
                            </div>
                            <p className="text-xs text-neutral-400 leading-normal">
                                Automatically trips to block requests if consecutive API errors reach 10.
                            </p>
                        </div>

                        <div className="space-y-2">
                            <span className="text-sm font-bold text-neutral-500">Rate Limiter Bucket</span>
                            <div className="text-lg font-extrabold mt-1">
                                {system.rate_limiter_tokens} <span className="text-xs font-normal text-neutral-500">/ 30.0 tokens</span>
                            </div>
                            <div className="w-full bg-neutral-100 dark:bg-neutral-800 rounded-full h-2 overflow-hidden mt-2">
                                <div 
                                    className="bg-indigo-500 h-2 rounded-full transition-all duration-300" 
                                    style={{ width: `${Math.min(100, (system.rate_limiter_tokens / 30) * 100)}%` }} 
                                />
                            </div>
                            <p className="text-xs text-neutral-400 leading-normal">
                                Token bucket refills at 0.5 tokens/sec. Acquired per scrap.
                            </p>
                        </div>

                        <div className="space-y-2">
                            <div className="flex justify-between items-center">
                                <span className="text-sm font-bold text-neutral-500">Daily API Quota</span>
                                {quotaWarning && (
                                    <span className="text-[10px] font-bold text-rose-500 flex items-center gap-1">
                                        <AlertTriangle className="size-3" /> Near Limit (90%)
                                    </span>
                                )}
                            </div>
                            <div className="text-lg font-extrabold mt-1">
                                {system.quota_consumed} <span className="text-xs font-normal text-neutral-500">/ {system.quota_limit} calls (IST)</span>
                            </div>
                            <div className="w-full bg-neutral-100 dark:bg-neutral-800 rounded-full h-2 overflow-hidden mt-2">
                                <div 
                                    className={`h-2 rounded-full transition-all duration-300 ${quotaWarning ? 'bg-rose-500' : 'bg-emerald-500'}`} 
                                    style={{ width: `${quotaPercentage}%` }} 
                                />
                            </div>
                            <p className="text-xs text-neutral-400 leading-normal">
                                Tracks requests in Indian Standard Time (IST). Refuses tasks at 90% capacity.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Details Section Grid */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Top Monitored Leaderboard */}
                    <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-lg font-bold">Top Influencers</CardTitle>
                                <CardDescription>Highest follower counts on your watchlist</CardDescription>
                            </div>
                            <TrendingUp className="size-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            {top_influencers.length === 0 ? (
                                <p className="text-sm text-neutral-400 text-center py-6">No fetched profiles available.</p>
                            ) : (
                                <div className="space-y-4">
                                    {top_influencers.map((profile) => (
                                        <div key={profile.id} className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-900 pb-3 last:border-0 last:pb-0">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-9 border">
                                                    {profile.profile_picture_url ? (
                                                        <AvatarImage src={`/profile-image-proxy?url=${encodeURIComponent(profile.profile_picture_url)}`} alt={profile.username} />
                                                    ) : null}
                                                    <AvatarFallback className="text-xs font-bold uppercase">
                                                        {profile.username.substring(0, 2)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <Link 
                                                        href={`/watchlist/${profile.id}`} 
                                                        className="hover:underline font-bold text-sm text-neutral-900 dark:text-neutral-100 flex items-center gap-1 group"
                                                    >
                                                        @{profile.username}
                                                        <ExternalLink className="size-3 text-neutral-400 opacity-0 group-hover:opacity-100 transition-opacity" />
                                                    </Link>
                                                    <p className="text-xs text-neutral-400 capitalize flex items-center gap-1">
                                                        {profile.platform === 'youtube' ? (
                                                            <Youtube className="size-3 text-rose-500" />
                                                        ) : (
                                                            <Instagram className="size-3 text-purple-500" />
                                                        )}
                                                        {profile.platform}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-sm font-bold font-mono">{formatNumber(profile.followers_count)}</div>
                                                <p className="text-[10px] text-neutral-400">
                                                    {profile.platform === 'youtube' ? 'subscribers' : 'followers'}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent Metric Snapshots */}
                    <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-lg font-bold">Recent Captures</CardTitle>
                                <CardDescription>Latest metrics updates written to DB</CardDescription>
                            </div>
                            <Link href="/watchlist">
                                <Button variant="ghost" size="sm" className="h-8 text-xs font-semibold gap-1">
                                    Watchlist <ArrowRight className="size-3" />
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recent_snapshots.length === 0 ? (
                                <p className="text-sm text-neutral-400 text-center py-6">No snapshots recorded yet.</p>
                            ) : (
                                <div className="space-y-4">
                                    {recent_snapshots.map((snapshot) => (
                                        <div key={snapshot.id} className="flex items-center justify-between border-b border-neutral-100 dark:border-neutral-900 pb-3 last:border-0 last:pb-0">
                                            <div className="flex items-center gap-3">
                                                <CheckCircle2 className="size-4 text-emerald-500 shrink-0" />
                                                <div>
                                                    <p className="font-bold text-sm">@{snapshot.username}</p>
                                                    <p className="text-[10px] text-neutral-400">
                                                        {new Date(snapshot.created_at).toLocaleString('en-IN', {
                                                            timeZone: 'Asia/Kolkata',
                                                            day: '2-digit',
                                                            month: 'short',
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        })} (IST)
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-sm font-mono font-bold text-neutral-700 dark:text-neutral-300">
                                                {formatNumber(snapshot.followers_count)}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
    ],
};
