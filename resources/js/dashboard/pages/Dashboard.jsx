import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';

const STATUS_STYLES = {
    active: 'bg-green-50 text-green-700 ring-green-600/20',
    expired: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    refunded: 'bg-red-50 text-red-700 ring-red-600/20',
    payment_failed: 'bg-rose-50 text-rose-700 ring-rose-600/20',
};

function StatCard({ label, value, icon, colors }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex items-center gap-4">
                <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${colors}`}>
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                        {icon}
                    </svg>
                </div>
                <div>
                    <p className="text-sm font-medium text-slate-500">{label}</p>
                    <p className="mt-0.5 text-2xl font-semibold text-slate-900">{value}</p>
                </div>
            </div>
        </div>
    );
}

function StatusBadge({ status }) {
    const classes = STATUS_STYLES[status] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20';

    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ring-1 ring-inset ${classes}`}>
            {status.replace('_', ' ')}
        </span>
    );
}

function formatAmount(value) {
    return `$${Number(value).toFixed(2)}`;
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Dashboard() {
    const { user } = useAuth();
    const isAdmin = user?.role === 'admin' || user?.role === 'super_admin';
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const loadStats = useCallback(async () => {
        setLoading(true);

        try {
            if (isAdmin) {
                const { data } = await api.get('/admin/dashboard/stats');
                setStats(data.data ?? {});
            } else {
                const [tools, credits] = await Promise.all([
                    api.get('/tools'),
                    api.get('/me/credits'),
                ]);

                setStats({
                    available_tools: (tools.data.data ?? []).length,
                    credit_balance: credits.data.balance ?? 0,
                });
            }
            setError('');
        } catch {
            setError('Failed to load dashboard stats.');
        } finally {
            setLoading(false);
        }
    }, [isAdmin]);

    useEffect(() => {
        loadStats();
    }, [loadStats]);

    if (loading) {
        return <div className="rounded-xl border border-slate-200 bg-white p-16 text-center text-sm text-slate-400">Loading dashboard…</div>;
    }

    if (error || !stats) {
        return (
            <div>
                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
                <button
                    type="button"
                    onClick={loadStats}
                    className="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    Retry
                </button>
            </div>
        );
    }

    if (!isAdmin) {
        return (
            <div className="space-y-6">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:flex sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">Welcome back, {user?.name ?? 'there'}</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Browse available tools, manage your account, and track your referral credits.
                        </p>
                    </div>
                    <div className="mt-4 flex gap-3 sm:mt-0">
                        <Link
                            to="/store"
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700"
                        >
                            Browse store
                        </Link>
                        <Link
                            to="/settings"
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Settings
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <StatCard
                        label="Available tools"
                        value={stats?.available_tools ?? 0}
                        colors="bg-emerald-50 text-emerald-600"
                        icon={<path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />}
                    />
                    <StatCard
                        label="Credit balance"
                        value={formatAmount(stats?.credit_balance ?? 0)}
                        colors="bg-indigo-50 text-indigo-600"
                        icon={<path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />}
                    />
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Total revenue"
                    value={formatAmount(stats?.total_revenue ?? 0)}
                    colors="bg-indigo-50 text-indigo-600"
                    icon={<path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />}
                />
                <StatCard
                    label="Active subscriptions"
                    value={stats?.active_subscriptions ?? 0}
                    colors="bg-violet-50 text-violet-600"
                    icon={<path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />}
                />
                <StatCard
                    label="Total tools"
                    value={stats?.total_tools ?? 0}
                    colors="bg-emerald-50 text-emerald-600"
                    icon={<path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />}
                />
                <StatCard
                    label="Total users"
                    value={stats?.total_users ?? 0}
                    colors="bg-sky-50 text-sky-600"
                    icon={<path strokeLinecap="round" strokeLinejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />}
                />
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 className="text-sm font-semibold text-slate-900">Recent purchases</h2>
                    <Link to="/tools" className="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                        Manage tools →
                    </Link>
                </div>

                {stats?.recent_purchases?.length ? (
                    <table className="min-w-full divide-y divide-slate-200">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Customer</th>
                                <th className="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Tool</th>
                                <th className="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Amount</th>
                                <th className="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Status</th>
                                <th className="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {(stats.recent_purchases ?? []).map((purchase) => (
                                <tr key={purchase.id} className="transition hover:bg-slate-50">
                                    <td className="px-6 py-3.5">
                                        <p className="text-sm font-medium text-slate-900">{purchase.user_name}</p>
                                        <p className="text-xs text-slate-500">{purchase.user_email}</p>
                                    </td>
                                    <td className="px-6 py-3.5 text-sm text-slate-600">{purchase.tool_name}</td>
                                    <td className="px-6 py-3.5 text-sm font-medium text-slate-900">{formatAmount(purchase.amount)}</td>
                                    <td className="px-6 py-3.5"><StatusBadge status={purchase.status} /></td>
                                    <td className="px-6 py-3.5 text-sm text-slate-600">{formatDate(purchase.created_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <div className="p-10 text-center text-sm text-slate-400">No purchases yet.</div>
                )}
            </div>
        </div>
    );
}