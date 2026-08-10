import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Clock3, Radio, Trophy } from 'lucide-react';
import Brand from '@/Components/Brand';

export default function Welcome() {
    const user = usePage().props.auth?.user;
    return <>
        <Head title="Tools Lomba 17 Agustus" />
        <div className="landing">
            <header className="landing-nav">
                <Brand />
                <Link className="btn btn-dark" href={user ? '/dashboard' : '/login'}>{user ? 'Buka dashboard' : 'Masuk panitia'} <ArrowRight size={17}/></Link>
            </header>
            <main className="hero">
                <div className="hero-copy">
                    <p className="eyebrow">TOOLS LOMBA KEMERDEKAAN</p>
                    <h1>Satu komando untuk lomba yang <em>lebih seru.</em></h1>
                    <p>Atur peserta, jalankan countdown, rebut buzzer, dan hitung skor—semuanya terasa cepat, jelas, dan siap dipakai di lapangan.</p>
                    <Link className="btn btn-primary btn-large" href={user ? '/dashboard' : '/login'}>Mulai mengatur lomba <ArrowRight size={19}/></Link>
                </div>
                <div className="hero-visual">
                    <div className="sunburst" />
                    <div className="hero-card timer-card"><span>Balap karung</span><strong>00:47</strong><small>Waktu tersisa</small></div>
                    <div className="hero-card buzzer-card"><div className="pulse-dot"/><strong>GRUP MERAH</strong><span>Menekan paling cepat!</span></div>
                    <div className="ribbon">MERDEKA!</div>
                </div>
            </main>
            <section className="feature-strip">
                <article><Clock3/><div><strong>Countdown presisi</strong><span>Individual atau serentak</span></div></article>
                <article><Radio/><div><strong>Buzzer real-time</strong><span>Satu pemenang, tanpa debat</span></div></article>
                <article><Trophy/><div><strong>Skor langsung</strong><span>Papan nilai selalu terkini</span></div></article>
            </section>
        </div>
    </>;
}
