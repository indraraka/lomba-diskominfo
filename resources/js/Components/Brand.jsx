import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Brand({ href, className = 'brand', value }) {
    const sharedBranding = usePage().props.branding || {};
    const branding = value || sharedBranding;
    const [imageFailed, setImageFailed] = useState(false);
    const name = branding.brand_name || 'Lomba Diskominfo';

    useEffect(() => {
        setImageFailed(false);
        const rootStyle = document.documentElement.style;
        rootStyle.setProperty('--red', branding.primary_color || '#0067A5');
        rootStyle.setProperty('--red-dark', branding.primary_dark_color || '#00476F');
        rootStyle.setProperty('--gold', branding.accent_color || '#E5B52A');
    }, [branding.logo_url, branding.primary_color, branding.primary_dark_color, branding.accent_color]);

    const content = <>
        <span className="brand-mark">
            {branding.logo_url && !imageFailed
                ? <img src={branding.logo_url} alt={`Logo ${name}`} onError={() => setImageFailed(true)} />
                : <b>LI</b>}
        </span>
        <span className="brand-name">{name}</span>
    </>;

    return href
        ? <Link href={href} className={className}>{content}</Link>
        : <div className={className}>{content}</div>;
}
