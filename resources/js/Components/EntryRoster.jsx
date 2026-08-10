import { router } from '@inertiajs/react';
import { Building2, ChevronDown, Pencil, Save, Trash2, UserRound, UsersRound, X } from 'lucide-react';
import { useState } from 'react';

const affiliationOptions = ['TIK', 'SANTIK', 'Sekretariat', 'IKP', 'Magang', 'PKL'];

function EntryCard({ entry, session, team }) {
    const knownAffiliation = affiliationOptions.includes(entry.affiliation);
    const [editing, setEditing] = useState(false);
    const [name, setName] = useState(entry.name);
    const [affiliationOption, setAffiliationOption] = useState(knownAffiliation ? entry.affiliation : entry.affiliation ? 'Lainnya' : '');
    const [customAffiliation, setCustomAffiliation] = useState(knownAffiliation ? '' : entry.affiliation || '');
    const [members, setMembers] = useState((entry.members || []).join('\n'));
    const [error, setError] = useState('');
    const entryRules = session.rules?.entry || {};
    const exact = entryRules.members_min === entryRules.members_max;

    const cancel = () => {
        setName(entry.name);
        setAffiliationOption(knownAffiliation ? entry.affiliation : entry.affiliation ? 'Lainnya' : '');
        setCustomAffiliation(knownAffiliation ? '' : entry.affiliation || '');
        setMembers((entry.members || []).join('\n'));
        setError('');
        setEditing(false);
    };
    const save = event => {
        event.preventDefault();
        const memberList = members.split(/\r?\n|,/).map(value => value.trim()).filter(Boolean);
        const affiliation = affiliationOption === 'Lainnya' ? customAffiliation.trim() : affiliationOption;
        router.patch(route('sessions.participants.update', [session.id, entry.id]), { name: name.trim(), affiliation, members: memberList }, {
            preserveScroll: true,
            onSuccess: () => { setError(''); setEditing(false); },
            onError: errors => setError(Object.values(errors)[0] || 'Data belum dapat disimpan.'),
        });
    };
    const remove = () => {
        const bracketWarning = session.competition.workflow_key === 'knockout_score' && session.matches?.length ? ' Bracket yang sudah dibuat akan direset.' : '';
        if (!window.confirm(`Hapus ${team ? 'regu' : 'peserta'} “${entry.name}”? Data hasilnya juga akan dihapus.${bracketWarning}`)) return;
        router.delete(route('sessions.participants.destroy', [session.id, entry.id]), { preserveScroll: true });
    };

    return <details className="entry-detail" open={editing || undefined}>
        <summary>
            <span className="entry-avatar">{team ? <UsersRound size={17}/> : <UserRound size={17}/>}</span>
            <span><strong>{entry.name}</strong><small>{entry.affiliation || 'Tanpa bidang'} · {entry.members?.length || 0} anggota</small></span>
            <i className={`status-dot ${entry.status === 'finished' ? 'archived' : 'active'}`}>{entry.status || (entry.approved ? 'terverifikasi' : 'menunggu')}</i>
            <ChevronDown size={17}/>
        </summary>
        <div className="entry-detail-body">
            {editing ? <form className="entry-edit-form" onSubmit={save}>
                <label><span>Nama {team ? 'regu' : 'peserta'}</span><input value={name} onChange={event => setName(event.target.value)} maxLength="100" required/></label>
                <label><span>Bidang/afiliasi</span><select value={affiliationOption} onChange={event => { setAffiliationOption(event.target.value); if (event.target.value !== 'Lainnya') setCustomAffiliation(''); }} required><option value="">Pilih bidang/afiliasi</option>{affiliationOptions.map(option => <option value={option} key={option}>{option}</option>)}<option value="Lainnya">Lainnya</option></select></label>
                {affiliationOption === 'Lainnya' && <label><span>Bidang lainnya</span><input value={customAffiliation} onChange={event => setCustomAffiliation(event.target.value)} maxLength="100" required/></label>}
                <label><span>Daftar anggota {exact ? `(wajib ${entryRules.members_min})` : `(${entryRules.members_min || 1}–${entryRules.members_max || 200})`}</span><textarea rows={Math.min(6, Math.max(2, entry.members?.length || 2))} value={members} onChange={event => setMembers(event.target.value)} placeholder="Satu nama per baris" required/></label>
                {error && <p className="entry-edit-error">{error}</p>}
                <div className="entry-edit-actions"><button type="button" className="mini-btn" onClick={cancel}><X size={14}/> Batal</button><button className="mini-btn approve" disabled={!name.trim() || !members.trim() || !affiliationOption || (affiliationOption === 'Lainnya' && !customAffiliation.trim())}><Save size={14}/> Simpan</button></div>
            </form> : <>
                <p><Building2 size={15}/><span>Bidang/afiliasi</span><strong>{entry.affiliation || '—'}</strong></p>
                <div><span>Daftar anggota</span>{entry.members?.length ? <ol>{entry.members.map((member, index) => <li key={`${entry.id}-${index}`}>{member}</li>)}</ol> : <p className="muted">Nama anggota belum dicantumkan.</p>}</div>
                {entry.notes && <div className="entry-note"><span>Catatan</span><p>{entry.notes}</p></div>}
                <div className="entry-manage-actions"><button type="button" className="mini-btn" onClick={() => setEditing(true)}><Pencil size={14}/> Edit</button><button type="button" className="mini-btn danger" onClick={remove}><Trash2 size={14}/> Hapus</button></div>
            </>}
        </div>
    </details>;
}

export default function EntryRoster({ session }) {
    const entries = session.participants || [];
    const team = session.competition.entry_kind === 'team';
    return <section className="panel entry-roster">
        <div className="panel-head">
            <div><h2><UsersRound size={18}/> Detail {team ? 'regu' : 'peserta'}</h2><p>Edit atau hapus data yang terdaftar.</p></div>
            <span>{entries.length}</span>
        </div>
        {entries.length === 0 ? <p className="muted">Belum ada entri.</p> : <div className="entry-detail-list">{entries.map(entry => <EntryCard entry={entry} session={session} team={team} key={entry.id}/>)}</div>}
    </section>;
}
