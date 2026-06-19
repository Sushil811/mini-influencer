import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { toast } from 'sonner';
import { 
    ArrowLeft, 
    RefreshCw, 
    CheckCircle2, 
    AlertTriangle, 
    Calendar, 
    FileText, 
    ArrowUpRight, 
    ArrowDownRight,
    TrendingUp,
    Users,
    Layers,
    Share2,
    Instagram,
    Youtube,
    Trash2
} from 'lucide-react';

interface Profile {
    id: number;
    username: string;
    platform: string;
    followers_count: number;
    following_count: number;
    posts_count: number;
    bio: string | null;
    profile_picture_url: string | null;
    status: 'pending' | 'fetching' | 'fetched' | 'failed';
    error_message: string | null;
    last_refreshed_at: string | null;
    created_at: string;
}

interface Snapshot {
    id: number;
    followers_count: number;
    following_count: number;
    posts_count: number;
    created_at: string; // UTC ISO string
    followers_delta: number;
}

interface PaginatedSnapshots {
    data: Snapshot[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface ShowProps {
    profile: Profile;
    snapshots: PaginatedSnapshots;
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

export default function Show({ profile, snapshots, flash }: ShowProps) {
    const [loading, setLoading] = useState(false);

    // Poll server for updates in real time if profile is pending or fetching
    useEffect(() => {
        if (profile.status !== 'pending' && profile.status !== 'fetching') return;

        const interval = setInterval(() => {
            router.reload({
                only: ['profile', 'snapshots'],
                preserveState: true,
                preserveScroll: true,
            });
        }, 3000);

        return () => clearInterval(interval);
    }, [profile.status]);

    // Show toast message if flash session variables are present
    if (flash?.success) {
        toast.success(flash.success);
        flash.success = null; // Clear to prevent loops
    }
    if (flash?.error) {
        toast.error(flash.error);
        flash.error = null;
    }

    const handleDelete = () => {
        if (confirm(`Are you sure you want to remove @${profile.username} from your watchlist?`)) {
            router.delete(`/watchlist/${profile.id}`);
        }
    };

    const handleRefresh = () => {
        setLoading(true);
        router.post(`/watchlist/${profile.id}/refetch`, {}, {
            onFinish: () => setLoading(false)
        });
    };

    const formatNumber = (num: number) => {
        return num.toLocaleString('en-IN');
    };

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return 'Never';
        const date = new Date(dateStr);
        return date.toLocaleString('en-IN', {
            timeZone: 'Asia/Kolkata',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }) + ' (IST)';
    };

    const getStatusBadge = (status: Profile['status']) => {
        switch (status) {
            case 'pending':
                return <Badge className="bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30">Pending</Badge>;
            case 'fetching':
                return (
                    <Badge className="bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30 gap-1.5">
                        <RefreshCw className="size-3 animate-spin" />
                        Fetching
                    </Badge>
                );
            case 'fetched':
                return <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30">Active</Badge>;
            case 'failed':
                return <Badge className="bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30">Failed</Badge>;
            default:
                return <Badge>{status}</Badge>;
        }
    };

    // Render interactive SVG Chart based on historical snapshots
    const renderSvgChart = () => {
        const history = [...snapshots.data].reverse(); // Left-to-right (chronological)
        const width = 600;
        const height = 180;
        const padding = 20;

        if (history.length === 0) {
            return (
                <div className="flex h-60 flex-col items-center justify-center border border-dashed rounded-xl border-neutral-200 dark:border-neutral-800 text-neutral-400 text-sm gap-2">
                    <AlertTriangle className="size-6 text-neutral-300 dark:text-neutral-700 animate-pulse" />
                    <span>No snapshots captured yet.</span>
                </div>
            );
        }

        if (history.length === 1) {
            const pt = {
                x: width / 2,
                y: height / 2,
                val: history[0].followers_count,
                date: history[0].created_at
            };

            return (
                <div className="relative w-full overflow-hidden">
                    <svg viewBox={`0 0 ${width} ${height}`} className="w-full h-auto overflow-visible select-none">
                        {/* Horizontal helper grid lines */}
                        <line x1={padding} y1={padding} x2={width - padding} y2={padding} className="stroke-neutral-100 dark:stroke-neutral-900" strokeWidth={1} />
                        <line x1={padding} y1={height / 2} x2={width - padding} y2={height / 2} className="stroke-neutral-200 dark:stroke-neutral-800 stroke-[1] stroke-dasharray-[4,4]" strokeWidth={1} />
                        <line x1={padding} y1={height - padding} x2={width - padding} y2={height - padding} className="stroke-neutral-100 dark:stroke-neutral-900" strokeWidth={1} />

                        {/* Single Point Dot */}
                        <circle
                            cx={pt.x}
                            cy={pt.y}
                            r={6}
                            className="fill-blue-500 stroke-white dark:stroke-neutral-950"
                            strokeWidth={2}
                        />

                        {/* Text label near the point */}
                        <text
                            x={pt.x}
                            y={pt.y - 12}
                            textAnchor="middle"
                            className="fill-blue-500 dark:fill-blue-400 font-mono font-bold text-[11px]"
                        >
                            {formatNumber(pt.val)}
                        </text>
                    </svg>
                    <div className="flex justify-between items-center text-[10px] text-neutral-400 font-semibold px-2 mt-2">
                        <span>Captured: {new Date(history[0].created_at).toLocaleDateString('en-IN')}</span>
                        <span className="text-neutral-400 italic">
                            Waiting for subsequent checks to plot trend line...
                        </span>
                    </div>
                </div>
            );
        }

        const maxVal = Math.max(...history.map(s => s.followers_count));
        const minVal = Math.min(...history.map(s => s.followers_count));
        const valRange = maxVal - minVal || 1;

        // Map data coordinates to SVG space
        const points = history.map((s, idx) => {
            const x = padding + (idx / (history.length - 1)) * (width - padding * 2);
            // Invert y because SVG y goes downwards
            const y = height - padding - ((s.followers_count - minVal) / valRange) * (height - padding * 2);
            return { x, y, val: s.followers_count, date: s.created_at };
        });

        // Construct SVG Path
        let pathD = `M ${points[0].x} ${points[0].y}`;
        for (let i = 1; i < points.length; i++) {
            pathD += ` L ${points[i].x} ${points[i].y}`;
        }

        // Construct Area Path (for gradient fill under the line)
        const areaD = `${pathD} L ${points[points.length - 1].x} ${height - padding} L ${points[0].x} ${height - padding} Z`;

        return (
            <div className="relative w-full overflow-hidden">
                <svg viewBox={`0 0 ${width} ${height}`} className="w-full h-auto overflow-visible select-none">
                    <defs>
                        <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#3b82f6" stopOpacity="0.25" />
                            <stop offset="100%" stopColor="#3b82f6" stopOpacity="0.00" />
                        </linearGradient>
                    </defs>

                    {/* horizontal helper grid lines */}
                    <line x1={padding} y1={padding} x2={width - padding} y2={padding} className="stroke-neutral-100 dark:stroke-neutral-900" strokeWidth={1} />
                    <line x1={padding} y1={height / 2} x2={width - padding} y2={height / 2} className="stroke-neutral-100 dark:stroke-neutral-900" strokeWidth={1} />
                    <line x1={padding} y1={height - padding} x2={width - padding} y2={height - padding} className="stroke-neutral-100 dark:stroke-neutral-900" strokeWidth={1} />

                    {/* Area under the line */}
                    <path d={areaD} fill="url(#chartGradient)" />

                    {/* Line path */}
                    <path d={pathD} fill="none" className="stroke-blue-500" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round" />

                    {/* Markers / Dots */}
                    {points.map((pt, idx) => (
                        <circle
                            key={idx}
                            cx={pt.x}
                            cy={pt.y}
                            r={history.length > 15 ? 1.5 : 3.5}
                            className="fill-blue-500 stroke-white dark:stroke-neutral-950"
                            strokeWidth={1.5}
                        />
                    ))}
                </svg>
                <div className="flex justify-between items-center text-[10px] text-neutral-400 font-semibold px-2 mt-2">
                    <span>{new Date(history[0].created_at).toLocaleDateString('en-IN')}</span>
                    <span className="flex items-center gap-1 text-blue-500">
                        <TrendingUp className="size-3" />
                        Min: {formatNumber(minVal)} — Max: {formatNumber(maxVal)}
                    </span>
                    <span>{new Date(history[history.length - 1].created_at).toLocaleDateString('en-IN')}</span>
                </div>
            </div>
        );
    };

    return (
        <>
            <Head title={`@${profile.username} Watchlist Details`} />

            <div className="space-y-6 p-6 max-w-7xl mx-auto">
                {/* Back button */}
                <div>
                    <Link 
                        href="/watchlist" 
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors"
                    >
                        <ArrowLeft className="size-4" />
                        Back to Watchlist
                    </Link>
                </div>

                {/* Profile Banner */}
                <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 bg-white dark:bg-neutral-950 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-sm">
                    <div className="flex items-center gap-4 min-w-0">
                        <Avatar className="size-16 md:size-20 border-2 border-neutral-100 dark:border-neutral-900 shadow-inner">
                            {profile.profile_picture_url ? (
                                <AvatarImage src={`/profile-image-proxy?url=${encodeURIComponent(profile.profile_picture_url)}`} alt={profile.username} />
                            ) : null}
                            <AvatarFallback className="bg-neutral-100 dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400 font-bold uppercase text-lg">
                                {profile.username.substring(0, 2)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="space-y-1.5 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <h1 className="text-2xl font-extrabold text-neutral-900 dark:text-neutral-50 tracking-tight">
                                    @{profile.username}
                                </h1>
                                {getStatusBadge(profile.status)}
                            </div>
                            <div className="flex items-center gap-3 text-xs text-neutral-500 font-medium">
                                <span className="flex items-center gap-1.5 capitalize font-semibold">
                                    {profile.platform === 'youtube' ? (
                                        <Youtube className="size-4 text-rose-500" />
                                    ) : (
                                        <Instagram className="size-4 text-purple-500" />
                                    )}
                                    {profile.platform}
                                </span>
                                <span className="size-1 bg-neutral-300 rounded-full" />
                                <span>Refreshed: {formatDate(profile.last_refreshed_at)}</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3 w-full md:w-auto">
                        <Button 
                            onClick={handleRefresh} 
                            disabled={loading || profile.status === 'fetching'}
                            className="w-full md:w-auto h-10 gap-2 font-bold px-5 bg-white hover:bg-neutral-50 dark:bg-neutral-900 dark:hover:bg-neutral-800 text-neutral-900 dark:text-neutral-50 border border-neutral-200 dark:border-neutral-800 shadow-sm"
                        >
                            <RefreshCw className={`size-4 ${loading || profile.status === 'fetching' ? 'animate-spin' : ''}`} />
                            Re-fetch now
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            className="w-full md:w-auto h-10 gap-2 font-bold px-5"
                        >
                            <Trash2 className="size-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                {/* Bio / Errors alerts */}
                {profile.status === 'failed' && profile.error_message && (
                    <div className="flex gap-2.5 bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 rounded-xl p-4 text-sm font-medium">
                        <AlertTriangle className="size-5 shrink-0" />
                        <div>
                            <div className="font-bold text-rose-800 dark:text-rose-300">Scraper job failed:</div>
                            <div className="mt-0.5 leading-relaxed">{profile.error_message}</div>
                        </div>
                    </div>
                )}

                {profile.bio && (
                    <Card className="border-neutral-200 dark:border-neutral-800 shadow-sm">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm uppercase font-bold text-neutral-400">Biography</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-neutral-700 dark:text-neutral-300 leading-relaxed max-w-3xl">
                                {profile.bio}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Three Metrics Cards */}
                <div className="grid gap-6 md:grid-cols-3">
                    <Card className="border-neutral-200 dark:border-neutral-800 shadow-sm bg-white dark:bg-neutral-950">
                        <CardContent className="pt-6">
                            <div className="flex justify-between items-start">
                                <div className="space-y-1">
                                    <p className="text-sm font-semibold text-neutral-400 uppercase">
                                        {profile.platform === 'youtube' ? 'Subscribers' : 'Followers'}
                                    </p>
                                    <h3 className="text-3xl font-extrabold text-neutral-900 dark:text-neutral-50 tracking-tight">
                                        {profile.status === 'fetched' ? formatNumber(profile.followers_count) : '-'}
                                    </h3>
                                </div>
                                <div className="p-2.5 rounded-xl bg-blue-500/10 text-blue-500">
                                    <Users className="size-5" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-neutral-200 dark:border-neutral-800 shadow-sm bg-white dark:bg-neutral-950">
                        <CardContent className="pt-6">
                            <div className="flex justify-between items-start">
                                <div className="space-y-1">
                                    <p className="text-sm font-semibold text-neutral-400 uppercase">
                                        {profile.platform === 'youtube' ? 'Channels' : 'Following'}
                                    </p>
                                    <h3 className="text-3xl font-extrabold text-neutral-900 dark:text-neutral-50 tracking-tight">
                                        {profile.status === 'fetched' ? formatNumber(profile.following_count) : '-'}
                                    </h3>
                                </div>
                                <div className="p-2.5 rounded-xl bg-purple-500/10 text-purple-500">
                                    <Users className="size-5" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-neutral-200 dark:border-neutral-800 shadow-sm bg-white dark:bg-neutral-950">
                        <CardContent className="pt-6">
                            <div className="flex justify-between items-start">
                                <div className="space-y-1">
                                    <p className="text-sm font-semibold text-neutral-400 uppercase">
                                        {profile.platform === 'youtube' ? 'Total Videos' : 'Total Posts'}
                                    </p>
                                    <h3 className="text-3xl font-extrabold text-neutral-900 dark:text-neutral-50 tracking-tight">
                                        {profile.status === 'fetched' ? formatNumber(profile.posts_count) : '-'}
                                    </h3>
                                </div>
                                <div className="p-2.5 rounded-xl bg-orange-500/10 text-orange-500">
                                    <Layers className="size-5" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* SVG Trend Chart */}
                <Card className="border-neutral-200 dark:border-neutral-800 shadow-sm bg-white dark:bg-neutral-950">
                    <CardHeader>
                        <CardTitle className="text-lg font-bold flex items-center gap-2">
                            <TrendingUp className="size-4 text-blue-500" />
                            {profile.platform === 'youtube' ? 'Subscribers Trend' : 'Followers Trend'}
                        </CardTitle>
                        <CardDescription>Visual trend of public metrics across historical snapshots</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-2">
                        {renderSvgChart()}
                    </CardContent>
                </Card>

                {/* History Snapshots Table */}
                <Card className="border-neutral-200 dark:border-neutral-800 shadow-sm bg-white dark:bg-neutral-950">
                    <CardHeader>
                        <CardTitle className="text-lg font-bold flex items-center gap-2">
                            <FileText className="size-4 text-neutral-400" />
                            Historical Snapshots
                        </CardTitle>
                        <CardDescription>List of metrics records fetched over time with change deltas</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0 border-t border-neutral-100 dark:border-neutral-900">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-900/30 text-xs font-semibold uppercase text-neutral-500 tracking-wider">
                                        <th className="px-6 py-4">Captured At</th>
                                        <th className="px-6 py-4 text-right">
                                            {profile.platform === 'youtube' ? 'Subscribers' : 'Followers'}
                                        </th>
                                        <th className="px-6 py-4 text-right">Delta (Change)</th>
                                        <th className="px-6 py-4 text-right">
                                            {profile.platform === 'youtube' ? 'Channels' : 'Following'}
                                        </th>
                                        <th className="px-6 py-4 text-right">
                                            {profile.platform === 'youtube' ? 'Videos' : 'Posts'}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800 text-sm">
                                    {snapshots.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="text-center py-12 text-neutral-400">
                                                No snapshots captured yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        snapshots.data.map((snapshot) => {
                                            const delta = snapshot.followers_delta;
                                            
                                            // Format delta element: positive green, negative red, zero neutral
                                            let deltaEl = <span className="text-neutral-400 font-medium font-mono">0</span>;
                                            if (delta > 0) {
                                                deltaEl = (
                                                    <span className="inline-flex items-center gap-0.5 text-emerald-500 dark:text-emerald-400 font-bold font-mono">
                                                        <ArrowUpRight className="size-3.5" />
                                                        +{formatNumber(delta)}
                                                    </span>
                                                );
                                            } else if (delta < 0) {
                                                deltaEl = (
                                                    <span className="inline-flex items-center gap-0.5 text-rose-500 dark:text-rose-400 font-bold font-mono">
                                                        <ArrowDownRight className="size-3.5" />
                                                        {formatNumber(delta)}
                                                    </span>
                                                );
                                            }

                                            return (
                                                <tr key={snapshot.id} className="hover:bg-neutral-50/30 dark:hover:bg-neutral-900/10">
                                                    <td className="px-6 py-4 flex items-center gap-2 text-neutral-500 dark:text-neutral-400">
                                                        <Calendar className="size-3.5" />
                                                        {formatDate(snapshot.created_at)}
                                                    </td>
                                                    <td className="px-6 py-4 text-right font-semibold font-mono text-neutral-900 dark:text-neutral-50">
                                                        {formatNumber(snapshot.followers_count)}
                                                    </td>
                                                    <td className="px-6 py-4 text-right font-medium">
                                                        {deltaEl}
                                                    </td>
                                                    <td className="px-6 py-4 text-right font-medium text-neutral-500 dark:text-neutral-400 font-mono">
                                                        {formatNumber(snapshot.following_count)}
                                                    </td>
                                                    <td className="px-6 py-4 text-right font-medium text-neutral-500 dark:text-neutral-400 font-mono">
                                                        {formatNumber(snapshot.posts_count)}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Pagination */}
                {snapshots.last_page > 1 && (
                    <div className="flex items-center justify-between pt-4 px-1">
                        <p className="text-xs font-medium text-neutral-500">
                            Showing page <span className="font-bold">{snapshots.current_page}</span> of <span className="font-bold">{snapshots.last_page}</span>
                        </p>
                        <div className="flex gap-1.5">
                            {snapshots.links.map((link, idx) => {
                                const label = link.label
                                    .replace('&laquo;', '«')
                                    .replace('&raquo;', '»')
                                    .replace('Previous', 'Prev')
                                    .replace('Next', 'Next');

                                return link.url ? (
                                    <Link key={idx} href={link.url} preserveState>
                                        <Button
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                            className="h-8 min-w-[32px] text-xs font-semibold"
                                        >
                                            {label}
                                        </Button>
                                    </Link>
                                ) : (
                                    <Button
                                        key={idx}
                                        variant="outline"
                                        size="sm"
                                        disabled
                                        className="h-8 min-w-[32px] text-xs font-semibold opacity-40 text-neutral-400 pointer-events-none"
                                    >
                                        {label}
                                    </Button>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

// Register breadcrumbs for application layout
Show.layout = (props: { profile: Profile }) => ({
    breadcrumbs: [
        {
            title: 'Watchlist',
            href: '/watchlist',
        },
        {
            title: `@${props.profile.username}`,
            href: `/watchlist/${props.profile.id}`,
        },
    ],
});
