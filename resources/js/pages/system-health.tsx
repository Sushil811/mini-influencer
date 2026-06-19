import { Head, useForm } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { 
    Activity, 
    Zap, 
    Clock, 
    Gauge, 
    RefreshCw, 
    ShieldCheck, 
    Fingerprint, 
    Send, 
    AlertTriangle,
    Info,
    Instagram,
    Youtube
} from 'lucide-react';

interface SystemHealthProps {
    cb: {
        state: 'CLOSED' | 'OPEN' | 'HALF_OPEN';
        failures: number;
        cooldown_remaining: number;
        threshold: number;
        cooldown: number;
    };
    limiter: {
        tokens_remaining: number;
        capacity: number;
        refill_rate: number;
    };
    quota: {
        consumed: number;
        limit: number;
        safety_margin: number;
    };
    webhook: {
        endpoint: string;
        secret: string;
        cached_nonces: number;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

export default function SystemHealth({ cb, limiter, quota, webhook, flash }: SystemHealthProps) {
    // Webhook simulation form
    const webhookForm = useForm({
        username: '',
        platform: 'instagram',
    });

    // Circuit Breaker reset form
    const resetForm = useForm({});

    const handleWebhookSimulate = (e: React.FormEvent) => {
        e.preventDefault();
        webhookForm.post('/system-health/simulate-webhook', {
            onSuccess: () => webhookForm.reset(),
        });
    };

    const handleResetCb = (e: React.FormEvent) => {
        e.preventDefault();
        resetForm.post('/system-health/reset-cb');
    };

    const getCbBadge = (state: string) => {
        switch (state) {
            case 'CLOSED':
                return <Badge className="bg-emerald-500/10 text-emerald-500 border-emerald-500/30 gap-1.5" variant="outline"><ShieldCheck className="size-3.5" /> CLOSED (Healthy)</Badge>;
            case 'OPEN':
                return <Badge className="bg-rose-500/10 text-rose-500 border-rose-500/30 gap-1.5 animate-pulse" variant="outline"><AlertTriangle className="size-3.5" /> OPEN (Tripped)</Badge>;
            case 'HALF_OPEN':
                return <Badge className="bg-amber-500/10 text-amber-500 border-amber-500/30 gap-1.5" variant="outline"><Activity className="size-3.5" /> HALF-OPEN (Testing)</Badge>;
            default:
                return <Badge>{state}</Badge>;
        }
    };

    return (
        <>
            <Head title="System Health & API limits" />
            <div className="space-y-6 p-6 max-w-7xl mx-auto">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-extrabold tracking-tight text-neutral-900 dark:text-neutral-50">
                        System Health & API Limits
                    </h1>
                    <p className="text-neutral-500 dark:text-neutral-400 mt-1">
                        Monitor active Redis concurrency controls, rate limits, and test webhook integration inputs.
                    </p>
                </div>

                {/* Flash Messages */}
                {flash?.success && (
                    <div className="p-4 rounded-lg bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-sm font-semibold flex items-center gap-2">
                        <ShieldCheck className="size-5 shrink-0" />
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="p-4 rounded-lg bg-rose-500/10 text-rose-500 border border-rose-500/20 text-sm font-semibold flex items-center gap-2">
                        <AlertTriangle className="size-5 shrink-0" />
                        {flash.error}
                    </div>
                )}

                {/* Main Grid */}
                <div className="grid gap-6 md:grid-cols-2">
                    
                    {/* Circuit Breaker Status Card */}
                    <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-lg font-bold">Redis Circuit Breaker</CardTitle>
                                <CardDescription>Protects third-party APIs from consecutive failure overload</CardDescription>
                            </div>
                            <Zap className={`size-5 ${cb.state === 'OPEN' ? 'text-rose-500 animate-bounce' : 'text-neutral-400'}`} />
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex justify-between items-center pb-2 border-b border-neutral-100 dark:border-neutral-900">
                                <span className="text-sm font-semibold text-neutral-500">Current Status</span>
                                <div>{getCbBadge(cb.state)}</div>
                            </div>
                            <div className="flex justify-between items-center pb-2 border-b border-neutral-100 dark:border-neutral-900">
                                <span className="text-sm font-semibold text-neutral-500">Failed Attempts</span>
                                <span className="text-sm font-bold font-mono">{cb.failures} / {cb.threshold}</span>
                            </div>
                            {cb.state === 'OPEN' && (
                                <div className="flex justify-between items-center pb-2 border-b border-neutral-100 dark:border-neutral-900 text-rose-500">
                                    <span className="text-sm font-semibold flex items-center gap-1"><Clock className="size-3.5" /> Cooldown Remaining</span>
                                    <span className="text-sm font-bold font-mono">{cb.cooldown_remaining}s</span>
                                </div>
                            )}

                            <form onSubmit={handleResetCb} className="pt-2">
                                <Button 
                                    type="submit" 
                                    variant="outline" 
                                    size="sm" 
                                    disabled={resetForm.processing} 
                                    className="w-full text-xs font-bold gap-1 border-neutral-200 hover:bg-neutral-50"
                                >
                                    <RefreshCw className={`size-3.5 ${resetForm.processing ? 'animate-spin' : ''}`} />
                                    Force Reset Circuit Breaker (CLOSED)
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Rate Limiting & Daily Quota Limits */}
                    <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-lg font-bold">Token Bucket & Daily Quota</CardTitle>
                                <CardDescription>Redis rate limits and IST calendar-day quota caps</CardDescription>
                            </div>
                            <Gauge className="size-5 text-neutral-400" />
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {/* Token Bucket */}
                            <div>
                                <div className="flex justify-between items-center text-sm mb-1.5">
                                    <span className="font-semibold text-neutral-500">Token Bucket Capacity</span>
                                    <span className="font-bold font-mono">{limiter.tokens_remaining} / {limiter.capacity}</span>
                                </div>
                                <div className="w-full bg-neutral-100 dark:bg-neutral-800 rounded-full h-2 overflow-hidden">
                                    <div 
                                        className="bg-indigo-500 h-2 rounded-full transition-all duration-300" 
                                        style={{ width: `${(limiter.tokens_remaining / limiter.capacity) * 100}%` }} 
                                    />
                                </div>
                                <span className="text-[10px] text-neutral-400 mt-1 block">Refills at {limiter.refill_rate} token/sec. Used to pace API requests.</span>
                            </div>

                            {/* Daily Quota */}
                            <div>
                                <div className="flex justify-between items-center text-sm mb-1.5">
                                    <span className="font-semibold text-neutral-500">Daily Quota Consumed</span>
                                    <span className="font-bold font-mono">{quota.consumed} / {quota.limit}</span>
                                </div>
                                <div className="w-full bg-neutral-100 dark:bg-neutral-800 rounded-full h-2 overflow-hidden">
                                    <div 
                                        className={`h-2 rounded-full transition-all duration-300 ${quota.consumed >= quota.safety_margin ? 'bg-rose-500' : 'bg-emerald-500'}`} 
                                        style={{ width: `${(quota.consumed / quota.limit) * 100}%` }} 
                                    />
                                </div>
                                <div className="flex justify-between text-[10px] text-neutral-400 mt-1">
                                    <span>Ceiling Ceiling: {quota.limit} units</span>
                                    <span className="text-rose-500">Safety margin trips at: {quota.safety_margin} units (90%)</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Webhook Security Status */}
                    <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-lg font-bold">Webhook Integrity Security</CardTitle>
                                <CardDescription>Active parameters verifying webhooks in under 2s</CardDescription>
                            </div>
                            <Fingerprint className="size-5 text-neutral-400" />
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-col gap-1.5 pb-2 border-b border-neutral-100 dark:border-neutral-900">
                                <span className="text-xs font-semibold text-neutral-400">Webhook Endpoint URLs</span>
                                <div className="space-y-1.5 mt-1">
                                    <div className="flex items-center gap-2">
                                        <Instagram className="size-3.5 text-purple-500 shrink-0" />
                                        <code className="text-xs font-mono bg-neutral-50 dark:bg-neutral-900 p-1.5 rounded border truncate select-all flex-1">/api/webhooks/instagram</code>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Youtube className="size-3.5 text-rose-500 shrink-0" />
                                        <code className="text-xs font-mono bg-neutral-50 dark:bg-neutral-900 p-1.5 rounded border truncate select-all flex-1">/api/webhooks/youtube</code>
                                    </div>
                                </div>
                            </div>
                            <div className="flex flex-col gap-1.5 pb-2 border-b border-neutral-100 dark:border-neutral-900">
                                <span className="text-xs font-semibold text-neutral-400">HMAC-SHA256 Secret</span>
                                <code className="text-xs font-mono bg-neutral-50 p-2 rounded border truncate select-all">{webhook.secret}</code>
                            </div>
                            <div className="flex justify-between items-center pt-1">
                                <span className="text-sm font-semibold text-neutral-500">Request IDs Cached (24h replay protect)</span>
                                <span className="text-sm font-bold font-mono bg-neutral-100 dark:bg-neutral-900 px-2 py-0.5 rounded border">{webhook.cached_nonces} nonces</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Interactive Webhook Simulator */}
                    <Card className="border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950 shadow-sm border-indigo-200/60 dark:border-indigo-950 bg-indigo-50/10 dark:bg-indigo-950/10">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-lg font-bold text-indigo-900 dark:text-indigo-100">Webhook Input Simulator</CardTitle>
                                <CardDescription>Trigger a signed HMAC webhook to queue scrapers instantly</CardDescription>
                            </div>
                            <Send className="size-5 text-indigo-500" />
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleWebhookSimulate} className="space-y-4">
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <label className="text-sm font-bold text-neutral-700 dark:text-neutral-300">Platform</label>
                                        <select
                                            value={webhookForm.data.platform}
                                            onChange={(e) => webhookForm.setData('platform', e.target.value)}
                                            className="h-10 w-full rounded-md border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950 px-3 py-1 text-sm font-medium shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                            disabled={webhookForm.processing}
                                        >
                                            <option value="instagram">Instagram</option>
                                            <option value="youtube">YouTube</option>
                                        </select>
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm font-bold text-neutral-700 dark:text-neutral-300">
                                            {webhookForm.data.platform === 'youtube' ? 'YouTube Handle' : 'Instagram Handle'}
                                        </label>
                                        <div className="relative">
                                            <span className="absolute left-3 top-2.5 text-neutral-400 font-medium select-none">@</span>
                                            <Input
                                                type="text"
                                                placeholder={webhookForm.data.platform === 'youtube' ? 'mrbeast' : 'cristiano'}
                                                value={webhookForm.data.username}
                                                onChange={(e) => webhookForm.setData('username', e.target.value)}
                                                className="pl-8 bg-white dark:bg-neutral-950 border-neutral-200"
                                                disabled={webhookForm.processing}
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-start gap-2 text-xs text-indigo-700 dark:text-indigo-300 bg-indigo-100/30 dark:bg-indigo-950/30 p-3 rounded-lg border border-indigo-200/50">
                                    <Info className="size-4 shrink-0 mt-0.5" />
                                    <span>
                                        This simulates a real webhook payload. It signs the body with HMAC-SHA256 using the secret key, generates a fresh `X-Webhook-Request-ID` UUID, and POSTs to `/api/webhooks/{webhookForm.data.platform}`. The system processes it asynchronously.
                                    </span>
                                </div>

                                <Button 
                                    type="submit" 
                                    disabled={webhookForm.processing} 
                                    className="w-full h-10 gap-1.5 font-bold bg-indigo-600 hover:bg-indigo-700 text-white"
                                >
                                    <Send className="size-4" />
                                    {webhookForm.processing ? 'Simulating...' : 'Simulate HMAC Webhook Scrape'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

SystemHealth.layout = {
    breadcrumbs: [
        {
            title: 'System Health & Limits',
            href: '/system-health',
        },
    ],
};
