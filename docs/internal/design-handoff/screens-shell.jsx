// screens-shell.jsx — Shell, Resource List, Resource Form

const NAV = [
  { group: 'Контент', items: [
    { id: 'articles', label: 'Articles', icon: 'file-text', count: 1284 },
    { id: 'categories', label: 'Categories', icon: 'folder-tree', count: 24 },
    { id: 'media', label: 'Media', icon: 'image', count: 4521 },
  ]},
  { group: 'Аналитика', items: [
    { id: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard' },
    { id: 'reports', label: 'Reports', icon: 'bar-chart-3' },
  ]},
  { group: 'Настройки', items: [
    { id: 'users', label: 'Users', icon: 'users', count: 87 },
    { id: 'roles', label: 'Roles', icon: 'shield', count: 6 },
    { id: 'settings', label: 'Blog Settings', icon: 'settings-2' },
  ]},
];

function Sidebar({ active, collapsed, variant }) {
  return (
    <aside className="sb" data-variant={variant}>
      <div className="sb__brand">
        <div className="sb__brand-mark">L</div>
        <div className="sb__brand-name">Laravel Admin</div>
      </div>
      {!collapsed && (
        <div className="sb__tenant">
          <Icon name="building-2" size={13} />
          <span>Workspace</span>
          <b style={{ marginLeft: 'auto' }}>Acme Inc.</b>
          <Icon name="chevrons-up-down" size={12} />
        </div>
      )}
      <nav className="sb__nav">
        {NAV.map(g => (
          <div key={g.group} className="sb__group">
            <div className="sb__group-label">{g.group}</div>
            {g.items.map(it => (
              <a key={it.id} className={`sb__item ${active === it.id ? 'is-active' : ''}`}>
                <Icon name={it.icon} size={16} className="ico" />
                <span className="sb__item-label">{it.label}</span>
                {it.count != null && <span className="count">{it.count.toLocaleString('ru')}</span>}
              </a>
            ))}
          </div>
        ))}
      </nav>
      <div className="sb__foot">
        <span className="sb__foot-text">v2.4.1</span>
        <a className="sb__foot-text" style={{ color: 'var(--uid-text-tertiary)' }}>Docs</a>
      </div>
    </aside>
  );
}

function Topbar({ onToggleSb, onOpenNotif, onToggleTheme, dark, crumbs = [] }) {
  return (
    <header className="tb">
      <button className="tb__icon-btn" onClick={onToggleSb} title="Toggle sidebar">
        <Icon name="panel-left" size={16} />
      </button>
      <div className="crumbs">
        {crumbs.map((c, i) => (
          <React.Fragment key={i}>
            {i > 0 && <span className="sep"><Icon name="chevron-right" size={12} /></span>}
            <span className={i === crumbs.length - 1 ? 'cur' : ''}>{c}</span>
          </React.Fragment>
        ))}
      </div>
      <div className="tb__spacer" />
      <div className="tb__search">
        <Icon name="search" size={14} />
        <span>Поиск везде…</span>
        <kbd>⌘K</kbd>
      </div>
      <button className="tb__icon-btn" onClick={onOpenNotif} title="Notifications">
        <Icon name="bell" size={16} />
        <span className="tb__bell-dot">3</span>
      </button>
      <button className="tb__icon-btn" onClick={onToggleTheme} title="Theme">
        <Icon name={dark ? 'sun' : 'moon'} size={16} />
      </button>
      <button className="tb__icon-btn" title="Locale" style={{ width: 'auto', padding: '0 8px', fontSize: 12 }}>
        <Icon name="globe" size={14} /> RU
      </button>
      <Avatar name="Иван Петров" size="sm" />
    </header>
  );
}

function ImpersonationBanner() {
  return (
    <div className="imp-banner">
      <Icon name="user-cog" size={13} />
      Вы вошли как <b style={{ fontWeight: 600 }}>Анна Сидорова</b> · режим имперсонации
      <button>Выйти из режима</button>
    </div>
  );
}

/* =================== Resource List =================== */
const ARTICLES = [
  { id: 'A-1284', title: 'Введение в Laravel 12: что нового в фреймворке', author: 'Иван Петров', status: 'published', cat: 'Backend', views: 12483, updated: '2 мин назад', publishedAt: '2026-04-28' },
  { id: 'A-1283', title: 'Filament vs Nova: сравнение админок для Laravel', author: 'Анна Сидорова', status: 'published', cat: 'Backend', views: 8912, updated: '14 мин назад', publishedAt: '2026-04-27' },
  { id: 'A-1282', title: 'Архитектура SaaS-приложений: multi-tenancy паттерны', author: 'Дмитрий Орлов', status: 'review', cat: 'Architecture', views: 0, updated: '1 ч назад', publishedAt: '—' },
  { id: 'A-1281', title: 'Vue 3 + TypeScript: типизированные Composables', author: 'Мария Кузнецова', status: 'draft', cat: 'Frontend', views: 0, updated: '3 ч назад', publishedAt: '—' },
  { id: 'A-1280', title: 'Eloquent Performance: N+1, eager loading, индексы', author: 'Иван Петров', status: 'published', cat: 'Backend', views: 24102, updated: 'вчера', publishedAt: '2026-04-25' },
  { id: 'A-1279', title: 'Pinia store patterns для крупных Vue-приложений', author: 'Мария Кузнецова', status: 'published', cat: 'Frontend', views: 5443, updated: 'вчера', publishedAt: '2026-04-24' },
  { id: 'A-1278', title: 'Tiptap WYSIWYG: расширения и кастомизация', author: 'Анна Сидорова', status: 'archived', cat: 'Frontend', views: 1209, updated: '3 дня назад', publishedAt: '2026-04-21' },
  { id: 'A-1277', title: 'Laravel Octane: RoadRunner или Swoole?', author: 'Дмитрий Орлов', status: 'published', cat: 'Backend', views: 9871, updated: '4 дня назад', publishedAt: '2026-04-20' },
  { id: 'A-1276', title: 'Тестирование Vue-компонентов: Vitest + Testing Library', author: 'Мария Кузнецова', status: 'review', cat: 'Frontend', views: 0, updated: '5 дней назад', publishedAt: '—' },
  { id: 'A-1275', title: 'Очереди в Laravel: Horizon, supervisors, retry-стратегии', author: 'Иван Петров', status: 'published', cat: 'Backend', views: 6712, updated: 'неделю назад', publishedAt: '2026-04-17' },
  { id: 'A-1274', title: 'CSS Grid в продакшене: кейсы и подводные камни', author: 'Анна Сидорова', status: 'draft', cat: 'Frontend', views: 0, updated: 'неделю назад', publishedAt: '—' },
  { id: 'A-1273', title: 'PostgreSQL vs MySQL для Laravel: бенчмарки 2026', author: 'Дмитрий Орлов', status: 'published', cat: 'Backend', views: 18302, updated: '8 дней назад', publishedAt: '2026-04-15' },
];

const STATUS_VARIANTS = {
  published: { variant: 'success', label: 'Published' },
  review: { variant: 'warning', label: 'In review' },
  draft: { variant: 'default', label: 'Draft' },
  archived: { variant: 'info', label: 'Archived' },
};

function ResourceList({ density, polling, listState, filterVariant, bulkMode, onOpenForm, onOpenView }) {
  const [selected, setSelected] = useState(bulkMode ? new Set(['A-1284', 'A-1283', 'A-1282']) : new Set());
  const [editing, setEditing] = useState(null);
  const [editValue, setEditValue] = useState('');
  const [groupBy, setGroupBy] = useState(false);
  const [updatedRow, setUpdatedRow] = useState(null);

  // Sync bulk mode from tweak
  useEffect(() => {
    if (bulkMode && selected.size === 0) setSelected(new Set(['A-1284', 'A-1283', 'A-1282']));
    if (!bulkMode && selected.size > 0) setSelected(new Set());
  }, [bulkMode]);

  // Polling: highlight a row periodically
  useEffect(() => {
    if (!polling || listState !== 'ideal') return;
    const t = setInterval(() => {
      const idx = Math.floor(Math.random() * 3);
      setUpdatedRow(ARTICLES[idx].id);
      setTimeout(() => setUpdatedRow(null), 1400);
    }, 6000);
    return () => clearInterval(t);
  }, [polling, listState]);

  const toggle = id => {
    const s = new Set(selected);
    if (s.has(id)) s.delete(id); else s.add(id);
    setSelected(s);
  };
  const toggleAll = () => {
    if (selected.size === ARTICLES.length) setSelected(new Set());
    else setSelected(new Set(ARTICLES.map(a => a.id)));
  };

  const startEdit = (id, val) => { setEditing(id); setEditValue(val); };
  const finishEdit = () => { setEditing(null); };

  return (
    <div className="pg">
      <div className="pg__hd">
        <div className="pg__title-wrap">
          <div className="hstack">
            <h1 className="pg__title">Articles</h1>
            {polling && (
              <span className="poll-ind">
                <span className="dot dot--pulse" />
                обновлено только что
              </span>
            )}
          </div>
          <div className="pg__count">{ARTICLES.length} из 1284 записей</div>
        </div>
        <div className="pg__actions">
          <Btn variant="ghost" icon="bookmark"><span className="muted">Все статьи</span><Icon name="chevron-down" size={12} /></Btn>
          <Btn icon="more-horizontal" />
          <Btn icon="upload">Import</Btn>
          <Btn variant="primary" icon="plus" onClick={onOpenForm}>Создать</Btn>
        </div>
      </div>

      {/* Group-by chips bar */}
      {groupBy && (
        <div className="gb">
          <button className="gb__chip is-on">Все <b>1284</b></button>
          <button className="gb__chip">Published <b>892</b></button>
          <button className="gb__chip">In review <b>23</b></button>
          <button className="gb__chip">Draft <b>312</b></button>
          <button className="gb__chip">Archived <b>57</b></button>
        </div>
      )}

      {/* Filters / Bulk toolbar */}
      {selected.size > 0 ? (
        <BulkToolbar
          count={selected.size}
          total={1284}
          onCancel={() => setSelected(new Set())}
        />
      ) : (
        <FiltersBar variant={filterVariant} groupBy={groupBy} onToggleGroupBy={() => setGroupBy(g => !g)} />
      )}

      {/* Table or empty/loading/error */}
      {listState === 'empty' ? <EmptyState />
       : listState === 'loading' ? <LoadingState />
       : listState === 'error' ? <ErrorState />
       : (
        <div className="tbl-wrap">
          <table className="tbl">
            <thead>
              <tr>
                <th className="checkbox-cell">
                  <Checkbox
                    checked={selected.size === ARTICLES.length}
                    indeterminate={selected.size > 0 && selected.size < ARTICLES.length}
                    onChange={toggleAll}
                  />
                </th>
                <th><span className="sortable">ID <Icon name="arrow-up-down" size={11} /></span></th>
                <th><span className="sortable">Title <Icon name="arrow-down" size={11} /></span></th>
                <th>Status</th>
                <th>Author</th>
                <th>Category</th>
                <th className="right">Views</th>
                <th>Published</th>
                <th>Updated</th>
                <th className="actions"></th>
              </tr>
            </thead>
            <tbody>
              {ARTICLES.map(a => {
                const sv = STATUS_VARIANTS[a.status];
                const isSel = selected.has(a.id);
                const isUp = updatedRow === a.id;
                return (
                  <tr key={a.id} className={`${isSel ? 'is-selected' : ''} ${isUp ? 'is-updated' : ''}`}>
                    <td className="checkbox-cell">
                      <Checkbox checked={isSel} onChange={() => toggle(a.id)} />
                    </td>
                    <td className="mono tertiary text-xs">{a.id}</td>
                    <td>
                      <span className={`cell-edit ${editing === a.id ? 'is-editing' : ''}`}
                            onDoubleClick={() => startEdit(a.id, a.title)}>
                        {editing === a.id
                          ? <input autoFocus value={editValue} onChange={e => setEditValue(e.target.value)} onBlur={finishEdit} onKeyDown={e => { if (e.key === 'Enter' || e.key === 'Escape') finishEdit(); }} />
                          : <span className="truncate" onClick={onOpenView}>{a.title}</span>}
                      </span>
                    </td>
                    <td><Badge variant={sv.variant} dot>{sv.label}</Badge></td>
                    <td><span className="hstack" style={{ gap: 6 }}><Avatar name={a.author} size="sm" /><span>{a.author}</span></span></td>
                    <td><Badge>{a.cat}</Badge></td>
                    <td className="right">{a.views > 0 ? a.views.toLocaleString('ru') : <span className="tertiary">—</span>}</td>
                    <td className="tertiary text-xs">{a.publishedAt}</td>
                    <td className="muted text-xs">{a.updated}</td>
                    <td className="actions">
                      <Btn variant="ghost" size="sm" icon="more-horizontal" />
                    </td>
                  </tr>
                );
              })}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan="6" className="muted">Σ Summary (текущая страница)</td>
                <td className="right">87 654</td>
                <td colSpan="3"></td>
              </tr>
            </tfoot>
          </table>
          <div className="pg-foot">
            <div className="pager">
              <button><Icon name="chevron-left" size={12} /></button>
              <button className="is-on">1</button>
              <button>2</button>
              <button>3</button>
              <span className="gap">…</span>
              <button>107</button>
              <button><Icon name="chevron-right" size={12} /></button>
            </div>
            <div className="pg-foot__spacer" />
            <span>Показывать</span>
            <select style={{ height: 26, border: '1px solid var(--uid-border-subtle)', borderRadius: 6, background: 'var(--uid-surface-raised)', fontSize: 12, padding: '0 6px' }}>
              <option>25</option><option>50</option><option>100</option>
            </select>
            <span>на странице</span>
          </div>
        </div>
      )}
    </div>
  );
}

function FiltersBar({ variant, groupBy, onToggleGroupBy }) {
  if (variant === 'chips') {
    return (
      <div className="fb" style={{ alignItems: 'center' }}>
        <div className="fb__search">
          <Icon name="search" size={13} className="ico" />
          <input placeholder="Поиск по статьям…" />
        </div>
        <span className="fb__chip is-on">Status: Published <Icon name="x" size={11} className="x" /></span>
        <span className="fb__chip is-on">Author: Иван П. <Icon name="x" size={11} className="x" /></span>
        <span className="fb__chip">+ добавить фильтр</span>
        <span className="fb__chip"><Icon name="x" size={11} />Сбросить</span>
        <div className="fb__spacer" />
        <button className={`fb__chip ${groupBy ? 'is-on' : ''}`} onClick={onToggleGroupBy}>
          <Icon name="layout-grid" size={12} /> Группировать
        </button>
        <span className="fb__chip"><Icon name="columns-3" size={12} /> Колонки</span>
        <span className="fb__chip"><Icon name="bookmark" size={12} /> Сохранить вид</span>
      </div>
    );
  }
  if (variant === 'panel') {
    return (
      <div className="fb" style={{ flexDirection: 'column', alignItems: 'stretch', gap: 10, padding: '14px 16px' }}>
        <div className="hstack" style={{ gap: 8 }}>
          <div className="fb__search" style={{ maxWidth: 'none', flex: 1 }}>
            <Icon name="search" size={13} className="ico" />
            <input placeholder="Поиск по статьям…" />
          </div>
          <div className="fb__spacer" />
          <button className={`fb__chip ${groupBy ? 'is-on' : ''}`} onClick={onToggleGroupBy}>
            <Icon name="layout-grid" size={12} /> Группировать
          </button>
          <span className="fb__chip"><Icon name="columns-3" size={12} /> Колонки</span>
          <span className="fb__chip"><Icon name="bookmark" size={12} /> Сохранить</span>
        </div>
        <div className="hstack" style={{ gap: 8, flexWrap: 'wrap' }}>
          <PanelFilter label="Status" value="Published, Draft" />
          <PanelFilter label="Author" value="Все" />
          <PanelFilter label="Category" value="Backend" />
          <PanelFilter label="Published" value="За 30 дней" icon="calendar" />
          <PanelFilter label="Trashed" value="Без удалённых" icon="trash-2" />
          <Btn variant="ghost" size="sm" icon="rotate-ccw">Сбросить</Btn>
        </div>
      </div>
    );
  }
  // default: bar
  return (
    <div className="fb">
      <div className="fb__search">
        <Icon name="search" size={13} className="ico" />
        <input placeholder="Поиск по статьям…" />
      </div>
      <div className="fb__sep" />
      <span className="fb__chip is-on">Status: Published, Draft <Icon name="x" size={11} className="x" /></span>
      <span className="fb__chip"><Icon name="user" size={11} /> Author</span>
      <span className="fb__chip"><Icon name="folder" size={11} /> Category</span>
      <span className="fb__chip"><Icon name="calendar" size={11} /> Published</span>
      <span className="fb__chip">+ Filter</span>
      <Btn variant="ghost" size="sm" icon="rotate-ccw">Сбросить</Btn>
      <div className="fb__spacer" />
      <button className={`fb__chip ${groupBy ? 'is-on' : ''}`} onClick={onToggleGroupBy}>
        <Icon name="layout-grid" size={12} /> Группировать
      </button>
      <span className="fb__chip"><Icon name="columns-3" size={12} /> Колонки</span>
      <span className="fb__chip"><Icon name="bookmark" size={12} /> Сохранить</span>
    </div>
  );
}

function PanelFilter({ label, value, icon }) {
  return (
    <span className="fb__chip" style={{ height: 30, fontSize: 12 }}>
      {icon && <Icon name={icon} size={12} />}
      <span className="tertiary">{label}:</span>
      <b style={{ fontWeight: 500, color: 'var(--uid-text-primary)' }}>{value}</b>
      <Icon name="chevron-down" size={11} />
    </span>
  );
}

function BulkToolbar({ count, total, onCancel }) {
  return (
    <div className="bulk">
      <Btn variant="ghost" size="sm" icon="x" onClick={onCancel} style={{ color: 'rgba(255,255,255,0.7)' }} />
      <span>Выбрано <b>{count}</b></span>
      <button className="bulk__sel-all">Выбрать все {total.toLocaleString('ru')}</button>
      <div className="bulk__spacer" />
      <button className="bulk__btn"><Icon name="check-circle-2" size={13} /> Опубликовать</button>
      <button className="bulk__btn"><Icon name="archive" size={13} /> Архивировать</button>
      <button className="bulk__btn"><Icon name="download" size={13} /> Экспорт</button>
      <button className="bulk__btn bulk__btn--danger"><Icon name="trash-2" size={13} /> Удалить</button>
      <button className="bulk__btn"><Icon name="more-horizontal" size={13} /></button>
    </div>
  );
}

/* ----- States ----- */
function EmptyState({ tone = 'pictogram' }) {
  return (
    <div className="tbl-wrap tbl-wrap--top">
      <div className="state">
        <div className="state__art">
          <Icon name="inbox" size={36} stroke={1.4} />
        </div>
        <div className="state__title">Записей нет</div>
        <div className="state__body">Здесь появятся ваши статьи. Создайте первую — это займёт меньше минуты.</div>
        <Btn variant="primary" icon="plus">Создать первую статью</Btn>
      </div>
    </div>
  );
}

function LoadingState() {
  return (
    <div className="tbl-wrap tbl-wrap--top">
      <table className="tbl">
        <thead>
          <tr>
            <th className="checkbox-cell"><Checkbox /></th>
            <th>ID</th><th>Title</th><th>Status</th><th>Author</th><th>Category</th>
            <th className="right">Views</th><th>Published</th><th>Updated</th><th></th>
          </tr>
        </thead>
        <tbody>
          {Array.from({ length: 8 }).map((_, i) => (
            <tr key={i} style={{ height: 'var(--row-h)' }}>
              <td className="checkbox-cell"><Checkbox /></td>
              <td><Skel w={42} /></td>
              <td><Skel w={Math.floor(180 + Math.random() * 100)} /></td>
              <td><Skel w={68} h={18} /></td>
              <td><Skel w={120} /></td>
              <td><Skel w={60} h={18} /></td>
              <td className="right"><Skel w={42} /></td>
              <td><Skel w={68} /></td>
              <td><Skel w={68} /></td>
              <td className="actions"><Skel w={20} /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ErrorState() {
  return (
    <div className="tbl-wrap tbl-wrap--top">
      <div className="state">
        <div className="state__art" style={{ color: 'var(--uid-danger)', borderColor: 'color-mix(in srgb, var(--uid-danger) 30%, var(--uid-border-subtle))' }}>
          <Icon name="cloud-off" size={36} stroke={1.4} />
        </div>
        <div className="state__title">Не удалось загрузить</div>
        <div className="state__body">Сервер ответил <span className="mono">503</span>. Проверьте соединение и повторите запрос.</div>
        <div className="hstack">
          <Btn icon="rotate-cw">Повторить</Btn>
          <Btn variant="ghost" icon="copy">Скопировать trace-id</Btn>
        </div>
      </div>
    </div>
  );
}

/* =================== Resource Form =================== */
function ResourceForm({ density }) {
  const [tab, setTab] = useState('content');
  const [dirty, setDirty] = useState(true);
  return (
    <div className="pg">
      <div className="pg__hd">
        <div className="pg__title-wrap">
          <div className="hstack" style={{ gap: 6, fontSize: 12, color: 'var(--uid-text-tertiary)' }}>
            <a className="hstack" style={{ gap: 4 }}><Icon name="arrow-left" size={12} /> Articles</a>
          </div>
          <h1 className="pg__title">Введение в Laravel 12: что нового в фреймворке</h1>
          <div className="pg__count">
            <Badge variant="success" dot>Published</Badge>
            <span style={{ marginLeft: 8 }}>Изменено 2 мин назад · Иван Петров</span>
          </div>
        </div>
        <div className="pg__actions">
          <Btn variant="ghost" icon="eye">Preview</Btn>
          <Btn icon="more-horizontal" />
          <Btn danger icon="trash-2">Удалить</Btn>
          <Btn variant="primary" icon="check">Сохранить</Btn>
        </div>
      </div>

      <div className="card" style={{ marginBottom: 16 }}>
        <div className="form-tabs">
          {[
            { id: 'content', label: 'Содержимое', icon: 'file-text' },
            { id: 'seo', label: 'SEO', icon: 'globe', count: 3 },
            { id: 'media', label: 'Медиа', icon: 'image' },
            { id: 'meta', label: 'Метаданные', icon: 'tag' },
          ].map(t => (
            <button key={t.id} className={tab === t.id ? 'is-on' : ''} onClick={() => setTab(t.id)}>
              <span className="hstack" style={{ gap: 6 }}>
                <Icon name={t.icon} size={13} />
                {t.label}
                {t.count && <Badge variant="warning">{t.count}</Badge>}
              </span>
            </button>
          ))}
        </div>

        <div style={{ padding: 24 }}>
          <div className="form-grid">
            <div className="main">
              <FormBlock title="Основное" desc="Заголовок и URL — публичная карточка статьи.">
                <Field label="Заголовок" required>
                  <Input value="Введение в Laravel 12: что нового в фреймворке" onChange={() => {}} />
                </Field>
                <div className="form-cols form-cols--2">
                  <Field label="Slug" help="auto-генерируется из заголовка">
                    <Input value="vvedenie-v-laravel-12" readOnly icon="link" />
                  </Field>
                  <Field label="Категория" required>
                    <Select value="backend" onChange={() => {}} icon="folder">
                      <option value="backend">Backend</option>
                      <option value="frontend">Frontend</option>
                      <option value="architecture">Architecture</option>
                    </Select>
                  </Field>
                </div>
                <Field label="Краткое описание" help="160–200 символов. Используется в превью на главной и в SEO.">
                  <textarea
                    defaultValue="В Laravel 12 фокус на DX: новый scheduler, улучшенная типизация Eloquent-коллекций, нативная поддержка Dependency Injection в роутах."
                    style={{
                      minHeight: 80, padding: 10, resize: 'vertical',
                      border: '1px solid var(--uid-border-default)',
                      borderRadius: 'var(--uid-radius-md)',
                      background: 'var(--uid-surface-raised)',
                      fontSize: 13, fontFamily: 'inherit',
                    }}
                  />
                </Field>
              </FormBlock>

              <FormBlock title="Содержимое" desc="WYSIWYG-редактор с поддержкой Markdown-сокращений.">
                <WysiwygMock />
              </FormBlock>

              <FormBlock title="Связи">
                <Field label="Tags">
                  <TagsInputMock />
                </Field>
                <Field label="Связанные статьи" help="HasMany — Repeater со встроенными формами.">
                  <RepeaterMock />
                </Field>
              </FormBlock>
            </div>

            <div className="side">
              <FormBlock title="Публикация">
                <Field label="Статус">
                  <Select icon="circle-dot" defaultValue="published">
                    <option value="draft">Draft</option>
                    <option value="review">In review</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                  </Select>
                </Field>
                <Field label="Дата публикации">
                  <Input value="28.04.2026" icon="calendar" />
                </Field>
                <Field label="Автор">
                  <RelationSelectMock />
                </Field>
                <Switch on label="Показывать на главной" />
                <Switch label="Только для подписчиков" />
              </FormBlock>

              <FormBlock title="Обложка">
                <FileMock />
              </FormBlock>

              <FormBlock title="Локализация">
                <TranslatableMock />
              </FormBlock>
            </div>
          </div>

          <div className="save-bar">
            {dirty && (
              <div className="dirty">
                <Icon name="circle-dot" size={11} /> Несохранённые изменения
              </div>
            )}
            <Btn variant="ghost">Отмена</Btn>
            <Btn>Сохранить и продолжить</Btn>
            <Btn variant="primary" icon="check" onClick={() => setDirty(false)}>Сохранить</Btn>
          </div>
        </div>
      </div>
    </div>
  );
}

function FormBlock({ title, desc, children }) {
  return (
    <section className="form-block">
      <header className="form-block__hd">
        <div className="form-block__title">{title}</div>
        {desc && <div className="form-block__desc">{desc}</div>}
      </header>
      <div className="vstack" style={{ gap: 16 }}>{children}</div>
    </section>
  );
}

function WysiwygMock() {
  return (
    <div style={{ border: '1px solid var(--uid-border-default)', borderRadius: 'var(--uid-radius-md)', overflow: 'hidden' }}>
      <div className="hstack" style={{ borderBottom: '1px solid var(--uid-border-subtle)', padding: '6px 8px', gap: 2, flexWrap: 'wrap', background: 'var(--uid-surface-base)' }}>
        {['heading-1', 'heading-2', 'bold', 'italic', 'underline', 'strikethrough', 'code', 'quote', 'list', 'list-ordered', 'link', 'image', 'table'].map(i => (
          <button key={i} className="tb__icon-btn" style={{ width: 26, height: 26 }}>
            <Icon name={i} size={13} />
          </button>
        ))}
        <div style={{ flex: 1 }} />
        <button className="tb__icon-btn" style={{ width: 'auto', padding: '0 8px', fontSize: 11 }}>
          <Icon name="undo-2" size={12} />
        </button>
        <button className="tb__icon-btn" style={{ width: 'auto', padding: '0 8px', fontSize: 11 }}>
          <Icon name="redo-2" size={12} />
        </button>
      </div>
      <div style={{ padding: 16, minHeight: 180, fontSize: 14, lineHeight: 1.6 }}>
        <p style={{ marginTop: 0, color: 'var(--uid-text-primary)' }}>
          Laravel 12 — это первый «long-term-support» релиз с поддержкой <b>PHP 8.4</b>. Команда переработала несколько ключевых систем, и в этой статье разберём только то, что реально меняет повседневную разработку.
        </p>
        <h3 style={{ fontSize: 18, marginTop: 24, marginBottom: 8 }}>Основные изменения</h3>
        <ul style={{ paddingLeft: 22, color: 'var(--uid-text-primary)' }}>
          <li>Новый scheduler с поддержкой <span className="uid-code">cron-style timezones</span></li>
          <li>Pipeline middleware теперь типизированы дженериками</li>
          <li style={{ color: 'var(--uid-text-tertiary)' }}>… продолжайте печатать</li>
        </ul>
      </div>
      <div className="hstack" style={{ borderTop: '1px solid var(--uid-border-subtle)', padding: '6px 12px', fontSize: 11, color: 'var(--uid-text-tertiary)', background: 'var(--uid-surface-base)' }}>
        <span>Markdown поддерживается</span>
        <div style={{ flex: 1 }} />
        <span>312 слов · 2 мин чтения</span>
      </div>
    </div>
  );
}

function TagsInputMock() {
  const tags = ['laravel', 'php', 'backend', 'release-notes'];
  return (
    <div className="input" style={{ flexWrap: 'wrap', height: 'auto', minHeight: 32, gap: 6, padding: 6 }}>
      {tags.map(t => <Badge key={t} variant="accent">#{t}<Icon name="x" size={10} /></Badge>)}
      <input placeholder="Добавить тег…" style={{ flex: 1, minWidth: 100, height: 22, border: 0, outline: 0, background: 'transparent', fontSize: 13 }} />
    </div>
  );
}

function RelationSelectMock() {
  return (
    <div className="input">
      <Avatar name="Иван Петров" size="sm" />
      <span style={{ flex: 1, fontSize: 13 }}>Иван Петров</span>
      <span className="tertiary text-xs">@ivan.petrov</span>
      <Icon name="chevron-down" size={13} className="ico" />
    </div>
  );
}

function RepeaterMock() {
  return (
    <div className="vstack" style={{ gap: 8 }}>
      {[
        { t: 'Filament vs Nova: сравнение админок для Laravel', s: 'related' },
        { t: 'Laravel Octane: RoadRunner или Swoole?', s: 'related' },
      ].map((it, i) => (
        <div key={i} className="hstack" style={{ padding: 8, border: '1px solid var(--uid-border-subtle)', borderRadius: 'var(--uid-radius-md)', background: 'var(--uid-surface-base)' }}>
          <Icon name="grip-vertical" size={14} className="tertiary" />
          <span style={{ fontSize: 13, flex: 1 }}>{it.t}</span>
          <Badge>{it.s}</Badge>
          <button className="tb__icon-btn" style={{ width: 24, height: 24 }}><Icon name="x" size={12} /></button>
        </div>
      ))}
      <Btn variant="ghost" size="sm" icon="plus" block>Добавить связь</Btn>
    </div>
  );
}

function FileMock() {
  return (
    <div style={{ display: 'grid', gap: 8 }}>
      <div style={{ aspectRatio: '16/9', borderRadius: 'var(--uid-radius-md)', background: 'linear-gradient(135deg, var(--uid-color-zinc-200), var(--uid-color-zinc-300))', position: 'relative', overflow: 'hidden' }}>
        <div style={{ position: 'absolute', inset: 0, display: 'grid', placeItems: 'center', color: 'var(--uid-color-zinc-500)', fontSize: 12 }}>cover.jpg · 1920×1080</div>
      </div>
      <div className="hstack">
        <Btn variant="ghost" size="sm" icon="upload">Заменить</Btn>
        <Btn variant="ghost" size="sm" icon="crop">Обрезать</Btn>
        <div className="spacer" />
        <Btn variant="ghost" size="sm" icon="trash-2" />
      </div>
    </div>
  );
}

function TranslatableMock() {
  const [lang, setLang] = useState('ru');
  return (
    <div>
      <div className="hstack" style={{ gap: 0, marginBottom: 8, borderBottom: '1px solid var(--uid-border-subtle)' }}>
        {['ru', 'en', 'de'].map(l => (
          <button key={l} onClick={() => setLang(l)}
            style={{
              appearance: 'none', background: 'transparent', border: 0,
              padding: '6px 10px', fontSize: 12, fontWeight: 500,
              color: lang === l ? 'var(--uid-text-primary)' : 'var(--uid-text-tertiary)',
              borderBottom: lang === l ? '2px solid var(--uid-accent)' : '2px solid transparent',
              marginBottom: -1, textTransform: 'uppercase', letterSpacing: '0.05em',
            }}>{l}</button>
        ))}
      </div>
      <Input value={lang === 'ru' ? 'Введение в Laravel 12' : lang === 'en' ? 'Introduction to Laravel 12' : 'Einführung in Laravel 12'} onChange={() => {}} />
    </div>
  );
}

Object.assign(window, { Sidebar, Topbar, ImpersonationBanner, ResourceList, ResourceForm });
