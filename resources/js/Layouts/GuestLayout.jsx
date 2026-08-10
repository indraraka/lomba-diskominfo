import Brand from '@/Components/Brand';

export default function GuestLayout({ children }) {
    return <div className="auth-page"><Brand href="/" /><div className="auth-card">{children}</div><p className="auth-foot">Sistem lomba Dinas Komunikasi dan Informatika Kabupaten Indramayu.</p></div>;
}
