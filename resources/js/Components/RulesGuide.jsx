import {
    AlertTriangle,
    CheckCircle2,
    ClipboardCheck,
    PackageOpen,
    PlayCircle,
    RefreshCw,
    ShieldCheck,
    Trophy,
    UsersRound,
} from 'lucide-react';

const sectionDefinitions = [
    { key: 'equipment', title: 'Perlengkapan', eyebrow: 'Yang harus disiapkan', icon: PackageOpen, tone: 'blue' },
    { key: 'setup', title: 'Posisi & persiapan', eyebrow: 'Sebelum aba-aba mulai', icon: UsersRound, tone: 'slate' },
    { key: 'play', title: 'Cara bermain', eyebrow: 'Urutan pelaksanaan', icon: PlayCircle, tone: 'primary', ordered: true, featured: true },
    { key: 'scoring', title: 'Penilaian & pemenang', eyebrow: 'Dasar keputusan juri', icon: Trophy, tone: 'gold' },
    { key: 'contingencies', title: 'Kondisi khusus', eyebrow: 'Jika terjadi kendala atau seri', icon: RefreshCw, tone: 'violet' },
    { key: 'violations', title: 'Larangan & pelanggaran', eyebrow: 'Hal yang tidak diperbolehkan', icon: AlertTriangle, tone: 'red' },
    { key: 'safety', title: 'Keselamatan', eyebrow: 'Wajib diperhatikan', icon: ShieldCheck, tone: 'green' },
];

function RuleSection({ definition, items }) {
    const Icon = definition.icon;
    const List = definition.ordered ? 'ol' : 'ul';

    return <section className={`rule-card rule-card-${definition.tone} ${definition.featured ? 'rule-card-featured' : ''}`}>
        <header className="rule-card-header">
            <span className="rule-card-icon"><Icon size={20}/></span>
            <div>
                <span>{definition.eyebrow}</span>
                <h3>{definition.title}</h3>
            </div>
        </header>
        <List className="rich-rule-list">
            {items.map((item, index) => <li key={`${definition.key}-${index}`}>
                {!definition.ordered && <CheckCircle2 size={16} aria-hidden="true"/>}
                <span>{item}</span>
            </li>)}
        </List>
    </section>;
}

export default function RulesGuide({ rules }) {
    const sections = sectionDefinitions.filter(({ key }) => Array.isArray(rules?.[key]) && rules[key].length);

    if (!sections.length) return null;

    return <div className="rules-guide">
        <div className="rules-guide-heading">
            <span className="rules-guide-seal"><ClipboardCheck size={23}/></span>
            <div>
                <p>Pedoman resmi perlombaan</p>
                <h2>Aturan main yang perlu dipahami</h2>
                <span>Urutan di bawah diringkas dari dokumen panitia tanpa mengubah ketentuan.</span>
            </div>
        </div>
        <div className="rule-card-grid">
            {sections.map(definition => <RuleSection
                key={definition.key}
                definition={definition}
                items={rules[definition.key]}
            />)}
        </div>
        <p className="rules-source"><ClipboardCheck size={15}/> Sumber: {rules.source || 'Dokumen peraturan panitia'}</p>
    </div>;
}
