import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { Search, Plus, ExternalLink, RefreshCw, AlertTriangle, HelpCircle, CheckCircle2, Instagram, Youtube, Trash2 } from 'lucide-react';

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

interface PaginatedProfiles {
    data: Profile[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface IndexProps {
    profiles: PaginatedProfiles;
    filters: {
        q?: string;
        status?: string;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

export default function Index({ profiles, filters, flash }: IndexProps) {
    const [search, setSearch] = useState(filters.q || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');

    // Add Profile Form hook
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        username: '',
        platform: 'instagram',
    });

    // Update query parameters on search/filter change
    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            router.get(
                '/watchlist',
                { q: search, status: statusFilter },
                { preserveState: true, replace: true }
            );
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [search, statusFilter]);

    // Poll server for updates in real time if any profile is pending or fetching
    useEffect(() => {
        const hasPendingOrFetching = profiles.data.some(
            (p) => p.status === 'pending' || p.status === 'fetching'
        );

        if (!hasPendingOrFetching) return;

        const interval = setInterval(() => {
            router.reload({
                only: ['profiles'],
                preserveState: true,
                preserveScroll: true,
            });
        }, 3000);

        return () => clearInterval(interval);
    }, [profiles.data]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/watchlist', {
            onSuccess: () => {
                reset();
                clearErrors();
            },
        });
    };

    const formatNumber = (num: number) => {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    };

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return 'Never';
        // Convert UTC string from database into user's local timezone (e.g. IST)
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

    const handleDelete = (id: number, username: string) => {
        if (confirm(`Are you sure you want to remove @${username} from your watchlist?`)) {
            router.delete(`/watchlist/${id}`);
        }
    };

    const getStatusBadge = (status: Profile['status']) => {
        switch (status) {
            case 'pending':
                return (
                    <Badge variant="outline" className="bg-amber-500/10 text-amber-500 border-amber-500/30 gap-1">
                        <span className="size-1.5 rounded-full bg-amber-500 animate-pulse" />
                        Pending
                    </Badge>
                );
            case 'fetching':
                return (
                    <Badge variant="outline" className="bg-blue-500/10 text-blue-500 border-blue-500/30 gap-1">
                        <RefreshCw className="size-3 animate-spin" />
                        Fetching
                    </Badge>
                );
            case 'fetched':
                return (
                    <Badge variant="outline" className="bg-emerald-500/10 text-emerald-500 border-emerald-500/30 gap-1">
                        <CheckCircle2 className="size-3 text-emerald-500" />
                        Fetched
                    </Badge>
                );
            case 'failed':
                return (
                    <Badge variant="outline" className="bg-rose-500/10 text-rose-500 border-rose-500/30 gap-1">
                        <AlertTriangle className="size-3 text-rose-500" />
                        Failed
                    </Badge>
                );
            default:
                return <Badge>{status}</Badge>;
        }
    };

    return (
        <>
            <Head title="Influencer Watchlist" />

            <div className="space-y-6 p-6 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-extrabold tracking-tight text-neutral-900 dark:text-neutral-50">
                            Influencer Watchlist
                        </h1>
                        <p className="text-neutral-500 dark:text-neutral-400 mt-1">
                            Track, monitor and analyze metrics over time.
                        </p>
                    </div>
                </div>

                {/* Grid for Add Profile and Quick Stats */}
                <div className="grid gap-6 md:grid-cols-3">
                    {/* Add Form Card */}
                    <Card className="md:col-span-2 border-neutral-200 dark:border-neutral-800 shadow-sm bg-white dark:bg-neutral-950">
                        <CardHeader>
                            <CardTitle className="text-lg font-bold">Add Profile to Watchlist</CardTitle>
                            <CardDescription>
                                Enter a username handle and select its platform to begin tracking public metrics.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-3">
                                <div className="flex flex-col sm:flex-row gap-3">
                                    <div className="w-full sm:w-40">
                                        <select
                                            value={data.platform}
                                            onChange={(e) => setData('platform', e.target.value)}
                                            className="h-10 w-full rounded-md border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950 px-3 py-1 text-sm font-medium shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                            disabled={processing}
                                        >
                                            <option value="instagram">Instagram</option>
                                            <option value="youtube">YouTube</option>
                                        </select>
                                    </div>
                                    <div className="relative flex-1">
                                        <span className="absolute left-3 top-2.5 text-neutral-400 font-medium select-none">@</span>
                                        <Input
                                            type="text"
                                            placeholder={data.platform === 'youtube' ? 'mrbeast' : 'cristiano'}
                                            value={data.username}
                                            onChange={(e) => setData('username', e.target.value)}
                                            className={`pl-8 h-10 w-full rounded-md border ${
                                                errors.username 
                                                    ? 'border-rose-500 focus-visible:ring-rose-500' 
                                                    : 'border-neutral-200 dark:border-neutral-800'
                                            }`}
                                            disabled={processing}
                                        />
                                    </div>
                                    <Button type="submit" size="default" disabled={processing} className="h-10 px-5 gap-2">
                                        <Plus className="size-4" />
                                        Add to Watchlist
                                    </Button>
                                </div>
                                {errors.username && (
                                    <p className="text-sm text-rose-500 font-medium mt-1 flex items-center gap-1">
                                        <AlertTriangle className="size-3.5 inline" />
                                        {errors.username}
                                    </p>
                                )}
                            </form>
                        </CardContent>
                    </Card>

                    {/* Quick Info Card */}
                    <Card className="border-neutral-200 dark:border-neutral-800 shadow-sm bg-white dark:bg-neutral-950">
                        <CardHeader>
                            <CardTitle className="text-lg font-bold">Platform Status</CardTitle>
                            <CardDescription>Metrics dashboard summary</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3.5">
                            <div className="flex justify-between items-center pb-2 border-b border-neutral-100 dark:border-neutral-900">
                                <span className="text-neutral-500 font-medium">Total Monitored</span>
                                <span className="text-xl font-extrabold text-neutral-900 dark:text-neutral-50">{profiles.total}</span>
                            </div>
                            <div className="flex justify-between items-center pb-2 border-b border-neutral-100 dark:border-neutral-900">
                                <span className="text-neutral-500 font-medium">Active Scrapes</span>
                                <span className="text-sm font-semibold flex items-center gap-1.5 text-blue-500">
                                    <span className="size-2 rounded-full bg-blue-500 animate-ping" />
                                    {profiles.data.filter(p => p.status === 'fetching').length} Running
                                </span>
                            </div>
                            <div className="text-xs text-neutral-400 leading-relaxed">
                                Profiles older than 1 hour are automatically refreshed every 10 minutes by the scheduled system task.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters and Search */}
                <div className="flex flex-col sm:flex-row gap-4 items-center justify-between bg-neutral-50 dark:bg-neutral-900/40 p-4 rounded-xl border border-neutral-200/60 dark:border-neutral-800">
                    <div className="relative w-full sm:max-w-md">
                        <Search className="absolute left-3 top-2.5 size-4 text-neutral-400" />
                        <Input
                            type="text"
                            placeholder="Search by username..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9 bg-white dark:bg-neutral-950 border-neutral-200 dark:border-neutral-800"
                        />
                    </div>
                    
                    <div className="w-full sm:w-auto flex items-center gap-2">
                        <span className="text-sm font-medium text-neutral-500 shrink-0">Filter Status:</span>
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="w-full sm:w-40 h-9 rounded-md border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950 px-3 py-1 text-sm font-medium shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="fetching">Fetching</option>
                            <option value="fetched">Fetched</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-800 shadow-sm bg-white dark:bg-neutral-950">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-900/50 text-xs font-semibold uppercase text-neutral-500 tracking-wider">
                                    <th className="px-6 py-4">Profile</th>
                                    <th className="px-6 py-4">Platform</th>
                                    <th className="px-6 py-4 text-right">Followers</th>
                                    <th className="px-6 py-4 text-right">Posts</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4">Last Refreshed</th>
                                    <th className="px-6 py-4 text-center">Actions</th>
                                </tr>
                            </thead>
                            {profiles.data.length > 0 && (
                                <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800 text-sm">
                                    {profiles.data.map((profile) => (
                                        <tr 
                                            key={profile.id} 
                                            className="hover:bg-neutral-50/50 dark:hover:bg-neutral-900/30 transition-colors"
                                        >
                                            <td className="px-6 py-4 font-medium text-neutral-900 dark:text-neutral-50">
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-10 border border-neutral-100 dark:border-neutral-900">
                                                        {profile.profile_picture_url ? (
                                                            <AvatarImage src={`/profile-image-proxy?url=${encodeURIComponent(profile.profile_picture_url)}`} alt={profile.username} />
                                                        ) : null}
                                                        <AvatarFallback className="bg-neutral-100 dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400 font-bold uppercase text-xs">
                                                            {profile.username.substring(0, 2)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex flex-col min-w-0">
                                                        <Link 
                                                            href={`/watchlist/${profile.id}`} 
                                                            className="hover:underline font-bold text-neutral-950 dark:text-neutral-100 truncate flex items-center gap-1 group"
                                                        >
                                                            @{profile.username}
                                                            <ExternalLink className="size-3 opacity-0 group-hover:opacity-100 transition-opacity text-neutral-400" />
                                                        </Link>
                                                        {profile.bio && (
                                                            <span className="text-xs text-neutral-400 truncate max-w-[200px]">
                                                                {profile.bio}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                {profile.platform === 'youtube' ? (
                                                    <Badge variant="outline" className="bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/30 gap-1.5 capitalize font-bold text-xs">
                                                        <Youtube className="size-3.5 text-rose-500 shrink-0" />
                                                        YouTube
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" className="bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/30 gap-1.5 capitalize font-bold text-xs">
                                                        <Instagram className="size-3.5 text-purple-500 shrink-0" />
                                                        Instagram
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right font-semibold font-mono">
                                                {profile.status === 'fetched' ? formatNumber(profile.followers_count) : '-'}
                                            </td>
                                            <td className="px-6 py-4 text-right font-medium text-neutral-500 dark:text-neutral-400 font-mono">
                                                {profile.status === 'fetched' ? formatNumber(profile.posts_count) : '-'}
                                            </td>
                                            <td className="px-6 py-4">
                                                {getStatusBadge(profile.status)}
                                                {profile.status === 'failed' && profile.error_message && (
                                                    <div className="text-[10px] text-rose-500 max-w-[150px] truncate mt-1" title={profile.error_message}>
                                                        {profile.error_message}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                                                {formatDate(profile.last_refreshed_at)}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <div className="flex items-center justify-center gap-2">
                                                    <Link href={`/watchlist/${profile.id}`}>
                                                        <Button variant="outline" size="sm" className="h-8 text-xs font-semibold px-3">
                                                            Details
                                                        </Button>
                                                    </Link>
                                                    <Button 
                                                        variant="ghost" 
                                                        size="icon" 
                                                        className="h-8 w-8 text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                                                        onClick={() => handleDelete(profile.id, profile.username)}
                                                        title="Remove from Watchlist"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            )}
                        </table>
                        {profiles.data.length === 0 && (
                            <div className="text-center py-12 text-neutral-400 space-y-2">
                                <HelpCircle className="size-8 mx-auto text-neutral-300 dark:text-neutral-700" />
                                <div className="font-semibold text-neutral-600 dark:text-neutral-400">No profiles found</div>
                                <div className="text-xs">Add a handle or adjust filters to search.</div>
                            </div>
                        )}
                        </div>
                    </div>

                {/* Pagination */}
                {profiles.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-neutral-100 dark:border-neutral-900 pt-4 px-1">
                        <p className="text-xs font-medium text-neutral-500">
                            Showing page <span className="font-bold">{profiles.current_page}</span> of <span className="font-bold">{profiles.last_page}</span>
                        </p>
                        <div className="flex gap-1.5">
                            {profiles.links.map((link, idx) => {
                                // Clean up label characters e.g. &laquo; Previous
                                const label = link.label
                                    .replace('&laquo;', '«')
                                    .replace('&raquo;', '»')
                                    .replace('Previous', 'Prev')
                                    .replace('Next', 'Next');

                                return link.url ? (
                                    <Link 
                                        key={idx} 
                                        href={link.url}
                                        preserveState
                                    >
                                        <Button
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                            className={`h-8 min-w-[32px] text-xs font-semibold ${
                                                link.active ? '' : 'text-neutral-600 dark:text-neutral-400'
                                            }`}
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
Index.layout = {
    breadcrumbs: [
        {
            title: 'Watchlist',
            href: '/watchlist',
        },
    ],
};
