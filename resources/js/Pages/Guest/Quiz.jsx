import useSessionLive from "@/Hooks/useSessionLive";
import Brand from "@/Components/Brand";
import { csrfToken } from "@/bootstrap";
import { Head } from "@inertiajs/react";
import {
    CheckCircle2,
    Radio,
    RefreshCw,
    ShieldCheck,
    Wifi,
    WifiOff,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";

const affiliationOptions = [
    "TIK",
    "SANTIK",
    "Sekretariat",
    "IKP",
    "Magang",
    "PKL",
    "Lainnya",
];

async function readResponse(response) {
    const contentType = response.headers.get("content-type") || "";

    if (contentType.includes("application/json")) {
        return response.json();
    }

    return {
        message:
            response.status === 429
                ? "Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi."
                : "Server tidak dapat memproses permintaan. Muat ulang halaman lalu coba lagi.",
    };
}

export default function Quiz({ initialSession }) {
    const storageKey = `pestarakyat:${initialSession.code}`;
    const saved = JSON.parse(localStorage.getItem(storageKey) || "null");
    const [token, setToken] = useState(saved?.token || null);
    const [teamId, setTeamId] = useState(saved?.team_id || null);
    const { session, setSession, connected } = useSessionLive(
        initialSession,
        token,
    );
    const [name, setName] = useState("");
    const [affiliationOption, setAffiliationOption] = useState("");
    const [customAffiliation, setCustomAffiliation] = useState("");
    const [members, setMembers] = useState(["", "", ""]);
    const [joinMode, setJoinMode] = useState(initialSession.teams.length ? "existing" : "new");
    const [selectedTeamId, setSelectedTeamId] = useState("");
    const [pin, setPin] = useState("");
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [pressState, setPressState] = useState("idle");
    const [now, setNow] = useState(Date.now());
    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 100);
        return () => clearInterval(timer);
    }, []);
    const team = useMemo(
        () => session.teams.find((t) => t.id === teamId),
        [session.teams, teamId],
    );
    const registeredTeams = useMemo(() => session.teams.filter((item) => item.approved), [session.teams]);
    const selectedTeam = registeredTeams.find((item) => item.id === Number(selectedTeamId));
    const knockout = session.competition.quiz_format === "knockout";
    const matchTeams = [
        session.active_match?.team_a?.id,
        session.active_match?.team_b?.id,
    ].filter(Boolean);
    const inActiveMatch = !knockout || matchTeams.includes(teamId);
    const opponent =
        knockout && inActiveMatch
            ? session.active_match?.team_a?.id === teamId
                ? session.active_match?.team_b
                : session.active_match?.team_a
            : null;
    const opensAt = session.round?.opens_at
        ? new Date(session.round.opens_at).getTime()
        : null;
    const isOpen =
        team?.approved &&
        inActiveMatch &&
        session.round &&
        (session.round.state === "open" ||
            (session.round.state === "ready" && opensAt && now >= opensAt)) &&
        !session.round.winner_team_id &&
        !session.round.eliminated_team_ids.includes(teamId);
    const join = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError("");
        const affiliation =
            affiliationOption === "Lainnya"
                ? customAffiliation.trim()
                : affiliationOption;
        try {
            const response = await fetch(`/r/${session.code}/join`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrfToken(),
                },
                body: JSON.stringify(joinMode === "existing" ? { team_id: Number(selectedTeamId), pin } : { name, affiliation, members, pin }),
            });
            const body = await readResponse(response);
            if (!response.ok)
                throw new Error(body.message || "Tidak dapat bergabung.");
            localStorage.setItem(
                storageKey,
                JSON.stringify({ token: body.token, team_id: body.team_id }),
            );
            setToken(body.token);
            setTeamId(body.team_id);
            setSession(body.session);
        } catch (e) {
            setError(e.message);
        } finally {
            setLoading(false);
        }
    };
    const buzz = async () => {
        if (!isOpen || loading) return;
        setPressState("sending");
        navigator.vibrate?.(35);
        const key = crypto.randomUUID();
        try {
            const response = await fetch(`/r/${session.code}/buzz`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrfToken(),
                    Authorization: `Bearer ${token}`,
                },
                body: JSON.stringify({ idempotency_key: key }),
            });
            const body = await readResponse(response);
            if (!response.ok) {
                throw new Error(body.message || "Buzzer tidak dapat digunakan.");
            }
            if (body.session) setSession(body.session);
            setPressState(body.accepted ? "won" : body.reason || "locked");
        } catch {
            setPressState("offline");
        } finally {
            setTimeout(() => setPressState("idle"), 1800);
        }
    };
    if (!token)
        return (
            <>
                <Head title={`Gabung ${session.competition.name}`} />
                <div className="guest-page">
                    <Brand className="guest-brand" />
                    <main className="join-shell">
                        <section className="join-visual">
                            <p className="eyebrow">CERDAS CERMAT</p>
                            <h1>Siap jadi yang tercepat?</h1>
                            <p>
                                Daftarkan satu regu berisi tepat tiga anggota.
                                Buzzer digunakan pada babak rebutan dan
                                tie-break.
                            </p>
                            <div className="room-code">
                                <span>KODE RUANG</span>
                                <strong>{session.code}</strong>
                            </div>
                        </section>
                        <form className="join-form" onSubmit={join}>
                            <div className="mobile-buzzer-icon">
                                <Radio />
                            </div>
                            <h2>Gabung ke ruang</h2>
                            <p>{session.competition.name}</p>
                            {registeredTeams.length > 0 && <div className="join-mode-switch"><button type="button" className={joinMode === "existing" ? "active" : ""} onClick={() => setJoinMode("existing")}>Regu terdaftar</button><button type="button" className={joinMode === "new" ? "active" : ""} onClick={() => setJoinMode("new")}>Regu baru</button></div>}
                            {joinMode === "existing" ? <>
                                <label className="field"><span>Pilih regu</span><select value={selectedTeamId} onChange={(event) => setSelectedTeamId(event.target.value)} autoFocus><option value="">Pilih regu yang akan memakai perangkat ini</option>{registeredTeams.map((item) => <option value={item.id} key={item.id}>{item.name} · {item.affiliation}</option>)}</select></label>
                                {selectedTeam && <div className="claimed-team"><strong>{selectedTeam.name}</strong><span>{selectedTeam.affiliation}</span><ul>{selectedTeam.members.map((member, index) => <li key={index}>{member}</li>)}</ul></div>}
                            </> : <>
                                <label className="field"><span>Nama regu</span><input value={name} onChange={(e) => setName(e.target.value)} maxLength="80" placeholder="Contoh: Garuda Muda" autoFocus /></label>
                                <label className="field"><span>Bidang/afiliasi</span><select value={affiliationOption} onChange={(e) => { setAffiliationOption(e.target.value); if (e.target.value !== "Lainnya") setCustomAffiliation(""); }}><option value="">Pilih bidang/afiliasi</option>{affiliationOptions.map((option) => <option value={option} key={option}>{option}</option>)}</select></label>
                                {affiliationOption === "Lainnya" && <label className="field"><span>Bidang/afiliasi lainnya</span><input value={customAffiliation} onChange={(e) => setCustomAffiliation(e.target.value)} maxLength="100" placeholder="Tuliskan nama bidang" autoFocus /></label>}
                                {members.map((member, index) => <label className="field" key={index}><span>Anggota {index + 1}</span><input value={member} onChange={(e) => setMembers(members.map((value, i) => i === index ? e.target.value : value))} maxLength="100" /></label>)}
                            </>}
                            <label className="field">
                                <span>PIN 6 digit</span>
                                <input
                                    inputMode="numeric"
                                    pattern="[0-9]{6}"
                                    maxLength="6"
                                    value={pin}
                                    onChange={(e) =>
                                        setPin(
                                            e.target.value.replace(/\D/g, ""),
                                        )
                                    }
                                    placeholder="••••••"
                                />
                            </label>
                            {error && <div className="form-error">{error}</div>}
                            <button
                                className="btn btn-primary btn-large full"
                                disabled={
                                    loading ||
                                    (joinMode === "existing" ? !selectedTeamId : !name.trim() ||
                                    !affiliationOption ||
                                    (affiliationOption === "Lainnya" && !customAffiliation.trim()) ||
                                    members.some((member) => !member.trim())) ||
                                    pin.length !== 6
                                }
                            >
                                {loading ? "Menghubungkan..." : "Masuk ruang"}
                            </button>
                        </form>
                    </main>
                </div>
            </>
        );
    const eliminated = session.round?.eliminated_team_ids.includes(teamId);
    const feedback = {
        sending: "Mengirim...",
        won: "KAMU TERCEPAT!",
        locked: "Terlambat",
        too_early: "Belum dibuka",
        eliminated: "Selesai untuk soal ini",
        not_in_match: "Menunggu pertandingan",
        offline: "Koneksi terputus",
    }[pressState];
    return (
        <>
            <Head title={`Buzzer · ${team?.name || "Grup"}`} />
            <div
                className="buzzer-page"
                style={{ "--team-color": team?.color || "#dc2626" }}
            >
                <header>
                    <div>
                        <span className="team-dot" />
                        <strong>{team?.name || "Memuat grup..."}</strong>
                    </div>
                    <span
                        className={`connection ${connected ? "online" : "offline"}`}
                    >
                        {connected ? <Wifi size={16} /> : <WifiOff size={16} />}{" "}
                        {connected ? "Terhubung" : "Terputus"}
                    </span>
                </header>
                <main>
                    {!team?.approved ? (
                        <div className="waiting-card">
                            <RefreshCw className="spin-slow" />
                            <h1>Menunggu persetujuan</h1>
                            <p>
                                Panitia akan mengaktifkan grupmu sebentar lagi.
                                Tetap di halaman ini.
                            </p>
                        </div>
                    ) : session.state === "finished" ? (
                        <div className="waiting-card">
                            <CheckCircle2 />
                            <h1>Sesi selesai</h1>
                            <p>Skor akhir regumu</p>
                            <strong className="final-score">
                                {team.score}
                            </strong>
                        </div>
                    ) : session.question_number < 10 ? (
                        <div className="waiting-card">
                            <ShieldCheck />
                            <h1>Babak wajib</h1>
                            <p>
                                Jawab menggunakan kartu/papan. Operator akan
                                mencatat hasil semua regu.
                            </p>
                            <strong className="final-score">
                                {team.score}
                            </strong>
                        </div>
                    ) : knockout && !inActiveMatch ? (
                        <div className="waiting-card">
                            <RefreshCw className="spin-slow" />
                            <h1>Menunggu pertandingan</h1>
                            <p>
                                Bracket sedang berjalan. Buzzer akan aktif
                                otomatis ketika giliran grupmu tiba.
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="question-info">
                                <span>
                                    {session.round?.phase === "tiebreak"
                                        ? "TIE-BREAK"
                                        : `SOAL ${session.question_number}`}
                                </span>
                                <strong>{team.score} poin</strong>
                            </div>
                            <button
                                className={`giant-buzzer ${isOpen ? "ready" : ""} ${pressState}`}
                                onPointerDown={buzz}
                                disabled={!isOpen || !connected}
                            >
                                <span className="buzzer-rings" />
                                <Radio />
                                <strong>
                                    {feedback ||
                                        (eliminated
                                            ? "SUDAH MENJAWAB"
                                            : session.round?.winner
                                              ? "TERKUNCI"
                                              : isOpen
                                                ? "TEKAN!"
                                                : opensAt
                                                  ? "BERSIAP"
                                                  : "TUNGGU")}
                                </strong>
                                <small>
                                    {isOpen
                                        ? "Sentuh secepatnya"
                                        : session.round?.winner
                                          ? `${session.round.winner?.name} lebih cepat`
                                          : "Ikuti aba-aba panitia"}
                                </small>
                            </button>
                            <div className="safety-note">
                                <ShieldCheck size={16} /> Pemenang ditentukan
                                otomatis oleh server
                            </div>
                        </>
                    )}
                </main>
            </div>
        </>
    );
}
