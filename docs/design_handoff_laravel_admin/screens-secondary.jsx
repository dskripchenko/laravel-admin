// screens-secondary.jsx — Login, Dashboard, View, Profile, 2FA, Import, Notifications, Field gallery

function LoginScreen() {
  return (
    <div className="auth-page" style={{ position: 'relative' }}>
      <div className="auth-page__corner">
        <Btn variant="ghost" size="sm" icon="moon" />
        <Btn variant="ghost" size="sm">RU <Icon name="chevron-down" size={11} /></Btn>
      </div>
      <div className="card auth-card">
        <div className="auth-card__hd">
          <div className="auth-card__logo">L</div>
          <div className="auth-card__title">Laravel Admin</div>
          <div className="auth-card__sub">Войдите, чтобы продолжить работу</div>
        </div>
        <div className="auth-card__bd">
          <Field label="Email"><Input icon="mail" placeholder="you@company.com" defaultValue="ivan@acme.com" /></Field>
          <Field label="Пароль" error="Неверный email или пароль"><Input icon="lock" iconRight="eye" type="password" defaultValue="••••••••" error /></Field>
          <div className="hstack" style={{ justifyContent: 'space-between' }}>
            <Checkbox checked label="Запомнить меня" />
            <a className="text-xs" style={{ color: 'var(--uid-accent-text)' }}>Забыли пароль?</a>
          </div>
          <Btn variant="primary" size="lg" block>Войти</Btn>
          <div className="text-xs muted" style={{ textAlign: 'center', paddingTop: 4 }}>
            или войдите через <a style={{ color: 'var(--uid-accent-text)' }}>SSO</a>
          </div>
        </div>
      </div>
    </div>
  );
}

function TwoFactor() {
  return (
    <div className="auth-page">
      <div className="card auth-card" style={{ width: 440 }}>
        <div className="auth-card__hd">
          <div className="auth-card__logo" style={{ background: 'var(--uid-accent)' }}><Icon name="shield-check" size={18} /></div>
          <div className="auth-card__title">Двухфакторная проверка</div>
          <div className="auth-card__sub">Введите 6-значный код из приложения-аутентификатора</div>
        </div>
        <div className="auth-card__bd">
          <div className="code-input">
            {['1','2','7','3'].map((d,i) => <input key={i} defaultValue={d} maxLength={1} />)}
            <input maxLength={1} /><input maxLength={1} />
          </div>
          <Btn variant="primary" size="lg" block>Подтвердить</Btn>
          <div className="text-xs muted" style={{ textAlign: 'center' }}>
            Не получается? <a style={{ color: 'var(--uid-accent-text)' }}>Использовать recovery-код</a>
          </div>
        </div>
      </div>
    </div>
  );
}

/* Dashboard */
function Dashboard() {
  return (
    <div className="pg">
      <div className="pg__hd">
        <div className="pg__title-wrap">
          <h1 className="pg__title">Dashboard</h1>
          <div className="pg__count">Аналитика контента · последние 30 дней</div>
        </div>
        <div className="pg__actions">
          <Btn variant="ghost" icon="calendar">За 30 дней <Icon name="chevron-down" size={11} /></Btn>
          <Btn icon="download">Export</Btn>
          <Btn variant="primary" icon="plus">Add widget</Btn>
        </div>
      </div>

      <div className="dash">
        {/* KPI overview row */}
        <div className="card" style={{ gridColumn: 'span 12' }}>
          <div className="kpi-row">
            <div className="kpi-cell kpi"><div className="kpi__label">Total articles</div><div className="kpi__value">1 284</div><div className="kpi__delta kpi__delta--up"><Icon name="arrow-up-right" size={12} /> 12.4% vs прошлый месяц</div></div>
            <div className="kpi-cell kpi"><div className="kpi__label">Page views</div><div className="kpi__value">428K</div><div className="kpi__delta kpi__delta--up"><Icon name="arrow-up-right" size={12} /> 8.1%</div></div>
            <div className="kpi-cell kpi"><div className="kpi__label">Avg read time</div><div className="kpi__value">3:42</div><div className="kpi__delta kpi__delta--down"><Icon name="arrow-down-right" size={12} /> 0.4%</div></div>
            <div className="kpi-cell kpi"><div className="kpi__label">In review</div><div className="kpi__value">23</div><div className="kpi__delta kpi__delta--flat"><Icon name="minus" size={12} /> без изменений</div></div>
          </div>
        </div>

        {/* Bar chart widget */}
        <div className="card widget" style={{ gridColumn: 'span 8' }}>
          <div className="widget__hd">
            <span className="widget__title">Публикации по дням</span>
            <div className="hstack">
              <span className="poll-ind"><Icon name="rotate-cw" size={11} /> обновлено 2 мин назад</span>
              <button className="tb__icon-btn" style={{ width: 24, height: 24 }}><Icon name="more-horizontal" size={13} /></button>
            </div>
          </div>
          <div className="widget__bd">
            <div className="bars">
              {[12,18,8,22,16,28,14,20,32,24,18,26,40,28,22,30,36,42,28,34,38,28,32,40,46,38,30,42,48,52].map((v,i) => (
                <span key={i} style={{ height: `${v*1.6}%` }} />
              ))}
            </div>
            <div className="hstack" style={{ justifyContent: 'space-between', marginTop: 8, fontSize: 11, color: 'var(--uid-text-tertiary)' }}>
              <span>1 апр</span><span>10 апр</span><span>20 апр</span><span>сегодня</span>
            </div>
          </div>
        </div>

        {/* Donut */}
        <div className="card widget" style={{ gridColumn: 'span 4' }}>
          <div className="widget__hd"><span className="widget__title">Распределение статусов</span></div>
          <div className="widget__bd hstack" style={{ gap: 16, alignItems: 'center' }}>
            <svg className="donut" viewBox="0 0 36 36">
              <circle cx="18" cy="18" r="14" fill="none" stroke="var(--uid-surface-base)" strokeWidth="6" />
              <circle cx="18" cy="18" r="14" fill="none" stroke="var(--uid-accent)" strokeWidth="6" strokeDasharray="62 88" transform="rotate(-90 18 18)" strokeLinecap="butt" />
              <circle cx="18" cy="18" r="14" fill="none" stroke="var(--uid-warning)" strokeWidth="6" strokeDasharray="6 82" strokeDashoffset="-62" transform="rotate(-90 18 18)" />
              <circle cx="18" cy="18" r="14" fill="none" stroke="var(--uid-text-tertiary)" strokeWidth="6" strokeDasharray="20 68" strokeDashoffset="-68" transform="rotate(-90 18 18)" />
              <text x="18" y="19" textAnchor="middle" fontFamily="var(--uid-font-family-display)" fontSize="6" fontWeight="700" fill="var(--uid-text-primary)">1284</text>
            </svg>
            <div className="vstack" style={{ flex: 1, gap: 8, fontSize: 12 }}>
              <div className="hstack"><span className="dot" style={{ background: 'var(--uid-accent)' }} /><span>Published</span><span className="spacer" /><b>892</b></div>
              <div className="hstack"><span className="dot" style={{ background: 'var(--uid-warning)' }} /><span>In review</span><span className="spacer" /><b>23</b></div>
              <div className="hstack"><span className="dot" style={{ background: 'var(--uid-text-tertiary)' }} /><span>Draft</span><span className="spacer" /><b>312</b></div>
              <div className="hstack"><span className="dot" style={{ background: 'var(--uid-info)' }} /><span>Archived</span><span className="spacer" /><b>57</b></div>
            </div>
          </div>
        </div>

        {/* Recent table */}
        <div className="card widget" style={{ gridColumn: 'span 8' }}>
          <div className="widget__hd"><span className="widget__title">Последние публикации</span><Btn variant="ghost" size="sm">Все статьи →</Btn></div>
          <div className="widget__bd" style={{ padding: 0 }}>
            <table className="tbl">
              <tbody>
                {[
                  ['Введение в Laravel 12', 'Иван П.', '12 483', '2 мин'],
                  ['Filament vs Nova', 'Анна С.', '8 912', '14 мин'],
                  ['Eloquent Performance', 'Иван П.', '24 102', 'вчера'],
                  ['Pinia store patterns', 'Мария К.', '5 443', 'вчера'],
                ].map((r,i)=>(
                  <tr key={i}>
                    <td><span className="truncate" style={{ maxWidth: 280 }}>{r[0]}</span></td>
                    <td className="muted text-xs">{r[1]}</td>
                    <td className="right">{r[2]}</td>
                    <td className="tertiary text-xs">{r[3]}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Heatmap */}
        <div className="card widget" style={{ gridColumn: 'span 4' }}>
          <div className="widget__hd"><span className="widget__title">Активность по часам</span></div>
          <div className="widget__bd">
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(24, 1fr)', gap: 2 }}>
              {Array.from({length: 7*24}).map((_, i) => {
                const v = Math.random();
                return <div key={i} style={{ aspectRatio: '1', borderRadius: 2, background: `color-mix(in srgb, var(--uid-accent) ${Math.floor(v*80)}%, var(--uid-surface-base))` }} />;
              })}
            </div>
            <div className="hstack" style={{ marginTop: 8, fontSize: 10, color: 'var(--uid-text-tertiary)', justifyContent: 'space-between' }}>
              <span>Пн</span><span>Чт</span><span>Вс</span>
            </div>
          </div>
        </div>

        {/* Gauge */}
        <div className="card widget" style={{ gridColumn: 'span 4' }}>
          <div className="widget__hd"><span className="widget__title">SEO score (avg)</span></div>
          <div className="widget__bd hstack" style={{ alignItems: 'center', justifyContent: 'center', flexDirection: 'column', gap: 8 }}>
            <svg width="160" height="100" viewBox="0 0 160 100">
              <path d="M 20 90 A 60 60 0 0 1 140 90" fill="none" stroke="var(--uid-surface-base)" strokeWidth="14" strokeLinecap="round" />
              <path d="M 20 90 A 60 60 0 0 1 100 30" fill="none" stroke="var(--uid-accent)" strokeWidth="14" strokeLinecap="round" />
              <text x="80" y="76" textAnchor="middle" fontFamily="var(--uid-font-family-display)" fontSize="28" fontWeight="700" fill="var(--uid-text-primary)">78</text>
              <text x="80" y="92" textAnchor="middle" fontSize="10" fill="var(--uid-text-tertiary)">из 100</text>
            </svg>
            <div className="hstack text-xs muted" style={{ gap: 12 }}>
              <span><span className="dot" style={{background:'var(--uid-danger)'}} /> 0–40</span>
              <span><span className="dot" style={{background:'var(--uid-warning)'}} /> 40–70</span>
              <span><span className="dot" style={{background:'var(--uid-success)'}} /> 70–100</span>
            </div>
          </div>
        </div>

        {/* Markdown widget */}
        <div className="card widget" style={{ gridColumn: 'span 4' }}>
          <div className="widget__hd"><span className="widget__title">Заметка команды</span></div>
          <div className="widget__bd">
            <div className="text-sm" style={{ lineHeight: 1.5 }}>
              <b>Релиз 1 мая.</b> Сегодня код-фриз. Все новые статьи попадут в публикацию <span className="uid-code">v2.5.0</span>. Любые срочные правки — через сторим.
            </div>
            <div className="text-xs tertiary" style={{ marginTop: 8 }}>— @anna · 2 ч назад</div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* View / Infolist */
function ResourceView() {
  return (
    <div className="pg">
      <div className="pg__hd">
        <div className="pg__title-wrap">
          <div className="hstack" style={{ gap: 6, fontSize: 12, color: 'var(--uid-text-tertiary)' }}>
            <a className="hstack" style={{ gap: 4 }}><Icon name="arrow-left" size={12} /> Articles</a>
          </div>
          <h1 className="pg__title">Введение в Laravel 12</h1>
          <div className="pg__count"><Badge variant="success" dot>Published</Badge> · A-1284</div>
        </div>
        <div className="pg__actions">
          <Btn icon="edit-3">Редактировать</Btn>
          <Btn icon="more-horizontal" />
        </div>
      </div>
      <div className="form-grid">
        <div className="main">
          <div className="card">
            <div className="card__hd"><div className="card__hd-title">Основные данные</div></div>
            <div className="card__bd vstack" style={{ gap: 14 }}>
              <Entry label="Заголовок">Введение в Laravel 12: что нового в фреймворке</Entry>
              <Entry label="Slug"><span className="mono text-xs">vvedenie-v-laravel-12</span></Entry>
              <Entry label="Категория"><Badge>Backend</Badge></Entry>
              <Entry label="Tags"><span className="hstack" style={{flexWrap:'wrap', gap:4}}>{['laravel','php','backend','release-notes'].map(t=><Badge key={t} variant="accent">#{t}</Badge>)}</span></Entry>
              <Entry label="Краткое описание">В Laravel 12 фокус на DX: новый scheduler, улучшенная типизация Eloquent-коллекций.</Entry>
            </div>
          </div>
          <div className="card">
            <div className="card__hd"><div className="card__hd-title">История изменений</div></div>
            <div className="card__bd">
              <div className="tl">
                {[
                  { ico:'check-circle-2', t:'опубликовал', who:'Иван Петров', when:'2 мин назад', body:'Статус Draft → Published' },
                  { ico:'edit-3', t:'отредактировал', who:'Анна Сидорова', when:'1 ч назад', body:'Изменены 3 поля: title, excerpt, cover' },
                  { ico:'tag', t:'добавил тег', who:'Иван Петров', when:'2 ч назад', body:'release-notes' },
                  { ico:'plus', t:'создал', who:'Иван Петров', when:'3 дня назад', body:null },
                ].map((e,i) => (
                  <div key={i} className="tl__item">
                    <div className="tl__dot"><Icon name={e.ico} size={12} /></div>
                    <div className="tl__h">
                      <Avatar name={e.who} size="sm" />
                      <b>{e.who}</b> <span className="muted">{e.t}</span>
                      <span className="spacer" />
                      <span className="tertiary text-xs">{e.when}</span>
                    </div>
                    {e.body && <div className="tl__b">{e.body}</div>}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
        <div className="side">
          <div className="card">
            <div className="card__hd"><div className="card__hd-title">Метрики</div></div>
            <div className="card__bd vstack" style={{ gap: 12 }}>
              <Entry label="Просмотров">12 483</Entry>
              <Entry label="Среднее время">4:12</Entry>
              <Entry label="Опубликовано">28.04.2026, 14:23</Entry>
              <Entry label="Автор"><span className="hstack" style={{gap:6}}><Avatar name="Иван Петров" size="sm" /> Иван Петров</span></Entry>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
function Entry({ label, children }) {
  return (
    <div>
      <div className="text-xs tertiary" style={{ textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: 500, marginBottom: 4 }}>{label}</div>
      <div className="text-sm">{children}</div>
    </div>
  );
}

/* Profile */
function Profile() {
  return (
    <div className="pg">
      <div className="pg__hd">
        <div className="pg__title-wrap">
          <h1 className="pg__title">Profile</h1>
          <div className="pg__count">Личные данные, безопасность, токены</div>
        </div>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: '200px 1fr', gap: 24, alignItems: 'start' }}>
        <nav className="vstack" style={{ gap: 2 }}>
          {[
            { id:'general', label:'Основное', icon:'user', on: true },
            { id:'security', label:'Безопасность', icon:'shield' },
            { id:'tokens', label:'API токены', icon:'key' },
            { id:'sessions', label:'Сессии', icon:'monitor' },
          ].map(t => (
            <a key={t.id} className={`sb__item ${t.on ? 'is-active' : ''}`} style={{ borderRadius: 6, paddingLeft: 10 }}>
              <Icon name={t.icon} size={14} className="ico" />
              <span>{t.label}</span>
            </a>
          ))}
        </nav>
        <div className="vstack" style={{ gap: 16 }}>
          <div className="card">
            <div className="card__hd"><div className="card__hd-title">Профиль</div></div>
            <div className="card__bd">
              <div className="hstack" style={{ gap: 16, marginBottom: 16 }}>
                <Avatar name="Иван Петров" size="lg" />
                <div className="vstack" style={{ gap: 4 }}>
                  <div style={{ fontWeight: 600 }}>Иван Петров</div>
                  <div className="text-xs tertiary">@ivan.petrov · admin</div>
                </div>
                <div className="spacer" />
                <Btn variant="ghost" size="sm" icon="upload">Заменить</Btn>
              </div>
              <div className="form-cols form-cols--2" style={{ gap: 16 }}>
                <Field label="Имя"><Input defaultValue="Иван Петров" /></Field>
                <Field label="Email"><Input defaultValue="ivan@acme.com" icon="mail" /></Field>
                <Field label="Язык"><Select defaultValue="ru" icon="globe"><option value="ru">Русский</option><option value="en">English</option></Select></Field>
                <Field label="Тема"><Select defaultValue="system" icon="palette"><option>Светлая</option><option>Тёмная</option><option value="system">Как в системе</option></Select></Field>
              </div>
            </div>
          </div>
          <div className="card">
            <div className="card__hd"><div className="card__hd-title">Двухфакторная аутентификация</div><Badge variant="success" dot>Включена</Badge></div>
            <div className="card__bd vstack" style={{ gap: 12 }}>
              <div className="text-sm muted">2FA включена через Google Authenticator. У вас осталось 6 recovery-кодов.</div>
              <div className="hstack">
                <Btn variant="ghost" size="sm" icon="rotate-cw">Перегенерировать коды</Btn>
                <Btn danger size="sm" icon="shield-off">Отключить 2FA</Btn>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* Notification drawer */
function NotificationDrawer({ onClose }) {
  return (
    <>
      <div className="drawer-backdrop" onClick={onClose} />
      <div className="drawer">
        <div className="card__hd">
          <div className="hstack"><div className="card__hd-title">Уведомления</div><Badge variant="danger">3</Badge></div>
          <div className="hstack">
            <Btn variant="ghost" size="sm">Прочитать все</Btn>
            <button className="tb__icon-btn" onClick={onClose}><Icon name="x" size={14} /></button>
          </div>
        </div>
        <div className="form-tabs" style={{ paddingLeft: 16 }}>
          <button className="is-on">Все <Badge>12</Badge></button>
          <button>Непрочитанные <Badge variant="danger">3</Badge></button>
          <button>Прочитанные</button>
        </div>
        <div style={{ flex: 1, overflowY: 'auto' }}>
          {[
            { ico:'check-circle-2', kind:'success', t:'Импорт завершён', b:'Загружено 247 статей. 3 ошибки — посмотреть отчёт.', when:'2 мин назад', unread: true },
            { ico:'message-circle', kind:'info', t:'Новый комментарий к статье', b:'«Введение в Laravel 12» — Анна Сидорова: «Может стоит добавить раздел про…»', when:'14 мин назад', unread: true },
            { ico:'alert-triangle', kind:'warning', t:'Запланированная публикация', b:'Через 1 час будет опубликована статья «Vue 3 + TypeScript».', when:'1 ч назад', unread: true },
            { ico:'user-plus', kind:'info', t:'Новый пользователь', b:'Дмитрий Орлов получил роль Editor.', when:'вчера' },
            { ico:'trash-2', kind:'error', t:'Не удалось опубликовать', b:'A-1271 — отсутствует обложка.', when:'2 дня назад' },
          ].map((n,i) => (
            <div key={i} style={{ padding: '12px 16px', borderBottom: '1px solid var(--uid-border-subtle)', display: 'flex', gap: 10, background: n.unread ? 'color-mix(in srgb, var(--uid-accent) 4%, transparent)' : 'transparent' }}>
              <div style={{ width: 28, height: 28, borderRadius: 8, display: 'grid', placeItems: 'center', background: `var(--uid-${n.kind === 'info' ? 'info' : n.kind}-subtle)`, color: `var(--uid-${n.kind === 'info' ? 'info' : n.kind})`, flex: 'none' }}>
                <Icon name={n.ico} size={14} />
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div className="hstack">
                  <span style={{ fontWeight: n.unread ? 600 : 500, fontSize: 13 }}>{n.t}</span>
                  <span className="spacer" />
                  <span className="text-xs tertiary">{n.when}</span>
                </div>
                <div className="text-xs muted" style={{ marginTop: 2, lineHeight: 1.4 }}>{n.b}</div>
              </div>
              {n.unread && <span className="dot" style={{ background: 'var(--uid-accent)', alignSelf: 'flex-start', marginTop: 6 }} />}
            </div>
          ))}
        </div>
      </div>
    </>
  );
}

/* Import wizard */
function ImportWizard() {
  const [step, setStep] = useState(2);
  const steps = ['Загрузка', 'Сопоставление', 'Предпросмотр', 'Импорт'];
  return (
    <div className="pg" style={{ maxWidth: 1100, margin: '0 auto' }}>
      <div className="pg__hd">
        <div className="pg__title-wrap">
          <h1 className="pg__title">Импорт статей</h1>
          <div className="pg__count">CSV / TSV / XLSX, до 50 МБ</div>
        </div>
        <Btn variant="ghost" icon="x">Закрыть</Btn>
      </div>
      <div className="card">
        <div className="wiz">
          {steps.map((s, i) => (
            <div key={s} className={`wiz__step ${i === step ? 'is-on' : i < step ? 'is-done' : ''}`}>
              <span className="num">{i < step ? <Icon name="check" size={11} /> : i+1}</span>
              <span>{s}</span>
            </div>
          ))}
        </div>
        <div style={{ padding: 24 }}>
          {step === 0 && (
            <div className="drop">
              <Icon name="upload-cloud" size={36} stroke={1.4} />
              <b>Перетащите файл сюда</b>
              <span className="text-xs">или <a style={{color:'var(--uid-accent-text)'}}>выберите на компьютере</a></span>
              <span className="text-xs tertiary">CSV, TSV, XLSX · максимум 50 МБ</span>
            </div>
          )}
          {step === 1 && <MappingMock />}
          {step === 2 && <PreviewMock />}
          {step === 3 && <ProgressMock />}
        </div>
        <div className="hstack" style={{ padding: '12px 16px', borderTop: '1px solid var(--uid-border-subtle)', justifyContent: 'flex-end', gap: 8 }}>
          {step > 0 && <Btn onClick={() => setStep(step - 1)} icon="arrow-left">Назад</Btn>}
          <div className="spacer" />
          {step < 3 ? <Btn variant="primary" onClick={() => setStep(step + 1)} iconRight="arrow-right">Далее</Btn> :
            <Btn variant="primary" icon="check">Завершить</Btn>}
        </div>
      </div>
    </div>
  );
}

function MappingMock() {
  const rows = [
    ['title', 'Title', 'Введение в Laravel 12', true],
    ['slug', 'Slug', 'vvedenie-v-laravel-12', true],
    ['author_email', 'Author', 'ivan@acme.com', true],
    ['cat', 'Category', 'Backend', true],
    ['publish_date', 'Published at', '2026-04-28', false],
    ['extra_col', '— skip —', 'random text', false],
  ];
  return (
    <div className="vstack" style={{ gap: 8 }}>
      <div className="hstack text-xs tertiary" style={{ padding: '0 12px', textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: 600 }}>
        <span style={{ flex: 1 }}>Колонка в файле</span>
        <span style={{ flex: 1 }}>Поле ресурса</span>
        <span style={{ flex: 1 }}>Пример</span>
      </div>
      {rows.map(([src, tgt, sample, ok], i) => (
        <div key={i} className="hstack" style={{ padding: 10, border: '1px solid var(--uid-border-subtle)', borderRadius: 8, background: ok ? 'color-mix(in srgb, var(--uid-success) 5%, transparent)' : 'var(--uid-surface-raised)' }}>
          <div style={{ flex: 1, fontFamily: 'var(--uid-font-family-mono)', fontSize: 12 }}>{src}</div>
          <div style={{ flex: 1 }}><Select defaultValue={tgt} icon={ok ? 'check' : 'minus'}><option>{tgt}</option></Select></div>
          <div style={{ flex: 1, fontSize: 12, color: 'var(--uid-text-secondary)' }}>{sample}</div>
        </div>
      ))}
    </div>
  );
}
function PreviewMock() {
  return (
    <div>
      <div className="hstack" style={{ marginBottom: 12 }}>
        <span className="text-sm">Превью первых 20 строк</span>
        <span className="spacer" />
        <Badge variant="warning"><Icon name="alert-triangle" size={11} /> 3 предупреждения</Badge>
      </div>
      <div className="tbl-wrap tbl-wrap--top">
        <table className="tbl">
          <thead><tr><th>Title</th><th>Slug</th><th>Author</th><th>Category</th><th>Published</th></tr></thead>
          <tbody>
            <tr><td>Введение в Laravel 12</td><td className="mono text-xs">vvedenie-v-laravel-12</td><td>ivan@acme.com</td><td><Badge>Backend</Badge></td><td className="tertiary text-xs">2026-04-28</td></tr>
            <tr><td>Filament vs Nova</td><td className="mono text-xs">filament-vs-nova</td><td>anna@acme.com</td><td><Badge>Backend</Badge></td><td className="tertiary text-xs">2026-04-27</td></tr>
            <tr style={{ background: 'color-mix(in srgb, var(--uid-warning) 8%, transparent)' }}><td>Vue 3 patterns</td><td className="mono text-xs">vue-3-patterns</td><td><span className="hstack" style={{ gap: 4 }}><Icon name="alert-triangle" size={12} className="tertiary" />unknown@acme.com</span></td><td><Badge>Frontend</Badge></td><td className="tertiary text-xs">—</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  );
}
function ProgressMock() {
  return (
    <div className="vstack" style={{ gap: 16 }}>
      <div className="hstack"><span>Импортируется…</span><span className="spacer" /><b className="mono">182 / 247</b></div>
      <div className="prog"><div className="prog__bar" style={{ width: '74%' }} /></div>
      <div className="hstack" style={{ gap: 24 }}>
        <div><div className="text-xs tertiary">Создано</div><div style={{fontFamily:'var(--uid-font-family-display)', fontSize:22, fontWeight:600}}>178</div></div>
        <div><div className="text-xs tertiary">Обновлено</div><div style={{fontFamily:'var(--uid-font-family-display)', fontSize:22, fontWeight:600}}>1</div></div>
        <div><div className="text-xs tertiary">Ошибок</div><div style={{fontFamily:'var(--uid-font-family-display)', fontSize:22, fontWeight:600, color:'var(--uid-danger)'}}>3</div></div>
      </div>
    </div>
  );
}

/* Field gallery */
function FieldGallery() {
  return (
    <div className="pg">
      <div className="pg__hd">
        <div className="pg__title-wrap">
          <h1 className="pg__title">Field gallery</h1>
          <div className="pg__count">30+ типов полей · 8 групп</div>
        </div>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16 }}>
        <FieldDemo group="Текстовые" name="Input" desc="text/email/url/password"><Input icon="user" defaultValue="Иван Петров" /></FieldDemo>
        <FieldDemo group="Текстовые" name="Number" desc="numeric step"><Input defaultValue="42" iconRight="hash" /></FieldDemo>
        <FieldDemo group="Текстовые" name="Textarea" desc="multiline"><textarea defaultValue="Многострочный текст…" style={{minHeight:60,padding:8,border:'1px solid var(--uid-border-default)',borderRadius:6,fontFamily:'inherit',fontSize:13,resize:'none'}} /></FieldDemo>
        <FieldDemo group="Текстовые" name="Code" desc="syntax highlight"><div style={{padding:10,background:'var(--uid-surface-base)',border:'1px solid var(--uid-border-subtle)',borderRadius:6,fontFamily:'var(--uid-font-family-mono)',fontSize:12,lineHeight:1.5}}><span style={{color:'var(--uid-info)'}}>function</span> <span style={{color:'var(--uid-accent-text)'}}>hello</span>() {'{'}<br />&nbsp;&nbsp;<span style={{color:'var(--uid-text-tertiary)'}}>// ...</span><br />{'}'}</div></FieldDemo>

        <FieldDemo group="Выбор" name="Select" desc="single + multi"><Select defaultValue="ru" icon="languages"><option>Русский</option></Select></FieldDemo>
        <FieldDemo group="Выбор" name="Radio"><div className="vstack" style={{gap:6}}>{['Black','Teal','Auto'].map((o,i)=>(<label key={i} className="hstack" style={{gap:6,fontSize:13}}><span style={{width:14,height:14,borderRadius:'50%',border:`1.5px solid ${i===1?'var(--uid-accent)':'var(--uid-border-default)'}`,display:'grid',placeItems:'center'}}>{i===1 && <span style={{width:6,height:6,borderRadius:'50%',background:'var(--uid-accent)'}}/>}</span>{o}</label>))}</div></FieldDemo>
        <FieldDemo group="Выбор" name="Checkbox group"><div className="vstack" style={{gap:6}}><Checkbox checked label="Уведомления по email" /><Checkbox checked label="Уведомления в браузере" /><Checkbox label="SMS" /></div></FieldDemo>
        <FieldDemo group="Выбор" name="Switch"><div className="hstack"><Switch on label="Публиковать сразу" /></div></FieldDemo>

        <FieldDemo group="Дата/время" name="DatePicker"><Input icon="calendar" defaultValue="28.04.2026" /></FieldDemo>
        <FieldDemo group="Дата/время" name="DateRange"><Input icon="calendar" defaultValue="01.04.2026 — 30.04.2026" /></FieldDemo>
        <FieldDemo group="Дата/время" name="TimePicker"><Input icon="clock" defaultValue="14:23" /></FieldDemo>

        <FieldDemo group="Прочее" name="ColorPicker"><div className="hstack"><span style={{width:32,height:32,borderRadius:6,background:'#14b8a6',border:'1px solid var(--uid-border-default)'}}/><Input defaultValue="#14b8a6" /></div></FieldDemo>
        <FieldDemo group="Прочее" name="Slider"><div className="vstack" style={{gap:4}}><div style={{height:4,background:'var(--uid-surface-base)',borderRadius:2,position:'relative'}}><div style={{position:'absolute',left:0,top:0,bottom:0,width:'42%',background:'var(--uid-accent)',borderRadius:2}}/><div style={{position:'absolute',left:'42%',top:'50%',transform:'translate(-50%,-50%)',width:14,height:14,borderRadius:'50%',background:'var(--uid-accent)',border:'2px solid white',boxShadow:'var(--uid-shadow-sm)'}}/></div><div className="hstack text-xs tertiary"><span>0</span><span className="spacer"/><b style={{color:'var(--uid-text-primary)'}}>42</b><span className="spacer"/><span>100</span></div></div></FieldDemo>
        <FieldDemo group="Прочее" name="Rating"><div className="hstack" style={{gap:2}}>{[1,2,3,4,5].map(i=>(<Icon key={i} name="star" size={20} stroke={1.5} style={{color: i<=4?'var(--uid-warning)':'var(--uid-text-tertiary)', fill: i<=4?'var(--uid-warning)':'transparent'}} />))}</div></FieldDemo>
        <FieldDemo group="Прочее" name="FileUpload"><div className="drop" style={{padding:16,fontSize:12}}><Icon name="upload-cloud" size={20} /><b>Перетащите файлы</b></div></FieldDemo>

        <FieldDemo group="Связи" name="RelationSelect"><RelationSelectMock /></FieldDemo>
        <FieldDemo group="Связи" name="MorphSwitcher"><div className="hstack"><Select defaultValue="article" style={{flex:'0 0 120px'}}><option>article</option><option>page</option></Select><Input defaultValue="A-1284" style={{flex:1}} /></div></FieldDemo>
        <FieldDemo group="Связи" name="RelationTable"><div className="text-xs tertiary">Inline-table HasMany — см. Repeater в форме</div></FieldDemo>

        <FieldDemo group="Контент" name="WYSIWYG (mini)"><div style={{border:'1px solid var(--uid-border-default)',borderRadius:6,fontSize:12}}><div className="hstack" style={{padding:'4px 6px',borderBottom:'1px solid var(--uid-border-subtle)',gap:2,background:'var(--uid-surface-base)'}}>{['bold','italic','link','list','image'].map(i=><button key={i} className="tb__icon-btn" style={{width:22,height:22}}><Icon name={i} size={11}/></button>)}</div><div style={{padding:8,minHeight:50}}>Текст с <b>форматированием</b>…</div></div></FieldDemo>
        <FieldDemo group="Контент" name="Slug"><Input icon="link" defaultValue="vvedenie-v-laravel-12" readOnly /></FieldDemo>
        <FieldDemo group="Контент" name="KeyValue"><div className="vstack" style={{gap:4}}>{[['theme','dark'],['lang','ru']].map((kv,i)=>(<div key={i} className="hstack" style={{gap:4}}><Input defaultValue={kv[0]} style={{flex:1}}/><span className="tertiary">=</span><Input defaultValue={kv[1]} style={{flex:1}}/></div>))}</div></FieldDemo>
        <FieldDemo group="Контент" name="TagsInput"><TagsInputMock /></FieldDemo>

        <FieldDemo group="Иерархия" name="TreeSelect"><div style={{border:'1px solid var(--uid-border-default)',borderRadius:6,padding:8,fontSize:13}}><div>📁 Backend</div><div style={{paddingLeft:16,color:'var(--uid-accent-text)',fontWeight:500}}>↳ Laravel</div><div style={{paddingLeft:16}}>↳ Symfony</div><div>📁 Frontend</div></div></FieldDemo>
        <FieldDemo group="Иерархия" name="Cascader"><div className="hstack" style={{gap:4}}><Select style={{flex:1}}><option>Россия</option></Select><Select style={{flex:1}}><option>Москва</option></Select></div></FieldDemo>

        <FieldDemo group="Многоязычные" name="Translatable"><TranslatableMock /></FieldDemo>
      </div>
    </div>
  );
}

function FieldDemo({ group, name, desc, children }) {
  return (
    <div className="card">
      <div className="card__hd">
        <div>
          <div className="text-xs tertiary" style={{ textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: 600 }}>{group}</div>
          <div style={{ fontWeight: 600, fontSize: 14 }}>{name}</div>
          {desc && <div className="text-xs tertiary" style={{ marginTop: 2 }}>{desc}</div>}
        </div>
      </div>
      <div className="card__bd">{children}</div>
    </div>
  );
}

Object.assign(window, { LoginScreen, TwoFactor, Dashboard, ResourceView, Profile, NotificationDrawer, ImportWizard, FieldGallery });
