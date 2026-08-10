import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, LogOut, Settings, UserRound } from 'lucide-react';
import Brand from '@/Components/Brand';

export default function AppShell({ title, eyebrow, actions, children }) {
    const user = usePage().props.auth?.user;
    return (
        <div className="app-shell">
            <header className="topbar">
                <Brand href="/dashboard" />
                <nav className="topnav">
                    <Link href="/dashboard"><LayoutDashboard size={17}/> Dashboard</Link>
                    <Link href={route('settings.edit')}><Settings size={17}/> Pengaturan</Link>
                    <span className="user-pill"><UserRound size={16}/>{user?.name}</span>
                    <Link href={route('logout')} method="post" as="button" className="nav-button"><LogOut size={16}/> Keluar</Link>
                </nav>
            </header>
            <main className="page-wrap">
                <section className="page-heading">
                    <div>{eyebrow && <p className="eyebrow">{eyebrow}</p>}<h1>{title}</h1></div>
                    {actions && <div className="heading-actions">{actions}</div>}
                </section>
                {children}
            </main>
        </div>
    );
}
