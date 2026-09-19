import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { api } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [token, setToken] = useState(() => localStorage.getItem('token'));
    const [user, setUser] = useState(() => {
        try {
            return JSON.parse(localStorage.getItem('user'));
        } catch {
            return null;
        }
    });

    const logout = useCallback(async () => {
        try {
            await api.post('/logout');
        } catch {
            // token may already be invalid; clear locally regardless
        }

        localStorage.removeItem('token');
        localStorage.removeItem('user');
        setToken(null);
        setUser(null);
    }, []);

    useEffect(() => {
        if (!token) {
            return;
        }

        api.get('/me')
            .then(({ data }) => {
                setUser(data);
                localStorage.setItem('user', JSON.stringify(data));
            })
            .catch((err) => {
                if (err.response?.status === 401) {
                    logout();
                }
            });
    }, [token, logout]);

    const persist = useCallback((token, user) => {
        localStorage.setItem('token', token);
        localStorage.setItem('user', JSON.stringify(user));
        setToken(token);
        setUser(user);
    }, []);

    const login = useCallback(async (credentials) => {
        const { data } = await api.post('/login', credentials);
        persist(data.token, data.user);
    }, [persist]);

    const register = useCallback(async (payload) => {
        const { data } = await api.post('/register', payload);
        persist(data.token, data.user);
    }, [persist]);

    const updateUser = useCallback((user) => {
        localStorage.setItem('user', JSON.stringify(user));
        setUser(user);
    }, []);

    return (
        <AuthContext.Provider
            value={{
                user,
                token,
                isAuthenticated: Boolean(token),
                login,
                register,
                logout,
                updateUser,
                setSession: persist,
            }}
        >
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    return useContext(AuthContext);
}