import AppShell from '@/Components/AppShell';
import Brand from '@/Components/Brand';
import { Head, useForm } from '@inertiajs/react';
import { ImageUp, Palette, RotateCcw, Save } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Edit({ settings }) {
    const { data, setData, post, processing, errors } = useForm({ ...settings, logo: null });
    const [logoPreview, setLogoPreview] = useState(null);
    useEffect(() => () => {
        if (logoPreview) URL.revokeObjectURL(logoPreview);
    }, [logoPreview]);
    const submit = event => {
        event.preventDefault();
        post(route('settings.update'), { forceFormData: true, preserveScroll: true });
    };
    const chooseLogo = event => {
        const file = event.target.files?.[0] || null;
        setData('logo', file);
        setLogoPreview(file ? URL.createObjectURL(file) : null);
    };
    const useDefaultLogo = () => {
        setData(previous => ({ ...previous, logo: null, logo_url: '/images/logo-indramayu.png' }));
        setLogoPreview(null);
    };
    const previewStyle = {
        '--red': data.primary_color,
        '--red-dark': data.primary_dark_color,
        '--gold': data.accent_color,
    };

    return <AppShell title="Identitas aplikasi" eyebrow="PENGATURAN ADMIN">
        <Head title="Pengaturan Identitas" />
        <form className="settings-layout" onSubmit={submit}>
            <section className="panel form-panel">
                <div className="section-title"><span><Palette size={20}/></span><div><h2>Logo dan nama</h2><p>Ditampilkan pada dashboard, halaman peserta, dan layar publik.</p></div></div>
                <label className="field full"><span>Nama aplikasi</span><input value={data.brand_name} onChange={e => setData('brand_name', e.target.value)} maxLength="60" />{errors.brand_name && <small>{errors.brand_name}</small>}</label>
                <div className="field full"><span>Logo aplikasi</span><div className="logo-picker"><img src={logoPreview || data.logo_url || '/images/logo-indramayu.png'} alt="Pratinjau logo yang dipilih"/><div><strong>{data.logo?.name || 'Logo yang sedang digunakan'}</strong><p>PNG, JPG, atau WEBP · maksimal 2 MB</p><div className="logo-picker-actions"><label className="btn btn-outline" htmlFor="brand-logo"><ImageUp size={17}/> Pilih gambar</label><button type="button" className="btn btn-ghost" onClick={useDefaultLogo}><RotateCcw size={16}/> Logo bawaan</button></div></div><input id="brand-logo" className="file-picker-input" type="file" accept="image/png,image/jpeg,image/webp" onChange={chooseLogo}/></div>{errors.logo && <small>{errors.logo}</small>}{errors.logo_url && <small>{errors.logo_url}</small>}</div>
                <div className="color-settings">
                    <ColorField label="Warna utama" value={data.primary_color} error={errors.primary_color} onChange={value => setData('primary_color', value)} />
                    <ColorField label="Warna gelap" value={data.primary_dark_color} error={errors.primary_dark_color} onChange={value => setData('primary_dark_color', value)} />
                    <ColorField label="Warna aksen" value={data.accent_color} error={errors.accent_color} onChange={value => setData('accent_color', value)} />
                </div>
                <button className="btn btn-primary" disabled={processing}><Save size={18}/>{processing ? 'Menyimpan...' : 'Simpan pengaturan'}</button>
            </section>
            <aside className="panel branding-preview" style={previewStyle}>
                <p className="eyebrow">PRATINJAU</p>
                <Brand value={{ ...data, logo_url: logoPreview || data.logo_url }} />
                <h2>Semua lomba</h2>
                <p>Warna tombol, penanda, dan identitas aplikasi mengikuti pengaturan ini.</p>
                <button type="button" className="btn btn-primary">Contoh tombol</button>
                <span className="preview-accent">AKSEN</span>
            </aside>
        </form>
    </AppShell>;
}

function ColorField({ label, value, error, onChange }) {
    const validColor = /^#[0-9A-Fa-f]{6}$/.test(value) ? value : '#000000';
    return <label className="field"><span>{label}</span><div className="color-input"><input type="color" value={validColor} onChange={e => onChange(e.target.value.toUpperCase())}/><input value={value} onChange={e => onChange(e.target.value)} maxLength="7" /></div>{error && <small>{error}</small>}</label>;
}
