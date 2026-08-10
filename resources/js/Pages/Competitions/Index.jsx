import AppShell from '@/Components/AppShell';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Archive, ArrowRight, CalendarDays, Clock3, Plus, Radio, RotateCcw, Users } from 'lucide-react';

export default function Index({ competitions }) {
    const flash = usePage().props.flash;
    const active = competitions.filter(c => c.status === 'active');
    return <AppShell title="Semua lomba" eyebrow="PUSAT KENDALI" actions={<Link href={route('competitions.create')} className="btn btn-primary"><Plus size={18}/> Buat lomba</Link>}>
        <Head title="Dashboard" />
        {flash?.success && <div className="flash">{flash.success}</div>}
        <section className="stat-grid">
            <div className="stat-card"><span className="stat-icon red"><CalendarDays/></span><div><strong>{active.length}</strong><span>Lomba aktif</span></div></div>
            <div className="stat-card"><span className="stat-icon blue"><Clock3/></span><div><strong>{competitions.reduce((n,c)=>n+c.sessions_count,0)}</strong><span>Total sesi</span></div></div>
            <div className="stat-card"><span className="stat-icon gold"><Radio/></span><div><strong>{active.filter(c=>c.type==='quiz').length}</strong><span>Cerdas cermat</span></div></div>
        </section>
        {competitions.length === 0 ? <section className="empty-state"><div className="empty-icon"><Users/></div><h2>Belum ada lomba</h2><p>Buat lomba pertama, atur waktunya, lalu undang peserta.</p><Link className="btn btn-primary" href={route('competitions.create')}><Plus size={18}/> Buat lomba pertama</Link></section> :
        <section className="card-grid">
            {competitions.map(c => <article className={`competition-card ${c.status}`} key={c.id}>
                <div className="card-topline"><span className={`type-badge ${c.type}`}>{c.type === 'quiz' ? <><Radio size={14}/> Cerdas cermat</> : <><Clock3 size={14}/> Lomba umum</>}</span><span className={`status-dot ${c.status}`}>{c.status === 'active' ? 'Aktif' : 'Arsip'}</span></div>
                <h2>{c.name}</h2><p>{c.description || 'Belum ada deskripsi untuk lomba ini.'}</p>
                <div className="card-meta"><span>{c.sessions_count} sesi</span>{c.type === 'general' && <span>{c.schedule_text || 'Jadwal kustom'} · {({ judged_rubric: 'Penjurian', metric_race: 'Waktu/sisa', multi_round_score: 'Multisesi', elimination: 'Eliminasi', knockout_score: 'Sistem gugur' })[c.workflow_key] || (c.timer_mode === 'individual' ? 'Per peserta' : 'Serentak')}</span>}</div>
                <div className="card-actions"><Link href={route('competitions.show', c.id)} className="btn btn-primary">Buka <ArrowRight size={16}/></Link><Link href={route('competitions.edit', c.id)} className="btn btn-ghost">Edit</Link><button className="icon-button" title={c.status==='active'?'Arsipkan':'Aktifkan'} onClick={()=>router.delete(route('competitions.destroy',c.id))}>{c.status==='active'?<Archive size={17}/>:<RotateCcw size={17}/>}</button></div>
            </article>)}
        </section>}
    </AppShell>;
}
