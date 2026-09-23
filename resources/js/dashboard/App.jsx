import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Layout from './components/Layout';
import Login from './pages/Login';
import Register from './pages/Register';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import Dashboard from './pages/Dashboard';
import Tools from './pages/Tools';
import ToolDetail from './pages/ToolDetail';
import Users from './pages/Users';
import Analytics from './pages/Analytics';
import Store from './pages/Store';
import Settings from './pages/Settings';
import OAuthCallback from './pages/OAuthCallback';

export default function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <Routes>
                    <Route path="/login" element={<Login />} />
                    <Route path="/register" element={<Register />} />
                    <Route path="/forgot-password" element={<ForgotPassword />} />
                    <Route path="/reset-password" element={<ResetPassword />} />
                    <Route path="/store" element={<Store />} />
                    <Route path="/oauth/callback" element={<OAuthCallback />} />
                    <Route path="/auth/google/callback" element={<OAuthCallback />} />
                    <Route path="/auth/github/callback" element={<OAuthCallback />} />

                    <Route element={<ProtectedRoute />}>
                        <Route element={<Layout />}>
                            <Route path="/dashboard" element={<Dashboard />} />
                            <Route path="/tools" element={<Tools />} />
                            <Route path="/settings" element={<Settings />} />

                            <Route element={<ProtectedRoute roles={['admin', 'super_admin']} />}>
                                <Route path="/tools/:id" element={<ToolDetail />} />
                                <Route path="/users" element={<Users />} />
                                <Route path="/revenue" element={<Analytics />} />
                            </Route>

                            <Route path="/" element={<Navigate to="/dashboard" replace />} />
                        </Route>
                    </Route>

                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </AuthProvider>
        </BrowserRouter>
    );
}