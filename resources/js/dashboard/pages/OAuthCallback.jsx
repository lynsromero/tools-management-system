import { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function OAuthCallback() {
    const { setSession } = useAuth();
    const [searchParams] = useSearchParams();

    useEffect(() => {
        const token = searchParams.get('token');
        const email = searchParams.get('email');

        if (!token) {
            window.location.replace('/login?oauth_error=oauth_failed');
            return;
        }

        const name = email ? email.split('@')[0] : 'User';
        setSession(token, { name, email: email ?? '', avatar_url: null });
        window.location.replace('/dashboard');
    }, [searchParams, setSession]);

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-900 px-4">
            <div className="flex flex-col items-center gap-4 rounded-2xl bg-white p-8 shadow-2xl sm:p-10 text-center">
                <div className="h-10 w-10 animate-spin rounded-full border-4 border-indigo-600 border-t-transparent"></div>
                <div>
                    <h2 className="text-base font-semibold text-slate-900">Completing sign in…</h2>
                    <p className="text-sm text-slate-500">Redirecting to your dashboard</p>
                </div>
            </div>
        </div>
    );
}