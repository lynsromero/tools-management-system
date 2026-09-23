import { useCallback, useEffect, useState } from 'react';
import { api } from '../api/client';
import ToolForm from '../components/ToolForm';
import ToolTable from '../components/ToolTable';
import { useAuth } from '../context/AuthContext';

export default function Tools() {
    const { user } = useAuth();
    const isAdmin = user?.role === 'admin' || user?.role === 'super_admin';
    const [tools, setTools] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [saving, setSaving] = useState(false);

    const loadTools = useCallback(async () => {
        setLoading(true);

        try {
            const { data } = await api.get(isAdmin ? '/admin/tools' : '/tools');
            setTools(data.data ?? []);
            setError('');
        } catch {
            setError('Failed to load tools.');
        } finally {
            setLoading(false);
        }
    }, [isAdmin]);

    useEffect(() => {
        loadTools();
    }, [loadTools]);

    const handleCreate = async (payload) => {
        setSaving(true);

        try {
            await api.post('/admin/tools', payload);
            setShowModal(false);
            await loadTools();
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this tool? This cannot be undone.')) {
            return;
        }

        try {
            await api.delete(`/admin/tools/${id}`);
            await loadTools();
        } catch {
            setError('Failed to delete tool.');
        }
    };

    return (
        <div>
            <div className="flex items-center justify-between">
                <p className="text-sm text-slate-500">
                    {isAdmin ? 'Create, edit, and manage the tools you sell.' : 'Browse the tools available to you.'}
                </p>
                {isAdmin && (
                    <button
                        type="button"
                        onClick={() => setShowModal(true)}
                        className="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        New tool
                    </button>
                )}
            </div>

            {error && (
                <div className="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
            )}

            <div className="mt-6">
                {loading ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-16 text-center text-sm text-slate-400">
                        Loading tools…
                    </div>
                ) : (
                    <ToolTable tools={tools} onDelete={isAdmin ? handleDelete : undefined} />
                )}
            </div>

            {showModal && (
                <div className="fixed inset-0 z-30 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-2xl rounded-2xl bg-white p-8 shadow-2xl">
                        <div className="mb-6 flex items-center justify-between">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">New tool</h2>
                                <p className="text-sm text-slate-500">Fill in the details below to publish a tool.</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                aria-label="Close"
                            >
                                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <ToolForm
                            onSubmit={handleCreate}
                            onCancel={() => setShowModal(false)}
                            isSubmitting={saving}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}