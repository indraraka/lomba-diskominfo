import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, LockKeyhole } from 'lucide-react';

export default function Login({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '', remember: true });
    const submit=e=>{e.preventDefault();post(route('login'));};
    return <GuestLayout><Head title="Masuk panitia"/><div className="auth-icon"><LockKeyhole/></div><p className="eyebrow">AREA PANITIA</p><h1>Selamat datang kembali.</h1><p className="auth-copy">Masuk untuk menyiapkan dan menjalankan seluruh lomba.</p>{status&&<div className="flash">{status}</div>}<form onSubmit={submit}><label className="field"><span>Email admin</span><input type="email" value={data.email} onChange={e=>setData('email',e.target.value)} autoComplete="username" autoFocus placeholder="admin@lomba.test"/>{errors.email&&<small>{errors.email}</small>}</label><label className="field"><span>Password</span><input type="password" value={data.password} onChange={e=>setData('password',e.target.value)} autoComplete="current-password" placeholder="Masukkan password"/>{errors.password&&<small>{errors.password}</small>}</label><label className="remember"><input type="checkbox" checked={data.remember} onChange={e=>setData('remember',e.target.checked)}/> Tetap masuk di perangkat ini</label><button className="btn btn-primary btn-large full" disabled={processing}>Masuk ke dashboard <ArrowRight size={18}/></button></form></GuestLayout>;
}
