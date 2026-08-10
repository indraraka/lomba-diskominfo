import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Lomba Diskominfo';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const branding = props.initialPage.props.branding || {};
        const rootStyle = document.documentElement.style;
        rootStyle.setProperty('--red', branding.primary_color || '#0067A5');
        rootStyle.setProperty('--red-dark', branding.primary_dark_color || '#00476F');
        rootStyle.setProperty('--gold', branding.accent_color || '#E5B52A');
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
